# Kafka Consumer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/gurento/kafka-consumer.svg?style=flat-square)](https://packagist.org/packages/gurento/kafka-consumer)
[![Total Downloads](https://img.shields.io/packagist/dt/gurento/kafka-consumer.svg?style=flat-square)](https://packagist.org/packages/gurento/kafka-consumer)
[![License](https://img.shields.io/github/license/gurento/kafka-consumer?style=flat-square)](LICENSE)

A Laravel package for consuming Kafka messages and writing them into Eloquent models using **declarative, database-driven topic mappings** — no per-topic consumer classes required.

Built for operations teams that need:

- declarative topic-to-model mapping
- safe `updateOrCreate` upserts
- failed-message tracking and re-consume flows
- operational counters, health, and heartbeat metadata
- command-driven workflow for normal consume and replay

Ships with a default engine based on [`mateusjunges/laravel-kafka`](https://github.com/mateusjunges/laravel-kafka), so it works out of the box.

> **Admin UI available:** install [`gurento/kafka-consumer-filament`](https://packagist.org/packages/gurento/kafka-consumer-filament) for a full Filament panel — topic CRUD, live health monitoring, and one-click replay.

## Features

- Topic configuration in DB (`kafka_topics`) — no code deploys to add a topic
- Per-message logs in DB (`kafka_consume_logs`) with partition/offset/key metadata
- Payload-to-model field mapping with key exclusion
- Configurable upsert key per topic
- `BelongsToMany` relation syncing from nested payload arrays
- Automatic retry scheduling with per-topic backoff and attempt limits
- Re-consume command mode for failed messages
- Health status (`healthy` / `degraded` / `stalled` / `inactive`) from heartbeat + error state
- `KafkaMessageConsumed` / `KafkaMessageFailed` events for alerting and metrics

## Requirements

| Dependency | Version |
|---|---|
| PHP | 8.2+ |
| Laravel | 11 / 12 |
| ext-rdkafka | configured for your PHP runtime |
| Kafka broker | reachable from the app |

## Installation

```bash
composer require gurento/kafka-consumer
php artisan vendor:publish --tag=kafka-consumer-config
php artisan vendor:publish --tag=kafka-consumer-migrations
php artisan migrate
```

This creates two tables:

- `kafka_topics` — topic config, counters, and health metadata
- `kafka_consume_logs` — per-message processing logs

### Environment

Add your Kafka connection settings to `.env`:

```dotenv
KAFKA_BROKERS=localhost:9092
KAFKA_CONSUMER_GROUP_ID=app-consumer
KAFKA_DEBUG=false
```

- `KAFKA_BROKERS` — comma-separated broker list (host:port)
- `KAFKA_CONSUMER_GROUP_ID` — default consumer group used when `--group` is not passed
- `KAFKA_DEBUG` — set to `true` to enable verbose librdkafka debug output while troubleshooting

## Quick Start

1. Create a topic configuration (via seeder, tinker, or the Filament UI).
2. Start the consumer:

   ```bash
   php artisan gurento:kafka-consume
   ```

3. Inspect results in `kafka_topics.messages_consumed` and `kafka_consume_logs`.

## Topic Configuration

Each `kafka_topics` row defines how a Kafka topic maps into your model.

**Core columns:**

| Column | Purpose |
|---|---|
| `topic` | Kafka topic name |
| `model_class` | Fully-qualified model class (e.g. `App\Models\Office`) |
| `upsert_key` | Unique model column used by `updateOrCreate` |
| `field_map` | JSON array of `{from, to}` mapping pairs |
| `exclude_keys` | Optional payload keys to strip before mapping |
| `relations` | Optional `BelongsToMany` sync definitions |
| `is_active` | Whether the topic is consumed |

**Retry/health overrides** (fall back to config when `NULL`): `max_reconsume_attempts`, `retry_backoff_seconds`, `health_stale_after_seconds`.

**Operational columns** (auto-managed): `messages_consumed`, `messages_failed`, `messages_reconsumed`, `last_consumed_at`, `consumer_last_heartbeat_at`, `consumer_last_error`, `consumer_lag_seconds`.

### Field Mapping Example

Given this Kafka payload:

```json
{
  "uuid": "off-001",
  "name": "Accounting Office",
  "meta": {"source": "hr"}
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

| Option | Description |
|---|---|
| `--topics=*` | Consume only selected topics |
| `--limit=` | Process only N messages, then stop |
| `--group=` | Consumer group id (defaults to `kafka-consumer.default_group` config) |
| `--from-beginning` | Read from earliest offsets (replay mode) |
| `--stop-on-empty` | Stop after the last available message instead of polling forever |

Examples:

```bash
php artisan gurento:kafka-consume --topics=HR_APP.LIVE.office
php artisan gurento:kafka-consume --topics=HR_APP.LIVE.office --limit=500
php artisan gurento:kafka-consume --from-beginning --stop-on-empty
```

### Re-consume Failed Logs

Instead of polling Kafka, reprocess failed entries from `kafka_consume_logs`:

```bash
php artisan gurento:kafka-consume --reconsume-failed --reconsume-limit=100
```

Useful after fixing a wrong `field_map`, a wrong `model_class`, or a temporary DB/infrastructure failure.

## How Replay Works

`--from-beginning` sets offset reset to `earliest` and uses a unique consumer group (unless `--group` is provided) to avoid corrupting your normal consumer offsets.

Recommended pattern:

1. Run the normal consumer with a stable group for real-time operations.
2. Use `--from-beginning` only for backfills/replays.

## Failure & Retry Behavior

On processing errors, the package logs `status=failed`, the `error` message, and retry metadata (`attempt_count`, `next_retry_at`, `retryable`). Retries back off linearly per attempt using the topic's `retry_backoff_seconds` and stop after `max_reconsume_attempts`. Re-consume mode marks replayed entries `reconsumed_success` on success.

## Configuration

Values in `config/kafka-consumer.php` act as fallbacks when a topic row leaves the matching column `NULL`:

| Key | Default | Purpose |
|---|---|---|
| `default_group` | `app-consumer` | Consumer group id when `--group` is not passed |
| `max_reconsume_attempts` | `3` | Retry attempts before a failure becomes permanent |
| `retry_backoff_seconds` | `60` | Base backoff between retries |
| `health_stale_after_seconds` | `300` | Heartbeat age before a topic is considered stalled |

## Events

The service dispatches events on every processing outcome — hook them for alerting or metrics:

| Event | When | Payload |
|---|---|---|
| `Gurento\KafkaConsumer\Events\KafkaMessageConsumed` | Message (or re-consume) succeeds | `$topic`, `$log`, `$isReconsume` |
| `Gurento\KafkaConsumer\Events\KafkaMessageFailed` | Processing fails | `$topic`, `$log`, `$error`, `$willRetry` |

```php
use Gurento\KafkaConsumer\Events\KafkaMessageFailed;

Event::listen(KafkaMessageFailed::class, function (KafkaMessageFailed $event): void {
    if (! $event->willRetry) {
        // permanent failure — alert your on-call channel
    }
});
```

## Custom Engine (Optional)

By default the package binds `Gurento\KafkaConsumer\Contracts\ConsumerEngine` to `LaravelKafkaConsumerEngine`. To use a different transport, override the binding in your host app:

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

- Run the consumer as a daemon (Supervisor/systemd)
- Monitor `messages_failed` and `consumer_last_error` (or listen to `KafkaMessageFailed`)
- Use separate groups for replay/backfill jobs
- Keep `field_map` explicit and reviewed
- Keep topic configs in source-controlled seeders where possible

## Troubleshooting

**`No active topics configured`** — ensure `kafka_topics` has rows with `is_active = true`.

**Command not behaving as expected** — run `php artisan optimize:clear && composer dump-autoload`.

**Kafka connection/auth issues** — verify the host app's `config/kafka.php` and environment values (`KAFKA_BROKERS`, auth settings).

**Replay not processing failed logs** — check that rows have `status = failed`, `retryable = true`, and `next_retry_at <= now()` (or `NULL`).

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release history.

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.
