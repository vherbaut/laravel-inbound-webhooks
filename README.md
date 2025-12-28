# Laravel Inbound Webhooks

[![Tests](https://github.com/vherbaut/laravel-inbound-webhooks/actions/workflows/tests.yml/badge.svg)](https://github.com/vherbaut/laravel-inbound-webhooks/actions/workflows/tests.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%205-brightgreen.svg)](https://phpstan.org/)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/vherbaut/laravel-inbound-webhooks.svg)](https://packagist.org/packages/vherbaut/laravel-inbound-webhooks)
[![Total Downloads](https://img.shields.io/packagist/dt/vherbaut/laravel-inbound-webhooks.svg)](https://packagist.org/packages/vherbaut/laravel-inbound-webhooks)
[![License](https://img.shields.io/packagist/l/vherbaut/laravel-inbound-webhooks.svg)](https://packagist.org/packages/vherbaut/laravel-inbound-webhooks)
[![PHP Version](https://img.shields.io/packagist/php-v/vherbaut/laravel-inbound-webhooks.svg)](https://packagist.org/packages/vherbaut/laravel-inbound-webhooks)

A Laravel package to handle inbound webhooks from any provider with signature validation, logging, queue processing, and replay capabilities.

## Features

- 🔐 **Signature validation** for Stripe, GitHub, Slack, Twilio, and custom HMAC
- 📦 **Automatic storage** of all incoming webhooks with full payload
- ⚡ **Async processing** via Laravel queues (responds 200 in < 100ms)
- 🔄 **Replay webhooks** for debugging and development
- 🗑️ **Automatic cleanup** with configurable retention
- 📊 **Event mapping** to dispatch custom Laravel events
- 🔌 **Extensible** - add your own drivers easily

## Installation

```bash
composer require vherbaut/laravel-inbound-webhooks
```

Publish the config and migrations:

```bash
php artisan vendor:publish --tag=inbound-webhooks-config
php artisan vendor:publish --tag=inbound-webhooks-migrations
php artisan migrate
```

## Quick Start

### 1. Configure your provider

Add your webhook secret to `.env`:

```env
STRIPE_WEBHOOK_SECRET=whsec_xxxxx
```

### 2. Register your webhook URL

Point your provider's webhook settings to:

```
https://your-app.com/webhooks/stripe
```

### 3. Listen for events

In your `EventServiceProvider` or a listener:

```php
use Vherbaut\InboundWebhooks\Events\WebhookReceived;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        WebhookReceived::class => [
            HandleStripeWebhook::class,
        ],
    ];
}
```

```php
// app/Listeners/HandleStripeWebhook.php

class HandleStripeWebhook
{
    public function handle(WebhookReceived $event): void
    {
        if ($event->provider() !== 'stripe') {
            return;
        }

        match ($event->eventType()) {
            'payment_intent.succeeded' => $this->handlePaymentSucceeded($event),
            'customer.subscription.deleted' => $this->handleSubscriptionCanceled($event),
            default => null,
        };
    }

    protected function handlePaymentSucceeded(WebhookReceived $event): void
    {
        $paymentIntentId = $event->get('data.object.id');
        $amount = $event->get('data.object.amount');
        
        // Your logic here...
    }
}
```

## Configuration

### Providers

Configure each provider in `config/inbound-webhooks.php`:

```php
'providers' => [
    'stripe' => [
        'driver' => 'stripe',
        'secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => 300, // Timestamp tolerance in seconds
    ],

    'github' => [
        'driver' => 'github',
        'secret' => env('GITHUB_WEBHOOK_SECRET'),
    ],

    'slack' => [
        'driver' => 'slack',
        'signing_secret' => env('SLACK_SIGNING_SECRET'),
    ],

    'twilio' => [
        'driver' => 'twilio',
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
    ],

    // Custom provider using generic HMAC
    'acme' => [
        'driver' => 'hmac',
        'secret' => env('ACME_WEBHOOK_SECRET'),
        'algorithm' => 'sha256',
        'header' => 'X-Acme-Signature',
        'prefix' => 'sha256=', // Optional prefix
        'event_key' => 'event_type',
        'id_key' => 'webhook_id',
    ],
],
```

### Event Mapping

Map specific webhook events to custom Laravel event classes:

```php
'events' => [
    'stripe.payment_intent.succeeded' => \App\Events\PaymentReceived::class,
    'stripe.customer.subscription.deleted' => \App\Events\SubscriptionCanceled::class,
    'github.push' => \App\Events\GitHubPush::class,
],
```

Your custom event receives the webhook model:

```php
// app/Events/PaymentReceived.php

class PaymentReceived
{
    public function __construct(
        public InboundWebhook $webhook
    ) {}
}
```

## Events

The package dispatches three events during the webhook lifecycle:

### WebhookReceived

Dispatched when a webhook is received and ready for processing. This is the primary event for handling webhooks.

```php
use Vherbaut\InboundWebhooks\Events\WebhookReceived;

class HandleStripeWebhook
{
    public function handle(WebhookReceived $event): void
    {
        // Access webhook data via helper methods
        $provider = $event->provider();           // "stripe"
        $eventType = $event->eventType();         // "payment_intent.succeeded"
        $payload = $event->payload();             // Full payload array
        $value = $event->get('data.object.id');   // Dot notation access
    }
}
```

### WebhookProcessed

Dispatched after a webhook has been successfully processed. Useful for metrics or cleanup.

```php
use Vherbaut\InboundWebhooks\Events\WebhookProcessed;

class UpdateMetrics
{
    public function handle(WebhookProcessed $event): void
    {
        Metrics::increment("webhooks.{$event->webhook->provider}.success");
    }
}
```

### WebhookFailed

Dispatched when webhook processing fails. Use for error notifications or logging.

```php
use Vherbaut\InboundWebhooks\Events\WebhookFailed;

class NotifyOnFailure
{
    public function handle(WebhookFailed $event): void
    {
        Log::error('Webhook failed', [
            'provider' => $event->webhook->provider,
            'event_type' => $event->webhook->event_type,
            'error' => $event->exception->getMessage(),
        ]);

        // Send notification to Slack, email, etc.
    }
}
```

### Registering Listeners

Register your listeners in `EventServiceProvider`:

```php
use Vherbaut\InboundWebhooks\Events\WebhookReceived;
use Vherbaut\InboundWebhooks\Events\WebhookProcessed;
use Vherbaut\InboundWebhooks\Events\WebhookFailed;

protected $listen = [
    WebhookReceived::class => [
        HandleStripeWebhook::class,
        HandleGitHubWebhook::class,
    ],
    WebhookProcessed::class => [
        UpdateWebhookMetrics::class,
    ],
    WebhookFailed::class => [
        NotifyOnWebhookFailure::class,
        RetryFailedWebhook::class,
    ],
];
```

### Queue Configuration

```php
'queue' => [
    'connection' => env('INBOUND_WEBHOOKS_QUEUE_CONNECTION', 'default'),
    'queue' => env('INBOUND_WEBHOOKS_QUEUE', 'webhooks'),
],
```

### Storage

```php
'storage' => [
    'store_payload' => true,    // Store full payload (recommended for replay)
    'retention_days' => 30,     // Auto-prune after 30 days (null = forever)
],
```

## Console Commands

### Replay a webhook

```bash
# By UUID
php artisan webhooks:replay 550e8400-e29b-41d4-a716-446655440000

# Process synchronously (useful for debugging)
php artisan webhooks:replay 550e8400-e29b-41d4-a716-446655440000 --sync

# Force replay even if already processed
php artisan webhooks:replay 550e8400-e29b-41d4-a716-446655440000 --force
```

### Prune old webhooks

```bash
# Use configured retention days
php artisan webhooks:prune

# Custom retention
php artisan webhooks:prune --days=7

# Only prune failed webhooks
php artisan webhooks:prune --status=failed

# Only prune specific provider
php artisan webhooks:prune --provider=stripe

# Dry run (see what would be deleted)
php artisan webhooks:prune --dry-run
```

Schedule automatic pruning in `app/Console/Kernel.php`:

```php
$schedule->command('webhooks:prune')->daily();
```

## Custom Drivers

Create custom drivers to integrate with any webhook provider not covered by the built-in drivers.

### Registering a Custom Driver

Register your driver in the `boot()` method of a service provider:

```php
// app/Providers/AppServiceProvider.php

use Vherbaut\InboundWebhooks\Facades\InboundWebhooks;
use App\Webhooks\Drivers\PayPalDriver;

public function boot(): void
{
    InboundWebhooks::extend('paypal', function (array $config) {
        return new PayPalDriver($config);
    });
}
```

### Configuration

Add your provider configuration in `config/inbound-webhooks.php`:

```php
'providers' => [
    'paypal' => [
        'driver' => 'paypal',                              // Must match the name used in extend()
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),          // Custom config keys
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'sandbox' => env('PAYPAL_SANDBOX', true),
    ],
],
```

### Creating the Driver Class

Extend `AbstractDriver` and implement the required methods from `DriverInterface`:

```php
// app/Webhooks/Drivers/PayPalDriver.php

namespace App\Webhooks\Drivers;

use Illuminate\Http\Request;
use Vherbaut\InboundWebhooks\Drivers\AbstractDriver;
use Vherbaut\InboundWebhooks\Exceptions\InvalidSignatureException;

class PayPalDriver extends AbstractDriver
{
    /**
     * Validate the webhook signature.
     *
     * @throws InvalidSignatureException
     */
    public function validateSignature(Request $request): void
    {
        $transmissionId = $request->header('Paypal-Transmission-Id');
        $timestamp = $request->header('Paypal-Transmission-Time');
        $signature = $request->header('Paypal-Transmission-Sig');
        $certUrl = $request->header('Paypal-Cert-Url');

        if (! $transmissionId || ! $signature) {
            throw new InvalidSignatureException('Missing PayPal signature headers');
        }

        // Build the expected signature string
        $webhookId = $this->config['webhook_id'];
        $payload = $request->getContent();
        $crc32 = crc32($payload);

        $expectedSignature = "{$transmissionId}|{$timestamp}|{$webhookId}|{$crc32}";

        // Verify with PayPal certificate (simplified example)
        if (! $this->verifyWithCertificate($expectedSignature, $signature, $certUrl)) {
            throw new InvalidSignatureException('Invalid PayPal webhook signature');
        }
    }

    /**
     * Extract the event type from the webhook payload.
     */
    public function getEventType(Request $request): ?string
    {
        return $request->input('event_type');
    }

    /**
     * Extract the external ID (PayPal's webhook event ID).
     */
    public function getExternalId(Request $request): ?string
    {
        return $request->input('id');
    }

    /**
     * Define provider-specific headers to store for auditing.
     *
     * @return array<int, string>
     */
    protected function getRelevantHeaders(): array
    {
        return [
            'Content-Type',
            'Paypal-Transmission-Id',
            'Paypal-Transmission-Time',
            'Paypal-Transmission-Sig',
            'Paypal-Cert-Url',
        ];
    }

    private function verifyWithCertificate(
        string $data,
        string $signature,
        ?string $certUrl
    ): bool {
        // Your verification logic here
        return true;
    }
}
```

### Using AbstractDriver Helpers

The `AbstractDriver` class provides utility methods for common signature validation patterns:

```php
class AcmeDriver extends AbstractDriver
{
    public function validateSignature(Request $request): void
    {
        $signature = $request->header('X-Acme-Signature');
        $payload = $request->getContent();
        $secret = $this->config['secret'];

        if (! $signature) {
            throw new InvalidSignatureException('Missing signature header');
        }

        // Use built-in HMAC computation
        $expected = $this->computeHmac($payload, $secret, 'sha256');

        // Use timing-safe comparison to prevent timing attacks
        if (! $this->compareSignatures($expected, $signature)) {
            throw new InvalidSignatureException('Invalid signature');
        }
    }

    // ...
}
```

**Available helper methods:**

| Method | Description |
|--------|-------------|
| `computeHmac($payload, $secret, $algorithm)` | Compute HMAC signature (default: sha256) |
| `compareSignatures($expected, $actual)` | Timing-safe string comparison |
| `getRelevantHeaders()` | Override to define storable headers |
| `getPayload($request)` | Override to customize payload extraction |
| `getStorableHeaders($request)` | Filters headers based on `getRelevantHeaders()` |

### Custom Payload Extraction

Override `getPayload()` if your provider sends data in a non-standard format:

```php
public function getPayload(Request $request): array
{
    // Handle form-encoded webhooks (e.g., Twilio)
    if ($request->isJson()) {
        return $request->json()->all();
    }

    return $request->all();
}
```

### Full Driver Interface Reference

Your driver must implement all methods from `DriverInterface`:

```php
interface DriverInterface
{
    /**
     * Validate the webhook signature.
     *
     * @throws InvalidSignatureException
     */
    public function validateSignature(Request $request): void;

    /**
     * Extract the event type from the webhook payload.
     */
    public function getEventType(Request $request): ?string;

    /**
     * Extract the external ID (provider's webhook/event ID).
     */
    public function getExternalId(Request $request): ?string;

    /**
     * Get the parsed payload from the request.
     */
    public function getPayload(Request $request): array;

    /**
     * Get headers that should be stored with the webhook.
     */
    public function getStorableHeaders(Request $request): array;
}
```

### Testing Custom Drivers

```php
use App\Webhooks\Drivers\PayPalDriver;
use Illuminate\Http\Request;
use Vherbaut\InboundWebhooks\Exceptions\InvalidSignatureException;

it('validates paypal webhook signature', function () {
    $driver = new PayPalDriver([
        'webhook_id' => 'WH-123',
        'client_id' => 'test',
        'client_secret' => 'secret',
    ]);

    $request = Request::create('/webhooks/paypal', 'POST', [], [], [], [
        'HTTP_PAYPAL_TRANSMISSION_ID' => 'abc123',
        'HTTP_PAYPAL_TRANSMISSION_SIG' => 'valid-signature',
        'HTTP_PAYPAL_TRANSMISSION_TIME' => '2024-01-15T10:00:00Z',
    ], json_encode(['event_type' => 'PAYMENT.CAPTURE.COMPLETED']));

    // Should not throw
    $driver->validateSignature($request);
});

it('rejects invalid signature', function () {
    $driver = new PayPalDriver(['webhook_id' => 'WH-123']);

    $request = Request::create('/webhooks/paypal', 'POST');

    $driver->validateSignature($request);
})->throws(InvalidSignatureException::class);
```

## Querying Webhooks

```php
use Vherbaut\InboundWebhooks\Models\InboundWebhook;

// Get all failed Stripe webhooks
$failed = InboundWebhook::provider('stripe')
    ->failed()
    ->latest()
    ->get();

// Get recent payment webhooks
$payments = InboundWebhook::provider('stripe')
    ->eventType('payment_intent.succeeded')
    ->where('created_at', '>', now()->subDay())
    ->get();

// Retry all failed webhooks
InboundWebhook::failed()->each(function ($webhook) {
    $webhook->resetForRetry();
    ProcessWebhook::dispatch($webhook);
});
```

## Testing

In your tests, you can simulate incoming webhooks:

```php
use Vherbaut\InboundWebhooks\Models\InboundWebhook;

// Create a webhook directly for testing
$webhook = InboundWebhook::create([
    'provider' => 'stripe',
    'event_type' => 'payment_intent.succeeded',
    'payload' => [
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'pi_123',
                'amount' => 1000,
            ],
        ],
    ],
]);

// Process it
ProcessWebhook::dispatchSync($webhook);
```

## License

MIT
