# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2024-XX-XX

### Added

- Initial release
- **Drivers**: Stripe, GitHub, Slack, Twilio, and generic HMAC signature validation
- **Async processing**: Queue-based webhook processing with configurable connection and queue
- **Event system**: `WebhookReceived`, `WebhookProcessed`, and `WebhookFailed` events
- **Event mapping**: Map provider events to custom Laravel event classes
- **Model**: `InboundWebhook` with UUID support, status tracking, and query scopes
- **Commands**: `webhooks:replay` for replaying webhooks, `webhooks:prune` for cleanup
- **Storage**: Configurable payload storage and automatic retention-based pruning
- **Extensibility**: Custom driver support via `InboundWebhooks::extend()`
- **Security**: Timing-safe signature comparison, timestamp tolerance validation

### Drivers

- `StripeDriver`: HMAC-SHA256 with `t=timestamp,v1=signature` format
- `GitHubDriver`: HMAC-SHA256 with `sha256=` prefix
- `SlackDriver`: HMAC-SHA256 with `v0=` versioned signatures
- `TwilioDriver`: HMAC-SHA1 with URL + sorted params
- `HmacDriver`: Configurable generic HMAC driver

### Security

- All drivers use timing-safe comparison (`hash_equals`)
- Timestamp tolerance validation to prevent replay attacks
- Signature headers are stored for auditing

[Unreleased]: https://github.com/vherbaut/laravel-inbound-webhooks/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/vherbaut/laravel-inbound-webhooks/releases/tag/v1.0.0