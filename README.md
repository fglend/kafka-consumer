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

> **Admin UI available:** install [`gurento/kafka-consumer-filament`](https://packagist.org/packages/gurento/kafka-consumer-filament) for consumer topic CRUD, live health monitoring, and one-click replay — and/or [`gurento/kafka-producer-filament`](https://packagist.org/packages/gurento/kafka-producer-filament) for producer topic CRUD and a Send Message composer. Both are independent Filament panels sharing this package as their backend; install either or both.

## Features

**Consumer**

- Topic configuration in DB (`kafka_topics`) — no code deploys to add a topic
- Per-message logs in DB (`kafka_consume_logs`) with partition/offset/key metadata
- Payload-to-model field mapping with key exclusion
- Configurable upsert key per topic
- `BelongsToMany` relation syncing from nested payload arrays
- Automatic retry scheduling with per-topic backoff and attempt limits
- Re-consume command mode for failed messages
- Health status (`healthy` / `degraded` / `stalled` / `inactive`) from heartbeat + error state
- `KafkaMessageConsumed` / `KafkaMessageFailed` events for alerting and metrics

**Producer**

- Producer topic configuration in DB (`kafka_producer_topics`) — key field, default headers, optional payload schema
- Per-message send logs in DB (`kafka_produce_logs`)
- Send ad-hoc messages programmatically, via command, or from the Filament UI
- Automatic retry scheduling with per-topic backoff and attempt limits (mirrors the consumer side)
- `KafkaMessageProduced` / `KafkaMessageProduceFailed` events for alerting and metrics

## Requirements

| Dependency | Version |
|---|---|
| PHP | 8.2+ |
| Laravel | 11 / 12 / 13 |
| ext-rdkafka | configured for your PHP runtime |
| Kafka broker | reachable from the app |

## Installation

```bash
composer require gurento/kafka-consumer
php artisan vendor:publish --tag=kafka-consumer-config
php artisan vendor:publish --tag=kafka-consumer-migrations
php artisan migrate
```

This creates four tables:

- `kafka_topics` — consumer topic config, counters, and health metadata
- `kafka_consume_logs` — per-message consume processing logs
- `kafka_producer_topics` — producer topic config, counters, and health metadata
- `kafka_produce_logs` — per-message send logs

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

## Failure & Retry Behavior (Consumer)

On processing errors, the package logs `status=failed`, the `error` message, and retry metadata (`attempt_count`, `next_retry_at`, `retryable`). Retries back off linearly per attempt using the topic's `retry_backoff_seconds` and stop after `max_reconsume_attempts`. Re-consume mode marks replayed entries `reconsumed_success` on success.

## Producing Messages

The producer side is a mirror of the consumer: declarative topic config, per-message logs, and automatic retry — just in the opposite direction.

### Producer Topic Configuration

Each `kafka_producer_topics` row defines how a topic accepts outgoing messages.

| Column | Purpose |
|---|---|
| `topic` | Kafka topic name to publish to |
| `model_class` | Optional Eloquent model class — used only by `gurento/kafka-producer-filament`'s UI to auto-derive `payload_schema`/`relations`; has no effect on `send()` |
| `key_field` | Payload field used as the Kafka message key when no explicit key is given |
| `default_headers` | JSON array of `{name, value}` pairs merged into every message |
| `payload_schema` | Optional JSON array of `{name, type, required}` field definitions (drives the Filament send composer) |
| `payload_template` | Optional free-form raw JSON text — when set, takes priority over `payload_schema`/`relations` as the Filament send composer's default payload |
| `relations` | Optional JSON array of `{relationship, type, related_model}` — informational only, surfaced in the Filament UI |
| `is_active` | Whether the topic accepts sends |

**Retry overrides** (fall back to `config('kafka-consumer.producer.*')` when `NULL`): `max_send_attempts`, `send_backoff_seconds`.

**Operational columns** (auto-managed): `messages_produced`, `messages_send_failed`, `messages_resent`, `last_produced_at`, `producer_last_error`.

### Sending a Message

Programmatically:

```php
app(\Gurento\KafkaConsumer\Services\KafkaProducerService::class)
    ->send('APP.LIVE.orders', ['uuid' => 'ord-001', 'status' => 'paid']);
```

Via command:

```bash
php artisan gurento:kafka-produce --topic=APP.LIVE.orders --payload='{"uuid":"ord-001","status":"paid"}'
```

| Option | Description |
|---|---|
| `--topic=` | Producer topic name to send to |
| `--payload=` | Message payload as a JSON string |
| `--key=` | Optional message key (overrides the topic's `key_field` resolution) |
| `--retry-failed` | Retry failed produce logs instead of sending a new message |
| `--retry-limit=` | Max failed logs per topic to retry (default 50) |

Retry all topics' failed sends:

```bash
php artisan gurento:kafka-produce --retry-failed --retry-limit=100
```

### Failure & Retry Behavior (Producer)

On send errors, the package logs `status=failed`, the `error` message, and retry metadata (`attempt_count`, `next_retry_at`, `retryable`) — backing off linearly per attempt using `send_backoff_seconds` and stopping after `max_send_attempts`. Retried entries are marked `resent_success` on success.

> **At-least-once semantics:** if the broker acknowledgment is lost after actually receiving the message, a retry can produce a duplicate. Design consumers to be idempotent (e.g. upsert by a stable key) when replaying producer retries.

## Configuration

Values in `config/kafka-consumer.php` act as fallbacks when a topic row leaves the matching column `NULL`:

| Key | Default | Purpose |
|---|---|---|
| `default_group` | `app-consumer` | Consumer group id when `--group` is not passed |
| `max_reconsume_attempts` | `3` | Consumer retry attempts before a failure becomes permanent |
| `retry_backoff_seconds` | `60` | Consumer base backoff between retries |
| `health_stale_after_seconds` | `300` | Heartbeat age before a topic is considered stalled |
| `producer.max_send_attempts` | `3` | Producer retry attempts before a send failure becomes permanent |
| `producer.send_backoff_seconds` | `60` | Producer base backoff between retries |
| `producer.health_stale_after_seconds` | `300` | Reserved for future producer health checks |

## Events

The services dispatch events on every processing outcome — hook them for alerting or metrics:

| Event | When | Payload |
|---|---|---|
| `Gurento\KafkaConsumer\Events\KafkaMessageConsumed` | Message (or re-consume) succeeds | `$topic`, `$log`, `$isReconsume` |
| `Gurento\KafkaConsumer\Events\KafkaMessageFailed` | Processing fails | `$topic`, `$log`, `$error`, `$willRetry` |
| `Gurento\KafkaConsumer\Events\KafkaMessageProduced` | Message (or resend) sends successfully | `$topic`, `$log`, `$isResend` |
| `Gurento\KafkaConsumer\Events\KafkaMessageProduceFailed` | Send fails | `$topic`, `$log`, `$error`, `$willRetry` |

```php
use Gurento\KafkaConsumer\Events\KafkaMessageFailed;
use Gurento\KafkaConsumer\Events\KafkaMessageProduceFailed;

Event::listen(KafkaMessageFailed::class, function (KafkaMessageFailed $event): void {
    if (! $event->willRetry) {
        // permanent consume failure — alert your on-call channel
    }
});

Event::listen(KafkaMessageProduceFailed::class, function (KafkaMessageProduceFailed $event): void {
    if (! $event->willRetry) {
        // permanent send failure — alert your on-call channel
    }
});
```

## Custom Engine (Optional)

By default the package binds:

- `Gurento\KafkaConsumer\Contracts\ConsumerEngine` → `LaravelKafkaConsumerEngine`
- `Gurento\KafkaConsumer\Contracts\ProducerEngine` → `LaravelKafkaProducerEngine`

To use a different transport, override either binding in your host app:

```php
$this->app->singleton(
    \Gurento\KafkaConsumer\Contracts\ConsumerEngine::class,
    YourCustomConsumerEngine::class,
);

$this->app->singleton(
    \Gurento\KafkaConsumer\Contracts\ProducerEngine::class,
    YourCustomProducerEngine::class,
);
```

Your engines must implement:

```php
public function consume(array $topics, callable $handler, array $options = []): void;

public function publish(string $topic, array $payload, ?string $key = null, array $headers = []): void;
```

## Programmatic Hooks

If you already consume or produce Kafka messages elsewhere, call the services directly:

```php
app(\Gurento\KafkaConsumer\Services\KafkaConsumerService::class)
    ->handle($topicName, $payload, $metadata);

app(\Gurento\KafkaConsumer\Services\KafkaProducerService::class)
    ->send($topicName, $payload, $key);
```

## Production Recommendations

- Run the consumer as a daemon (Supervisor/systemd)
- Monitor `messages_failed` / `messages_send_failed` and `*_last_error` (or listen to the `*Failed` events)
- Use separate groups for replay/backfill jobs
- Keep `field_map` explicit and reviewed
- Keep topic configs in source-controlled seeders where possible
- Schedule `gurento:kafka-produce --retry-failed` (e.g. every few minutes) if you don't retry sends immediately

## Troubleshooting

**`No active topics configured`** — ensure `kafka_topics` has rows with `is_active = true`.

**Command not behaving as expected** — run `php artisan optimize:clear && composer dump-autoload`.

**Kafka connection/auth issues** — verify the host app's `config/kafka.php` and environment values (`KAFKA_BROKERS`, auth settings).

**Replay not processing failed logs** — check that rows have `status = failed`, `retryable = true`, and `next_retry_at <= now()` (or `NULL`). Applies to both `kafka_consume_logs` and `kafka_produce_logs`.

**Message not sent, no error shown** — confirm the producer topic row has `is_active = true`; inactive topics are silently skipped (logged as a warning) rather than erroring.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release history.

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.
