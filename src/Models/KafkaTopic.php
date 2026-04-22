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

    protected $casts = [
        'field_map' => 'array',
        'exclude_keys' => 'array',
        'relations' => 'array',
        'is_active' => 'boolean',
        'last_consumed_at' => 'datetime',
        'consumer_last_heartbeat_at' => 'datetime',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(KafkaConsumeLog::class, 'kafka_topic_id');
    }

    public function resolvedFieldMap(): array
    {
        return collect($this->field_map ?? [])->pluck('to', 'from')->filter()->all();
    }

    public function pendingRetryCount(): int
    {
        return $this->logs()
            ->where('status', 'failed')
            ->where('retryable', true)
            ->where(fn ($q) => $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now()))
            ->count();
    }
}
