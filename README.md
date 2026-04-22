# Kafka Consumer

Laravel package for Kafka topic consumption with:

- topic-to-model mapping
- upsert-based persistence
- consume logs
- failed-message retry / re-consume tracking
- consumer heartbeat and lag counters

## Installation

```bash
composer require gurento/kafka-consumer
php artisan vendor:publish --tag=kafka-consumer-config
php artisan vendor:publish --tag=kafka-consumer-migrations
php artisan migrate
```

## Configure Topics

Insert rows into `kafka_topics` with:

- `topic`
- `model_class`
- `upsert_key`
- `field_map` (JSON array of `{from,to}`)

## Consume

Bind `Gurento\KafkaConsumer\Contracts\ConsumerEngine` in your host app, then run:

```bash
php artisan kafka:consume
```

Consume from earliest offsets:

```bash
php artisan kafka:consume --from-beginning
```

Retry failed logs:

```bash
php artisan kafka:consume --reconsume-failed --reconsume-limit=100
```

## In-App Service Usage

```php
app(\Gurento\KafkaConsumer\Services\KafkaConsumerService::class)
    ->handle($topicName, $payload, $metadata);
```

## License

MIT
