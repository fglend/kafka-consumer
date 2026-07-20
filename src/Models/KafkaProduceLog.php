<?php

namespace Gurento\KafkaConsumer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KafkaProduceLog extends Model
{
    protected $table = 'kafka_produce_logs';

    public $timestamps = false;

    protected $fillable = [
        'kafka_producer_topic_id',
        'status',
        'attempt_count',
        'payload',
        'headers',
        'message_key',
        'error',
        'produced_at',
        'last_attempt_at',
        'next_retry_at',
        'resolved_at',
        'is_resent',
        'retryable',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'headers' => 'array',
            'produced_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'resolved_at' => 'datetime',
            'is_resent' => 'boolean',
            'retryable' => 'boolean',
            'attempt_count' => 'integer',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(KafkaProducerTopic::class, 'kafka_producer_topic_id');
    }
}
