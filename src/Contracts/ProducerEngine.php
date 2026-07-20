<?php

namespace Gurento\KafkaConsumer\Contracts;

interface ProducerEngine
{
    /**
     * Publish a single message to a Kafka topic.
     *
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $headers
     */
    public function publish(string $topic, array $payload, ?string $key = null, array $headers = []): void;
}
