<?php

namespace Gurento\KafkaConsumer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KafkaTopic extends Model
{
    protected $table = 'kafka_topics';

    protected $fillable = [
        'topic',
        'model_class',
        'upsert_key',
        'field_map',
        'exclude_keys',
        'relations',
        'description',
        'is_active',
        'messages_consumed',
        'messages_failed',
        'messages_reconsumed',
        'last_consumed_at',
        'consumer_last_heartbeat_at',
        'consumer_last_error',
        'consumer_lag_seconds',
        'max_reconsume_attempts',
        'retry_backoff_seconds',
        'health_stale_after_seconds',
    ];

    protected function casts(): array
    {
        return [
            'field_map' => 'array',
            'exclude_keys' => 'array',
            'relations' => 'array',
            'is_active' => 'boolean',
            'last_consumed_at' => 'datetime',
            'consumer_last_heartbeat_at' => 'datetime',
            'messages_consumed' => 'integer',
            'messages_failed' => 'integer',
            'messages_reconsumed' => 'integer',
            'consumer_lag_seconds' => 'integer',
            'max_reconsume_attempts' => 'integer',
            'retry_backoff_seconds' => 'integer',
            'health_stale_after_seconds' => 'integer',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(KafkaConsumeLog::class, 'kafka_topic_id');
    }

    /**
     * Resolve field_map rows into a simple from => to lookup array.
     */
    public function resolvedFieldMap(): array
    {
        return collect($this->field_map ?? [])
            ->pluck('to', 'from')
            ->filter()
            ->all();
    }

    public function pendingRetryCount(): int
    {
        return $this->logs()
            ->where('status', 'failed')
            ->where('retryable', true)
            ->where(function ($query): void {
                $query->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->count();
    }

    public function getHealthStatusAttribute(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        if (! $this->consumer_last_heartbeat_at) {
            return 'unknown';
        }

        $staleAfterSeconds = max(30, (int) ($this->health_stale_after_seconds ?? 300));
        $isStale = $this->consumer_last_heartbeat_at->lt(now()->subSeconds($staleAfterSeconds));

        if ($isStale) {
            return 'stalled';
        }

        if (filled($this->consumer_last_error)) {
            return 'degraded';
        }

        return 'healthy';
    }

    public function getFailureRateAttribute(): float
    {
        $success = (int) $this->messages_consumed;
        $failed = (int) $this->messages_failed;
        $total = $success + $failed;

        if ($total === 0) {
            return 0.0;
        }

        return round(($failed / $total) * 100, 2);
    }
}
