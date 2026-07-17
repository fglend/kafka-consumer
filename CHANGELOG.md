# Changelog

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
