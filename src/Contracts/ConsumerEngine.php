<?php

namespace Gurento\KafkaConsumer\Contracts;

interface ConsumerEngine
{
    /**
     * Register callback for consumed messages.
     *
     * @param  callable(string, array, array):void  $handler
     * @param  array<string,mixed>  $options
     */
    public function consume(array $topics, callable $handler, array $options = []): void;
}
