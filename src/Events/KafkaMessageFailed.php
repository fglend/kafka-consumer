<?php

namespace Gurento\KafkaConsumer\Events;

use Gurento\KafkaConsumer\Models\KafkaConsumeLog;
use Gurento\KafkaConsumer\Models\KafkaTopic;
use Illuminate\Foundation\Events\Dispatchable;

class KafkaMessageFailed
{
    use Dispatchable;

    public function __construct(
        public KafkaTopic $topic,
        public KafkaConsumeLog $log,
        public string $error,
        public bool $willRetry = false,
    ) {
    }
}
