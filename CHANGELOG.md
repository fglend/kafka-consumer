# Changelog

## Unreleased

- `kafka_producer_topics` gains a third new nullable column, `payload_template` (raw `text`) — a free-form JSON template hand-editable in `gurento/kafka-producer-filament`'s UI; no runtime effect on `KafkaProducerService::send()`. Added via another safe, additive migration.
- `kafka_producer_topics` gains two new nullable columns via a safe, additive migration: `model_class` (an optional Eloquent model used purely to auto-derive `payload_schema`/`relations` in `gurento/kafka-producer-filament`'s UI — no runtime effect on `KafkaProducerService::send()`) and `relations` (JSON, informational). `KafkaProducerTopic`'s `$fillable`/`casts()` updated to match.
- Migrations are now idempotent: each `up()` checks `Schema::hasTable()` before creating its table and skips if it already exists. Safe to re-run `php artisan migrate` in environments where a table exists but isn't tracked in the `migrations` table (e.g. after re-publishing migrations), without needing `migrate:fresh` or manual DB surgery.

## 1.3.0 - 2026-07-20

- **Producer side added** — the package now supports publishing messages, not just consuming them:
  - `Contracts\ProducerEngine` + default `Engines\LaravelKafkaProducerEngine` (based on `mateusjunges/laravel-kafka`), bound as a singleton
  - New tables `kafka_producer_topics` and `kafka_produce_logs` (published via the existing `kafka-consumer-migrations` tag)
  - `Models\KafkaProducerTopic` / `Models\KafkaProduceLog` with `health_status`, `failure_rate`, and `pendingRetryCount()` mirroring the consumer models
  - `Services\KafkaProducerService::send()` / `retryFailedMessages()` / `retryLog()` with the same retry/backoff semantics as the consumer service
  - `gurento:kafka-produce` command — ad-hoc sends (`--topic`, `--payload`, `--key`) and failed-send retries (`--retry-failed`, `--retry-limit`)
  - Events `KafkaMessageProduced` / `KafkaMessageProduceFailed`
  - Actions `RetryKafkaProduceFailuresAction` / `ResetKafkaProducerCountersAction`
  - New `producer` config section (`max_send_attempts`, `send_backoff_seconds`, `health_stale_after_seconds`)

## 1.2.3 - 2026-07-17

- Laravel 13 support: `illuminate/*` constraints widened to `^11.0|^12.0|^13.0` (Laravel 13 requires PHP 8.3+, enforced by the framework)
- Dev: orchestra/testbench `^11.0` and phpunit `^12.0` allowed
- New `SECURITY.md` with supported versions, private vulnerability reporting, and operational security notes
- README: requirements table updated to Laravel 11 / 12 / 13

## 1.2.2 - 2026-07-14

- README: environment configuration section with Kafka connection settings (`KAFKA_BROKERS`, `KAFKA_DEBUG`)

## 1.2.1 - 2026-07-14

- README: expanded features, requirements, and configuration details

## 1.2.0 - 2026-07-14

- `--group=` and `--stop-on-empty` options on `gurento:kafka-consume`
- New events: `KafkaMessageConsumed` and `KafkaMessageFailed` dispatched on every processing outcome (fresh and re-consume)
- Fix: default consumer group now reads `kafka-consumer.default_group` (previously only `kafka.consumer_group_id` was consulted)
- Fix: null per-topic `max_reconsume_attempts` / `retry_backoff_seconds` / `health_stale_after_seconds` now fall back to the published `kafka-consumer` config instead of hardcoded values

## 1.1.3 - 2026-04-22

- `gurento:kafka-consume` command signature refinements and README corrections

## 1.1.2 - 2026-04-22

- README and model documentation: detailed features, requirements, and retry logic

## 1.1.1 - 2026-04-22

- Command signature updated for `gurento:kafka-consume` usage; README overhauled to match

## 1.1.0 - 2026-04-22

- `LaravelKafkaConsumerEngine` for plug-and-play integration via the service provider

## 1.0.0 - 2026-04-22

- Initial package structure
- Kafka topic model mapping service
- Retry/re-consume tracking
- Publishable config and migrations
- Console command for retry operations
