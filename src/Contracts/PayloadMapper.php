<?php

namespace Gurento\KafkaConsumer\Contracts;

interface PayloadMapper
{
    public function map(array $payload, array $fieldMap): array;
}
