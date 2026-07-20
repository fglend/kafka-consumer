<?php

namespace Gurento\KafkaConsumer\Engines;

use Gurento\KafkaConsumer\Contracts\ProducerEngine;
use Junges\Kafka\Facades\Kafka;

class LaravelKafkaProducerEngine implements ProducerEngine
{
    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $headers
     */
    public function publish(string $topic, array $payload, ?string $key = null, array $headers = []): void
    {
        $producer = Kafka::publish()
            ->onTopic($topic)
            ->withBody($payload);

        if ($key !== null && $key !== '') {
            $producer = $producer->withKafkaKey($key);
        }

        if ($headers !== []) {
            $producer = $producer->withHeaders($headers);
        }

        $producer->send();
    }
}
