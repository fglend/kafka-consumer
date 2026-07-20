<?php

namespace Gurento\KafkaConsumer\Services;

use Gurento\KafkaConsumer\Contracts\ProducerEngine;
use Gurento\KafkaConsumer\Events\KafkaMessageProduced;
use Gurento\KafkaConsumer\Events\KafkaMessageProduceFailed;
use Gurento\KafkaConsumer\Models\KafkaProduceLog;
use Gurento\KafkaConsumer\Models\KafkaProducerTopic;
use Illuminate\Support\Facades\Log;
use Throwable;

class KafkaProducerService
{
    private const STATUS_SENT = 'sent';

    private const STATUS_FAILED = 'failed';

    private const STATUS_RESENT_SUCCESS = 'resent_success';

    public function __construct(private ProducerEngine $engine)
    {
    }

    /**
     * Send a single message and record the attempt.
     */
    public function send(KafkaProducerTopic|string $topic, array $payload, ?string $key = null, array $headers = []): ?KafkaProduceLog
    {
        $topicConfig = $topic instanceof KafkaProducerTopic
            ? $topic
            : KafkaProducerTopic::query()->where('topic', $topic)->where('is_active', true)->first();

        if (! $topicConfig) {
            Log::warning('KafkaProducer: no active producer config for topic [' . (is_string($topic) ? $topic : $topic->topic) . ']');
            return null;
        }

        if (! $topicConfig->is_active) {
            Log::warning("KafkaProducer: topic [{$topicConfig->topic}] is inactive — message not sent.");
            return null;
        }

        $key = $key ?? $this->resolveKey($topicConfig, $payload);
        $headers = array_merge($topicConfig->resolvedDefaultHeaders(), $headers);

        try {
            $this->engine->publish($topicConfig->topic, $payload, $key, $headers);

            $this->markTopicSuccess($topicConfig, isResend: false);

            $log = KafkaProduceLog::create([
                'kafka_producer_topic_id' => $topicConfig->id,
                'status' => self::STATUS_SENT,
                'attempt_count' => 1,
                'payload' => $payload,
                'headers' => $headers ?: null,
                'message_key' => $key,
                'produced_at' => now(),
                'last_attempt_at' => now(),
                'retryable' => false,
            ]);

            KafkaMessageProduced::dispatch($topicConfig, $log);

            return $log;
        } catch (Throwable $e) {
            $retryable = $this->isRetryable(1, $topicConfig);
            $nextRetryAt = $this->resolveNextRetryAt($topicConfig, 1, $retryable);

            $this->markTopicFailure($topicConfig, $e->getMessage());

            Log::error("KafkaProducer: failed sending to [{$topicConfig->topic}]: {$e->getMessage()}", [
                'payload' => $payload,
            ]);

            $log = KafkaProduceLog::create([
                'kafka_producer_topic_id' => $topicConfig->id,
                'status' => self::STATUS_FAILED,
                'attempt_count' => 1,
                'payload' => $payload,
                'headers' => $headers ?: null,
                'message_key' => $key,
                'error' => $e->getMessage(),
                'produced_at' => now(),
                'last_attempt_at' => now(),
                'next_retry_at' => $nextRetryAt,
                'retryable' => $retryable,
                'is_resent' => false,
            ]);

            KafkaMessageProduceFailed::dispatch($topicConfig, $log, $e->getMessage(), $retryable);

            return $log;
        }
    }

    /**
     * Retry retryable failed produce logs for one topic.
     *
     * @return array{attempted:int,success:int,failed:int}
     */
    public function retryFailedMessages(KafkaProducerTopic $topic, int $limit = 50): array
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
            ->orderBy('produced_at')
            ->limit($limit)
            ->get();

        foreach ($logs as $log) {
            $attempted++;

            if ($this->processRetryAttempt($topic, $log)) {
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

    public function retryLog(KafkaProduceLog $failedLog): bool
    {
        $topic = $failedLog->topic;

        if (! $topic || ! $topic->is_active) {
            return false;
        }

        if ($failedLog->status !== self::STATUS_FAILED || ! $failedLog->retryable) {
            return false;
        }

        return $this->processRetryAttempt($topic, $failedLog);
    }

    private function processRetryAttempt(KafkaProducerTopic $topic, KafkaProduceLog $failedLog): bool
    {
        $attempt = max(2, ((int) $failedLog->attempt_count) + 1);

        $failedLog->update([
            'attempt_count' => $attempt,
            'last_attempt_at' => now(),
            'is_resent' => true,
        ]);

        $payload = is_array($failedLog->payload) ? $failedLog->payload : [];
        $headers = is_array($failedLog->headers) ? $failedLog->headers : [];

        try {
            $this->engine->publish($topic->topic, $payload, $failedLog->message_key, $headers);

            $this->markTopicSuccess($topic, isResend: true);

            $failedLog->update([
                'status' => self::STATUS_RESENT_SUCCESS,
                'error' => null,
                'next_retry_at' => null,
                'retryable' => false,
                'resolved_at' => now(),
            ]);

            KafkaMessageProduced::dispatch($topic, $failedLog, true);

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

            Log::warning("KafkaProducer: resend failed [{$topic->topic}] log={$failedLog->id}: {$e->getMessage()}");

            KafkaMessageProduceFailed::dispatch($topic, $failedLog, $e->getMessage(), $retryable);

            return false;
        }
    }

    private function resolveKey(KafkaProducerTopic $topic, array $payload): ?string
    {
        $keyField = $topic->key_field;

        if (! $keyField || ! isset($payload[$keyField])) {
            return null;
        }

        return (string) $payload[$keyField];
    }

    private function markTopicSuccess(KafkaProducerTopic $topic, bool $isResend): void
    {
        $topic->increment('messages_produced');

        if ($isResend) {
            $topic->increment('messages_resent');
        }

        $topic->update([
            'last_produced_at' => now(),
            'producer_last_error' => null,
        ]);
    }

    private function markTopicFailure(KafkaProducerTopic $topic, string $error): void
    {
        $topic->increment('messages_send_failed');

        $topic->update([
            'producer_last_error' => $error,
        ]);
    }

    private function isRetryable(int $attemptCount, KafkaProducerTopic $topic): bool
    {
        $maxAttempts = max(1, (int) ($topic->max_send_attempts
            ?? config('kafka-consumer.producer.max_send_attempts', 3)));

        return $attemptCount < $maxAttempts;
    }

    private function resolveNextRetryAt(KafkaProducerTopic $topic, int $attemptCount, bool $retryable): ?\Illuminate\Support\Carbon
    {
        if (! $retryable) {
            return null;
        }

        $backoffSeconds = max(5, (int) ($topic->send_backoff_seconds
            ?? config('kafka-consumer.producer.send_backoff_seconds', 60)));

        return now()->addSeconds($backoffSeconds * max(1, $attemptCount - 1));
    }
}
