# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |
| < 1.0   | :x:                |

Only the latest `1.x` release receives security fixes. Please upgrade to the most recent version before reporting an issue.

## Reporting a Vulnerability

If you discover a security vulnerability in `gurento/kafka-consumer`, please report it privately. **Do not open a public GitHub issue.**

- Email: **gdferrer@up.edu.ph**
- Subject line: `[SECURITY] kafka-consumer — <short description>`

Please include:

- A description of the vulnerability and its impact
- Steps to reproduce (a minimal example is ideal)
- The package version and PHP/Laravel versions affected
- Any suggested fix, if you have one

You can expect an acknowledgement within **72 hours**.

## Disclosure Process

1. Your report is acknowledged and triaged.
2. A fix is developed and validated privately.
3. A patched release is published to Packagist, with the fix noted in the [CHANGELOG](CHANGELOG.md).
4. After the release, the vulnerability may be disclosed publicly. Reporters are credited unless they prefer to remain anonymous.

## Operational Security Notes

This package connects to Kafka brokers and writes consume/failure logs to your database. When deploying:

- Keep `KAFKA_BROKERS` and any SASL/SSL credentials in your environment (`.env`), never in committed config.
- Restrict database access to the `kafka_topics` and log tables — they can contain message payloads.
- Treat consumed message payloads as untrusted input; validate before acting on them in your handlers.
