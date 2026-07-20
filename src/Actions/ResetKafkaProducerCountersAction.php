<?php

namespace Gurento\KafkaConsumer\Actions;

use Gurento\KafkaConsumer\Models\KafkaProducerTopic;

class ResetKafkaProducerCountersAction
{
    public function execute(KafkaProducerTopic $topic): void
    {
        $topic->update([
            'messages_produced' => 0,
            'messages_send_failed' => 0,
            'messages_resent' => 0,
            'producer_last_error' => null,
        ]);
    }
}
