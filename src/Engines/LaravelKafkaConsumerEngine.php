<?php

namespace Gurento\KafkaConsumer\Engines;

use Gurento\KafkaConsumer\Contracts\ConsumerEngine;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Facades\Kafka;

class LaravelKafkaConsumerEngine implements ConsumerEngine
{
    /**
     * @param  callable(string, array, array):void  $handler
     * @param  array<string,mixed>  $options
     */
    public function consume(array $topics, callable $handler, array $options = []): void
    {
        $topics = array_values(array_filter($topics));

        if ($topics === []) {
            return;
        }

        $fromBeginning = (bool) ($options['from_beginning'] ?? false);
        $offsetReset = (string) ($options['offset_reset'] ?? ($fromBeginning ? 'earliest' : 'latest'));
        $limit = max(0, (int) ($options['limit'] ?? 0));
        $stopOnEmpty = (bool) ($options['stop_on_empty'] ?? false);
        $groupId = (string) ($options['group'] ?? config('kafka.consumer_group_id', 'kafka-consumer'));

        // Avoid polluting production offsets if explicitly replaying from earliest.
        if ($fromBeginning && empty($options['group'])) {
            $groupId = 'kafka-consumer-earliest-' . now()->timestamp;
        }

        $builder = Kafka::consumer($topics)
            ->withConsumerGroupId($groupId)
            ->withHandler(function (ConsumerMessage $message) use ($handler): void {
                $payload = $message->getBody();

                if (! is_array($payload)) {
                    $payload = json_decode((string) $payload, true) ?? [];
                }

                $metadata = [
                    'kafka_partition' => method_exists($message, 'getPartition') ? $message->getPartition() : null,
                    'kafka_offset' => method_exists($message, 'getOffset') ? $message->getOffset() : null,
                    'kafka_key' => method_exists($message, 'getKey') ? $message->getKey() : null,
                ];

                $handler($message->getTopicName(), $payload, $metadata);
            })
            ->withOptions([
                'auto.offset.reset' => $offsetReset,
            ]);

        if ($limit > 0) {
            $builder->withMaxMessages($limit);
        }

        if ($stopOnEmpty) {
            $builder->stopAfterLastMessage();
        }

        $builder->build()->consume();
    }
}
