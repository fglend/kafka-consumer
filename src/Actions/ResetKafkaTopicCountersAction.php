<?php

namespace Gurento\KafkaConsumer\Actions;

use Gurento\KafkaConsumer\Models\KafkaTopic;

class ResetKafkaTopicCountersAction
{
    public function execute(KafkaTopic $topic): void
    {
        $topic->update([
            'messages_consumed' => 0,
            'messages_failed' => 0,
            'messages_reconsumed' => 0,
            'consumer_lag_seconds' => null,
            'consumer_last_error' => null,
        ]);
    }
}
