<?php

namespace Gurento\KafkaConsumer\Services;

use Gurento\KafkaConsumer\Models\KafkaConsumeLog;
use Gurento\KafkaConsumer\Models\KafkaTopic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

class KafkaConsumerService
{
    private const STATUS_SUCCESS = 'success';

    private const STATUS_FAILED = 'failed';

    private const STATUS_RECONSUMED_SUCCESS = 'reconsumed_success';

    /**
     * Process a single Kafka message for the given topic.
     */
    public function handle(string $topicName, array $payload, array $metadata = []): ?KafkaConsumeLog
    {
        $topicConfig = KafkaTopic::query()->where('topic', $topicName)
            ->where('is_active', true)
            ->first();

        if (! $topicConfig) {
            Log::warning("KafkaConsumer: no active config for topic [{$topicName}]");
            return null;
        }

        return $this->processFreshMessage($topicConfig, $payload, $metadata);
    }

    /**
     * Re-consume retryable failed logs for one topic.
     *
     * @return array{attempted:int,success:int,failed:int}
     */
    public function reconsumeFailedMessages(KafkaTopic $topic, int $limit = 50): array
    {
        $limit = min(max($limit, 1), 500);
        $attempted = 0;
        $success = 0;
        $failed = 0;

        $logs = $topic->logs()
            ->where('status', self::STATUS_FAILED)
            ->where('retryable', true)
            ->where(function ($query): void {
                $query->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->orderBy('consumed_at')
            ->limit($limit)
            ->get();

        foreach ($logs as $log) {
            $attempted++;
            $wasSuccess = $this->processReconsumeAttempt($topic, $log);

            if ($wasSuccess) {
                $success++;
            } else {
                $failed++;
            }
        }

        return [
            'attempted' => $attempted,
            'success' => $success,
            'failed' => $failed,
        ];
    }

    public function reconsumeLog(KafkaConsumeLog $failedLog): bool
    {
        $topic = $failedLog->topic;

        if (! $topic || ! $topic->is_active) {
            return false;
        }

        if ($failedLog->status !== self::STATUS_FAILED || ! $failedLog->retryable) {
            return false;
        }

        return $this->processReconsumeAttempt($topic, $failedLog);
    }

    private function processFreshMessage(KafkaTopic $topicConfig, array $payload, array $metadata = []): KafkaConsumeLog
    {
        $this->touchHeartbeat($topicConfig);

        try {
            $upsertValue = $this->processPayload($topicConfig, $payload);
            $this->markTopicSuccess($topicConfig, isReconsume: false);

            return KafkaConsumeLog::create([
                'kafka_topic_id' => $topicConfig->id,
                'status' => self::STATUS_SUCCESS,
                'attempt_count' => 1,
                'upsert_key_value' => (string) $upsertValue,
                'payload' => $payload,
                'consumed_at' => now(),
                'last_attempt_at' => now(),
                'retryable' => false,
                'kafka_partition' => $metadata['kafka_partition'] ?? null,
                'kafka_offset' => $metadata['kafka_offset'] ?? null,
                'kafka_key' => isset($metadata['kafka_key']) ? (string) $metadata['kafka_key'] : null,
            ]);
        } catch (Throwable $e) {
            $retryable = $this->isRetryable(1, $topicConfig);
            $nextRetryAt = $this->resolveNextRetryAt($topicConfig, 1, $retryable);

            $this->markTopicFailure($topicConfig, $e->getMessage());

            Log::error("KafkaConsumer: failed processing [{$topicConfig->topic}]: {$e->getMessage()}", [
                'payload' => $payload,
            ]);

            return KafkaConsumeLog::create([
                'kafka_topic_id' => $topicConfig->id,
                'status' => self::STATUS_FAILED,
                'attempt_count' => 1,
                'payload' => $payload,
                'error' => $e->getMessage(),
                'consumed_at' => now(),
                'last_attempt_at' => now(),
                'next_retry_at' => $nextRetryAt,
                'retryable' => $retryable,
                'is_reconsumed' => false,
                'kafka_partition' => $metadata['kafka_partition'] ?? null,
                'kafka_offset' => $metadata['kafka_offset'] ?? null,
                'kafka_key' => isset($metadata['kafka_key']) ? (string) $metadata['kafka_key'] : null,
            ]);
        }
    }

    /**
     * Re-run a failed log entry and update its retry state.
     */
    private function processReconsumeAttempt(KafkaTopic $topic, KafkaConsumeLog $failedLog): bool
    {
        $attempt = max(2, ((int) $failedLog->attempt_count) + 1);

        $failedLog->update([
            'attempt_count' => $attempt,
            'last_attempt_at' => now(),
            'is_reconsumed' => true,
        ]);

        $payload = is_array($failedLog->payload) ? $failedLog->payload : [];
        $this->touchHeartbeat($topic);

        try {
            $upsertValue = $this->processPayload($topic, $payload);

            $this->markTopicSuccess($topic, isReconsume: true);

            $failedLog->update([
                'status' => self::STATUS_RECONSUMED_SUCCESS,
                'upsert_key_value' => $upsertValue,
                'error' => null,
                'next_retry_at' => null,
                'retryable' => false,
                'resolved_at' => now(),
            ]);

            return true;
        } catch (Throwable $e) {
            $retryable = $this->isRetryable($attempt, $topic);
            $nextRetryAt = $this->resolveNextRetryAt($topic, $attempt, $retryable);

            $this->markTopicFailure($topic, $e->getMessage());

            $failedLog->update([
                'status' => self::STATUS_FAILED,
                'error' => $e->getMessage(),
                'next_retry_at' => $nextRetryAt,
                'retryable' => $retryable,
            ]);

            Log::warning("KafkaConsumer: re-consume failed [{$topic->topic}] log={$failedLog->id}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Run mapping + upsert + relation sync and return the resolved upsert key value.
     */
    private function processPayload(KafkaTopic $topicConfig, array $payload): string
    {
        $excluded = $topicConfig->exclude_keys ?? [];
        $data = collect($payload)->except($excluded)->all();

        $fieldMap = $topicConfig->resolvedFieldMap();
        $mapped = [];
        foreach ($fieldMap as $from => $to) {
            if (array_key_exists($from, $data)) {
                $mapped[$to] = $data[$from];
            }
        }

        $upsertKey = $topicConfig->upsert_key;
        $upsertValue = $mapped[$upsertKey]
            ?? $data[$upsertKey]
            ?? $data['uuid']
            ?? $data['id']
            ?? null;

        if (! isset($mapped[$upsertKey]) && isset($upsertValue)) {
            $mapped[$upsertKey] = $upsertValue;
        }

        $modelClass = $topicConfig->model_class;

        if (! class_exists($modelClass)) {
            throw new \RuntimeException("Model class [{$modelClass}] does not exist.");
        }

        if (empty($upsertValue)) {
            throw new \RuntimeException(
                "Upsert key [{$upsertKey}] not found in mapped payload or raw payload. " .
                "Check the field_map and upsert_key configuration for this topic."
            );
        }

        /** @var Model $record */
        $record = $modelClass::query()->updateOrCreate(
            [$upsertKey => $upsertValue],
            $mapped
        );

        $this->syncRelations($record, $topicConfig->relations ?? [], $payload);

        return (string) $upsertValue;
    }

    /**
     * Sync BelongsToMany relationships from nested arrays in the payload.
     */
    private function syncRelations(Model $record, array $relations, array $payload): void
    {
        foreach ($relations as $rel) {
            $payloadKey = $rel['payload_key'] ?? null;
            $relationship = $rel['relationship'] ?? null;
            $relatedClass = $rel['related_model'] ?? null;
            $lookupKey = $rel['related_lookup_key'] ?? null;
            $relatedModelKey = $rel['related_model_key'] ?? null;

            if (! $payloadKey || ! $relationship || ! $relatedClass) {
                Log::warning('KafkaConsumer: incomplete relation config — skipping.', ['rel' => $rel]);
                continue;
            }

            if (! isset($payload[$payloadKey]) || ! is_array($payload[$payloadKey])) {
                continue;
            }

            if (! method_exists($record, $relationship)) {
                Log::warning('KafkaConsumer: relationship [' . $relationship . '] not found on ' . get_class($record));
                continue;
            }

            /** @var Model $relatedInstance */
            $relatedInstance = new $relatedClass;
            $relatedPk = $relatedModelKey ?? $relatedInstance->getKeyName();

            $relatedIds = [];

            foreach ($payload[$payloadKey] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $lookupValue = null;

                if ($lookupKey && isset($item[$lookupKey])) {
                    $lookupValue = $item[$lookupKey];
                } else {
                    $lookupValue = $item['uuid'] ?? $item['id'] ?? null;
                }

                if (empty($lookupValue)) {
                    continue;
                }

                $relatedId = $relatedClass::query()->where($relatedPk, $lookupValue)->value($relatedPk);

                if ($relatedId !== null) {
                    $relatedIds[] = $relatedId;
                }
            }

            $record->{$relationship}()->sync($relatedIds);
        }
    }

    private function markTopicSuccess(KafkaTopic $topic, bool $isReconsume): void
    {
        $topic->increment('messages_consumed');

        if ($isReconsume) {
            $topic->increment('messages_reconsumed');
        }

        $topic->update([
            'last_consumed_at' => now(),
            'consumer_last_heartbeat_at' => now(),
            'consumer_last_error' => null,
            'consumer_lag_seconds' => 0,
        ]);
    }

    private function markTopicFailure(KafkaTopic $topic, string $error): void
    {
        $topic->increment('messages_failed');

        $lag = $topic->last_consumed_at?->diffInSeconds(now());
        $lag = $lag !== null ? (int) floor($lag) : null;

        $topic->update([
            'consumer_last_heartbeat_at' => now(),
            'consumer_last_error' => $error,
            'consumer_lag_seconds' => $lag,
        ]);
    }

    private function touchHeartbeat(KafkaTopic $topic): void
    {
        $lag = $topic->last_consumed_at?->diffInSeconds(now());
        $lag = $lag !== null ? (int) floor($lag) : null;

        $topic->update([
            'consumer_last_heartbeat_at' => now(),
            'consumer_lag_seconds' => $lag,
        ]);
    }

    private function isRetryable(int $attemptCount, KafkaTopic $topic): bool
    {
        $maxAttempts = max(1, (int) ($topic->max_reconsume_attempts ?? 3));

        return $attemptCount < $maxAttempts;
    }

    private function resolveNextRetryAt(KafkaTopic $topic, int $attemptCount, bool $retryable): ?\Illuminate\Support\Carbon
    {
        if (! $retryable) {
            return null;
        }

        $backoffSeconds = max(5, (int) ($topic->retry_backoff_seconds ?? 60));
        $delay = $backoffSeconds * max(1, $attemptCount - 1);

        return now()->addSeconds($delay);
    }
}
