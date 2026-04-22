# Kafka Consumer

`gurento/kafka-consumer` is a Laravel package for consuming Kafka messages and writing them into Eloquent models using configurable topic mappings.

It is built for operations teams that need:

- declarative topic-to-model mapping
- safe `updateOrCreate` upserts
- failed-message tracking and re-consume flows
- operational counters and heartbeat metadata
- command-driven workflow for normal consume and replay

The package ships with a default engine based on `mateusjunges/laravel-kafka`, so it works out of the box.

## Features

- Topic configuration in DB (`kafka_topics`)
- Message logs in DB (`kafka_consume_logs`)
- Payload key exclusion
- Payload-to-model field mapping
- Configurable upsert key
- Failed log retry scheduling metadata
- Re-consume command mode for failed messages
- Consume metadata tracking (partition, offset, key)

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- Kafka broker access
- `rdkafka` extension configured for your PHP runtime

## Installation

```bash
composer require gurento/kafka-consumer
php artisan vendor:publish --tag=kafka-consumer-config
php artisan vendor:publish --tag=kafka-consumer-migrations
php artisan migrate
```

## What Gets Installed

After migration:

- `kafka_topics` table: topic config + counters + health metadata
- `kafka_consume_logs` table: per-message processing logs

## Quick Start

1. Create a topic configuration.
2. Start consumer:

```bash
php artisan gurento:kafka-consume
```

3. Inspect results in:
- `kafka_topics.messages_consumed`
- `kafka_consume_logs`

## Topic Configuration Model

Each `kafka_topics` row defines how a Kafka topic maps into your model.

Core columns:

- `topic`: Kafka topic name
- `model_class`: Fully-qualified model class (example: `App\\Models\\Office`)
- `upsert_key`: Unique model column used by `updateOrCreate`
- `field_map`: JSON array of mapping pairs (`from` payload field, `to` model field)
- `exclude_keys`: Optional payload keys to ignore
- `is_active`: whether topic is consumed

Operational columns (auto-managed):

- `messages_consumed`, `messages_failed`, `messages_reconsumed`
- `last_consumed_at`, `consumer_last_heartbeat_at`
- `consumer_last_error`, `consumer_lag_seconds`

## Field Mapping Example

If Kafka payload is:

```json
{
  "uuid": "off-001",
  "name": "Accounting Office",
  "meta": {"source":"hr"}
}
```

Use `field_map`:

```json
[
  {"from": "uuid", "to": "id"},
  {"from": "name", "to": "name"}
]
```

And `exclude_keys`:

```json
["meta"]
```

The package upserts by `upsert_key` (for example `id`).

## Command Usage

### Normal Consumption

```bash
php artisan gurento:kafka-consume
```

Options:

- `--topics=*` consume only selected topics
- `--limit=` process only N messages then stop
- `--from-beginning` read from earliest offsets (replay mode)

Examples:

```bash
php artisan gurento:kafka-consume --topics=HR_APP.LIVE.office
php artisan gurento:kafka-consume --topics=HR_APP.LIVE.office --limit=500
php artisan gurento:kafka-consume --from-beginning
```

### Re-consume Failed Logs

Instead of polling Kafka, reprocess failed entries from `kafka_consume_logs`:

```bash
php artisan gurento:kafka-consume --reconsume-failed --reconsume-limit=100
```

Useful after fixing:

- wrong `field_map`
- wrong `model_class`
- temporary DB/infrastructure failures

## How Replay Works

`--from-beginning` sets offset reset to `earliest` and uses a unique consumer group (unless provided) to avoid corrupting your normal consumer offsets.

Recommended pattern:

1. Run normal consumer with stable group for real-time operations.
2. Use `--from-beginning` only for backfills/replays.

## Failure and Retry Behavior

On processing errors, package logs:

- `status=failed`
- `error`
- retry metadata (`attempt_count`, `next_retry_at`, `retryable`)

Re-consume mode updates status to `reconsumed_success` when replay succeeds.

## Plug-and-Play Engine

By default, package auto-binds:

- `Gurento\\KafkaConsumer\\Contracts\\ConsumerEngine`
- to `Gurento\\KafkaConsumer\\Engines\\LaravelKafkaConsumerEngine`

No host-app binding is required for standard usage.

## Custom Engine (Optional)

If you need a different transport implementation, override binding in host app:

```php
$this->app->singleton(
    \Gurento\KafkaConsumer\Contracts\ConsumerEngine::class,
    YourCustomEngine::class,
);
```

Your engine must implement:

```php
public function consume(array $topics, callable $handler, array $options = []): void;
```

## Programmatic Consumption Hook

If you already consume Kafka elsewhere, call the service directly:

```php
app(\Gurento\KafkaConsumer\Services\KafkaConsumerService::class)
    ->handle($topicName, $payload, $metadata);
```

## Production Recommendations

- Run consumer as a daemon (Supervisor/systemd)
- Monitor `messages_failed` and `consumer_last_error`
- Use separate groups for replay/backfill jobs
- Keep `field_map` explicit and reviewed
- Keep topic configs in source-controlled seeders where possible

## Troubleshooting

### `No active topics configured`

Make sure `kafka_topics` has rows with `is_active = true`.

### Command exists but not working as expected

Run:

```bash
php artisan optimize:clear
composer dump-autoload
```

### Kafka connection/auth issues

Verify host app `config/kafka.php` and environment values (`KAFKA_BROKERS`, auth settings).

### Replay not processing any failed logs

Check failed log rows:

- `status = failed`
- `retryable = true`
- `next_retry_at <= now()` (or `NULL`)

## Filament UI Integration

Install companion package for admin UI:

- `gurento/kafka-consumer-filament`

See its README for plugin registration and panel setup.

## License

MIT
