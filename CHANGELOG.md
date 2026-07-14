# Changelog

## 1.1.0 - 2026-07-14

- `--group=` and `--stop-on-empty` options on `gurento:kafka-consume`
- New events: `KafkaMessageConsumed` and `KafkaMessageFailed` dispatched on every processing outcome (fresh and re-consume)
- Fix: default consumer group now reads `kafka-consumer.default_group` (previously only `kafka.consumer_group_id` was consulted)
- Fix: null per-topic `max_reconsume_attempts` / `retry_backoff_seconds` / `health_stale_after_seconds` now fall back to the published `kafka-consumer` config instead of hardcoded values

## 1.0.0 - 2026-04-22

- Initial package structure
- Kafka topic model mapping service
- Retry/re-consume tracking
- Publishable config and migrations
- Console command for retry operations
