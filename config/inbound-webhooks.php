<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Webhook Route Path
    |--------------------------------------------------------------------------
    |
    | The base path for incoming webhooks. Each provider will be accessible
    | at: {path}/{provider} (e.g., /webhooks/stripe, /webhooks/github)
    |
    */
    'path' => 'webhooks',

    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware to apply to all webhook routes. By default, we exclude
    | CSRF verification since webhooks come from external services.
    |
    */
    'middleware' => ['api'],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Webhooks are processed asynchronously via queues. Configure the
    | connection and queue name for webhook processing jobs.
    |
    */
    'queue' => [
        'connection' => env('INBOUND_WEBHOOKS_QUEUE_CONNECTION', 'default'),
        'queue' => env('INBOUND_WEBHOOKS_QUEUE', 'webhooks'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how long to keep webhook logs and whether to store
    | the full payload (useful for replay but uses more storage).
    |
    */
    'storage' => [
        'store_payload' => true,
        'retention_days' => 30, // Set to null to keep forever
    ],

    /*
    |--------------------------------------------------------------------------
    | Providers Configuration
    |--------------------------------------------------------------------------
    |
    | Configure each webhook provider with its driver, secret, and
    | optional settings. Add your own providers as needed.
    |
    */
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
            'tolerance' => 300,
        ],

        'twilio' => [
            'driver' => 'twilio',
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
        ],

        // Example custom provider using HMAC
        // 'custom' => [
        //     'driver' => 'hmac',
        //     'secret' => env('CUSTOM_WEBHOOK_SECRET'),
        //     'algorithm' => 'sha256',
        //     'header' => 'X-Custom-Signature',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Mapping
    |--------------------------------------------------------------------------
    |
    | Map specific webhook event types to dedicated event classes.
    | If not mapped, the generic WebhookReceived event is dispatched.
    |
    | Format: 'provider.event_type' => EventClass::class
    |
    */
    'events' => [
        // 'stripe.payment_intent.succeeded' => \App\Events\StripePaymentSucceeded::class,
        // 'github.push' => \App\Events\GitHubPush::class,
    ],
];
