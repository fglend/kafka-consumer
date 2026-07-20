<?php

namespace Gurento\KafkaConsumer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KafkaProducerTopic extends Model
{
    protected $table = 'kafka_producer_topics';

    protected $fillable = [
        'topic',
        'model_class',
        'key_field',
        'default_headers',
        'payload_schema',
        'payload_template',
        'relations',
        'description',
        'is_active',
        'messages_produced',
        'messages_send_failed',
        'messages_resent',
        'last_produced_at',
        'producer_last_error',
        'max_send_attempts',
        'send_backoff_seconds',
    ];

    protected function casts(): array
    {
        return [
            'default_headers' => 'array',
            'payload_schema' => 'array',
            'relations' => 'array',
            'is_active' => 'boolean',
            'last_produced_at' => 'datetime',
            'messages_produced' => 'integer',
            'messages_send_failed' => 'integer',
            'messages_resent' => 'integer',
            'max_send_attempts' => 'integer',
            'send_backoff_seconds' => 'integer',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(KafkaProduceLog::class, 'kafka_producer_topic_id');
    }

    /**
     * Resolve default_headers rows into a simple name => value lookup array.
     */
    public function resolvedDefaultHeaders(): array
    {
        return collect($this->default_headers ?? [])
            ->pluck('value', 'name')
            ->filter(fn ($value, $key) => filled($key))
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

        if (filled($this->producer_last_error)) {
            return 'degraded';
        }

        if (! $this->last_produced_at) {
            return 'unknown';
        }

        return 'healthy';
    }

    public function getFailureRateAttribute(): float
    {
        $success = (int) $this->messages_produced;
        $failed = (int) $this->messages_send_failed;
        $total = $success + $failed;

        if ($total === 0) {
            return 0.0;
        }

        return round(($failed / $total) * 100, 2);
    }
}
