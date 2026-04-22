<?php

namespace Gurento\KafkaConsumer\Actions;

use Gurento\KafkaConsumer\Models\KafkaTopic;

class MarkKafkaTopicHealthyAction
{
    public function execute(KafkaTopic $topic): void
    {
        $topic->update([
            'consumer_last_error' => null,
            'consumer_last_heartbeat_at' => now(),
            'consumer_lag_seconds' => 0,
        ]);
    }
}
