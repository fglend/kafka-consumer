<?php

namespace Gurento\KafkaConsumer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KafkaConsumeLog extends Model
{
    protected $table = 'kafka_consume_logs';

    public $timestamps = false;

    protected $fillable = [
        'kafka_topic_id',
        'status',
        'attempt_count',
        'upsert_key_value',
        'payload',
        'error',
        'consumed_at',
        'last_attempt_at',
        'next_retry_at',
        'resolved_at',
        'is_reconsumed',
        'retryable',
        'origin_log_id',
        'kafka_partition',
        'kafka_offset',
        'kafka_key',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'consumed_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'resolved_at' => 'datetime',
            'is_reconsumed' => 'boolean',
            'retryable' => 'boolean',
            'attempt_count' => 'integer',
            'kafka_partition' => 'integer',
            'kafka_offset' => 'integer',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(KafkaTopic::class, 'kafka_topic_id');
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(self::class, 'origin_log_id');
    }

    public function retries(): HasMany
    {
        return $this->hasMany(self::class, 'origin_log_id');
    }
}
