# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

Instead, please report them via email to: **vincenth.lzh@gmail.com**

Include the following information in your report:

- Type of vulnerability (e.g., signature bypass, injection, etc.)
- Full path to the affected source file(s)
- Step-by-step instructions to reproduce
- Proof-of-concept or exploit code (if possible)
- Impact assessment

### What to Expect

- **Acknowledgment**: Within 48 hours of your report
- **Status update**: Within 7 days with an assessment
- **Resolution timeline**: Depends on severity, typically 30-90 days

### Disclosure Policy

- We will work with you to understand and resolve the issue
- We will credit you in the security advisory (unless you prefer anonymity)
- We ask that you give us reasonable time to address the issue before public disclosure

## Security Best Practices

When using this package, ensure you:

### 1. Keep Secrets Secure

```bash
# Never commit secrets to version control
STRIPE_WEBHOOK_SECRET=whsec_xxxxx
GITHUB_WEBHOOK_SECRET=xxxxx
```

### 2. Use HTTPS

Always configure your webhook endpoints with HTTPS URLs to prevent man-in-the-middle attacks.

### 3. Validate All Webhooks

Never disable signature validation in production:

```php
// NEVER do this in production
'providers' => [
    'stripe' => [
        'driver' => 'stripe',
        'secret' => null, // DON'T DO THIS
    ],
],
```

### 4. Monitor Failed Webhooks

Set up monitoring for failed webhook signatures:

```php
use Vherbaut\InboundWebhooks\Events\WebhookFailed;

Event::listen(WebhookFailed::class, function ($event) {
    if (str_contains($event->exception->getMessage(), 'signature')) {
        // Alert on potential attack
        Log::alert('Webhook signature validation failed', [
            'provider' => $event->webhook->provider,
            'ip' => request()->ip(),
        ]);
    }
});
```

### 5. Prune Old Data

Configure retention to avoid storing sensitive data indefinitely:

```php
'storage' => [
    'retention_days' => 30,
],
```

## Security Features

This package implements several security measures:

- **Timing-safe comparison**: All signature comparisons use `hash_equals()` to prevent timing attacks
- **Timestamp validation**: Prevents replay attacks by rejecting old requests
- **Header storage**: Stores relevant headers for security auditing
- **Queue isolation**: Webhook processing is isolated in queue jobs

## Known Security Considerations

### Payload Storage

Webhook payloads may contain sensitive data. Consider:

- Setting appropriate `retention_days`
- Encrypting the `payload` column if needed
- Restricting database access

### Queue Security

Ensure your queue workers run in a secure environment, as they process potentially sensitive webhook data.