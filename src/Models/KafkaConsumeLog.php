<?php

namespace Gurento\KafkaConsumer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'kafka_partition',
        'kafka_offset',
        'kafka_key',
    ];

    protected $casts = [
        'payload' => 'array',
        'consumed_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'resolved_at' => 'datetime',
        'is_reconsumed' => 'boolean',
        'retryable' => 'boolean',
    ];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(KafkaTopic::class, 'kafka_topic_id');
    }
}
