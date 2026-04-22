<?php

namespace Gurento\KafkaConsumer\Actions;

use Gurento\KafkaConsumer\Models\KafkaTopic;
use Gurento\KafkaConsumer\Services\KafkaConsumerService;

class ReconsumeKafkaTopicFailuresAction
{
    public function execute(KafkaTopic $topic, int $limit = 50): array
    {
        return app(KafkaConsumerService::class)->reconsumeFailedMessages($topic, $limit);
    }
}
