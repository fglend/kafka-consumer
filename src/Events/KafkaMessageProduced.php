<?php

namespace Gurento\KafkaConsumer\Events;

use Gurento\KafkaConsumer\Models\KafkaProduceLog;
use Gurento\KafkaConsumer\Models\KafkaProducerTopic;
use Illuminate\Foundation\Events\Dispatchable;

class KafkaMessageProduced
{
    use Dispatchable;

    public function __construct(
        public KafkaProducerTopic $topic,
        public KafkaProduceLog $log,
        public bool $isResend = false,
    ) {
    }
}
