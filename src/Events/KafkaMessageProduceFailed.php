<?php

namespace Gurento\KafkaConsumer\Events;

use Gurento\KafkaConsumer\Models\KafkaProduceLog;
use Gurento\KafkaConsumer\Models\KafkaProducerTopic;
use Illuminate\Foundation\Events\Dispatchable;

class KafkaMessageProduceFailed
{
    use Dispatchable;

    public function __construct(
        public KafkaProducerTopic $topic,
        public KafkaProduceLog $log,
        public string $error,
        public bool $willRetry = false,
    ) {
    }
}
