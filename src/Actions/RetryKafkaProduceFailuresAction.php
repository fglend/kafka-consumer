<?php

namespace Gurento\KafkaConsumer\Actions;

use Gurento\KafkaConsumer\Models\KafkaProducerTopic;
use Gurento\KafkaConsumer\Services\KafkaProducerService;

class RetryKafkaProduceFailuresAction
{
    public function execute(KafkaProducerTopic $topic, int $limit = 50): array
    {
        return app(KafkaProducerService::class)->retryFailedMessages($topic, $limit);
    }
}
