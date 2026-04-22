<?php

namespace Gurento\KafkaConsumer\Services;

use Gurento\KafkaConsumer\Models\KafkaConsumeLog;
use Gurento\KafkaConsumer\Models\KafkaTopic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

class KafkaConsumerService
{
    public function handle(string $topicName, array $payload, array $metadata = []): ?KafkaConsumeLog
    {
        $topic = KafkaTopic::query()->where('topic', $topicName)->where('is_active', true)->first();

        if (! $topic) {
            Log::warning("KafkaConsumer: no active topic config [{$topicName}]");
            return null;
        }

        $this->touchHeartbeat($topic);

        try {
            $upsertValue = $this->processPayload($topic, $payload);

            $topic->increment('messages_consumed');
            $topic->update([
                'last_consumed_at' => now(),
                'consumer_last_heartbeat_at' => now(),
                'consumer_last_error' => null,
                'consumer_lag_seconds' => 0,
            ]);

            return KafkaConsumeLog::create([
                'kafka_topic_id' => $topic->id,
                'status' => 'success',
                'attempt_count' => 1,
                'upsert_key_value' => $upsertValue,
                'payload' => $payload,
                'consumed_at' => now(),
                'last_attempt_at' => now(),
                'retryable' => false,
                'kafka_partition' => $metadata['kafka_partition'] ?? null,
                'kafka_offset' => $metadata['kafka_offset'] ?? null,
                'kafka_key' => isset($metadata['kafka_key']) ? (string) $metadata['kafka_key'] : null,
            ]);
        } catch (Throwable $e) {
            $attempt = 1;
            $retryable = $attempt < (int) ($topic->max_reconsume_attempts ?: config('kafka-consumer.max_reconsume_attempts', 3));
            $backoff = max(5, (int) ($topic->retry_backoff_seconds ?: config('kafka-consumer.retry_backoff_seconds', 60)));

            $topic->increment('messages_failed');
            $topic->update([
                'consumer_last_error' => $e->getMessage(),
                'consumer_last_heartbeat_at' => now(),
                'consumer_lag_seconds' => $this->resolveLag($topic),
            ]);

            return KafkaConsumeLog::create([
                'kafka_topic_id' => $topic->id,
                'status' => 'failed',
                'attempt_count' => $attempt,
                'payload' => $payload,
                'error' => $e->getMessage(),
                'consumed_at' => now(),
                'last_attempt_at' => now(),
                'next_retry_at' => $retryable ? now()->addSeconds($backoff) : null,
                'retryable' => $retryable,
                'kafka_partition' => $metadata['kafka_partition'] ?? null,
                'kafka_offset' => $metadata['kafka_offset'] ?? null,
                'kafka_key' => isset($metadata['kafka_key']) ? (string) $metadata['kafka_key'] : null,
            ]);
        }
    }

    public function reconsumeFailedMessages(KafkaTopic $topic, int $limit = 50): array
    {
        $attempted = 0;
        $success = 0;
        $failed = 0;

        $logs = $topic->logs()
            ->where('status', 'failed')
            ->where('retryable', true)
            ->where(fn ($q) => $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now()))
            ->orderBy('consumed_at')
            ->limit(max(1, min(500, $limit)))
            ->get();

        foreach ($logs as $log) {
            $attempted++;
            $attempt = ((int) $log->attempt_count) + 1;

            try {
                $this->processPayload($topic, is_array($log->payload) ? $log->payload : []);

                $topic->increment('messages_consumed');
                $topic->increment('messages_reconsumed');
                $topic->update([
                    'last_consumed_at' => now(),
                    'consumer_last_heartbeat_at' => now(),
                    'consumer_last_error' => null,
                    'consumer_lag_seconds' => 0,
                ]);

                $log->update([
                    'status' => 'reconsumed_success',
                    'attempt_count' => $attempt,
                    'last_attempt_at' => now(),
                    'retryable' => false,
                    'next_retry_at' => null,
                    'resolved_at' => now(),
                    'is_reconsumed' => true,
                    'error' => null,
                ]);

                $success++;
            } catch (Throwable $e) {
                $max = (int) ($topic->max_reconsume_attempts ?: config('kafka-consumer.max_reconsume_attempts', 3));
                $retryable = $attempt < $max;
                $backoff = max(5, (int) ($topic->retry_backoff_seconds ?: config('kafka-consumer.retry_backoff_seconds', 60)));

                $topic->increment('messages_failed');
                $topic->update([
                    'consumer_last_error' => $e->getMessage(),
                    'consumer_last_heartbeat_at' => now(),
                    'consumer_lag_seconds' => $this->resolveLag($topic),
                ]);

                $log->update([
                    'attempt_count' => $attempt,
                    'last_attempt_at' => now(),
                    'retryable' => $retryable,
                    'next_retry_at' => $retryable ? now()->addSeconds($backoff * max(1, $attempt - 1)) : null,
                    'is_reconsumed' => true,
                    'error' => $e->getMessage(),
                ]);

                $failed++;
            }
        }

        return compact('attempted', 'success', 'failed');
    }

    private function processPayload(KafkaTopic $topic, array $payload): string
    {
        $data = collect($payload)->except($topic->exclude_keys ?? [])->all();

        $mapped = [];
        foreach ($topic->resolvedFieldMap() as $from => $to) {
            if (array_key_exists($from, $data)) {
                $mapped[$to] = $data[$from];
            }
        }

        $upsertKey = $topic->upsert_key;
        $upsertValue = $mapped[$upsertKey] ?? $data[$upsertKey] ?? $data['uuid'] ?? $data['id'] ?? null;

        if (! $upsertValue) {
            throw new \RuntimeException("Upsert key [{$upsertKey}] missing from payload/mapping.");
        }

        $modelClass = $topic->model_class;

        if (! class_exists($modelClass)) {
            throw new \RuntimeException("Model class [{$modelClass}] does not exist.");
        }

        if (! isset($mapped[$upsertKey])) {
            $mapped[$upsertKey] = $upsertValue;
        }

        /** @var Model $modelClass */
        $modelClass::query()->updateOrCreate([$upsertKey => $upsertValue], $mapped);

        return (string) $upsertValue;
    }

    private function touchHeartbeat(KafkaTopic $topic): void
    {
        $topic->update([
            'consumer_last_heartbeat_at' => now(),
            'consumer_lag_seconds' => $this->resolveLag($topic),
        ]);
    }

    private function resolveLag(KafkaTopic $topic): ?int
    {
        $lag = $topic->last_consumed_at?->diffInSeconds(now());

        return $lag === null ? null : (int) floor($lag);
    }
}
