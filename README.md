# Kafka Consumer

Laravel package for Kafka topic consumption with:

- topic-to-model mapping
- upsert-based persistence
- consume logs
- failed-message retry / re-consume tracking
- consumer heartbeat and lag counters
- built-in laravel-kafka consumer engine (plug and play)

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

Run normal consumption:

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

## Custom Engine (Optional)

If you need a different Kafka transport implementation, override the default binding in your host app:

```php
$this->app->singleton(\Gurento\KafkaConsumer\Contracts\ConsumerEngine::class, YourEngine::class);
```

## In-App Service Usage

```php
app(\Gurento\KafkaConsumer\Services\KafkaConsumerService::class)
    ->handle($topicName, $payload, $metadata);
```

## License

MIT
