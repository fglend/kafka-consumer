# Changelog

## 2.0.0 - 2026-07-21

**Major release: full producer (send) support alongside the existing consumer.** The package now covers both directions of Kafka messaging with matching architecture, retry semantics, and observability on each side.

### Added — Producer core

- `Contracts\ProducerEngine` + default `Engines\LaravelKafkaProducerEngine` (based on `mateusjunges/laravel-kafka`), bound as a singleton alongside the existing `ConsumerEngine`
- New tables `kafka_producer_topics` and `kafka_produce_logs` (published via the existing `kafka-consumer-migrations` tag)
- `Models\KafkaProducerTopic` / `Models\KafkaProduceLog` with `health_status`, `failure_rate`, and `pendingRetryCount()` mirroring the consumer-side models
- `Services\KafkaProducerService::send()` / `retryFailedMessages()` / `retryLog()` with the same retry/backoff semantics as the consumer service
- `gurento:kafka-produce` command — ad-hoc sends (`--topic`, `--payload`, `--key`) and failed-send retries (`--retry-failed`, `--retry-limit`)
- Events `KafkaMessageProduced` / `KafkaMessageProduceFailed`
- Actions `RetryKafkaProduceFailuresAction` / `ResetKafkaProducerCountersAction`
- New `producer` config section (`max_send_attempts`, `send_backoff_seconds`, `health_stale_after_seconds`)

### Added — Dynamic producer schema (for `gurento/kafka-producer-filament`)

- `kafka_producer_topics.model_class` — optional Eloquent model used only by the Filament UI to auto-derive a payload schema and detected relationships; no runtime effect on `send()`
- `kafka_producer_topics.relations` (JSON) — informational relationship metadata surfaced in the UI
- `kafka_producer_topics.payload_template` (raw `text`) — a free-form JSON template hand-editable in the Filament UI; when set, it's what the Send Message composer pre-fills, ahead of building one from `payload_schema`/`relations`
- `KafkaProducerTopic`'s `$fillable`/`casts()` updated to match all three new columns

### Changed

- Migrations are now idempotent: every `up()` checks `Schema::hasTable()` (and, for the add-column migrations, `Schema::hasColumn()`) before making changes, skipping cleanly if already applied. Safe to re-run `php artisan migrate` in environments where a table exists but isn't tracked in the `migrations` table (e.g. after re-publishing migrations), without needing `migrate:fresh` or manual DB surgery.

### Compatibility

- All changes are additive — existing consumer-side behavior, config, and events are unchanged. No breaking changes to the public API; the major version bump reflects the scope of the new producer capability, not a compatibility break.

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
