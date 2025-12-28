<?php

use Vherbaut\InboundWebhooks\Enums\WebhookStatus;
use Vherbaut\InboundWebhooks\Events\WebhookFailed;
use Vherbaut\InboundWebhooks\Events\WebhookProcessed;
use Vherbaut\InboundWebhooks\Events\WebhookReceived;
use Vherbaut\InboundWebhooks\Models\InboundWebhook;

it('WebhookReceived provides access to webhook', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'event_type' => 'payment_intent.succeeded',
        'status' => WebhookStatus::Pending,
        'payload' => [
            'id' => 'evt_123',
            'data' => [
                'object' => [
                    'amount' => 1000,
                    'currency' => 'usd',
                ],
            ],
        ],
    ]);

    $event = new WebhookReceived($webhook);

    expect($event->webhook)->toBe($webhook);
});

it('WebhookReceived returns provider name', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Pending,
    ]);

    $event = new WebhookReceived($webhook);

    expect($event->provider())->toBe('stripe');
});

it('WebhookReceived returns event type', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'event_type' => 'payment_intent.succeeded',
        'status' => WebhookStatus::Pending,
    ]);

    $event = new WebhookReceived($webhook);

    expect($event->eventType())->toBe('payment_intent.succeeded');
});

it('WebhookReceived returns full payload', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Pending,
        'payload' => [
            'id' => 'evt_123',
            'data' => ['key' => 'value'],
        ],
    ]);

    $event = new WebhookReceived($webhook);
    $payload = $event->payload();

    expect($payload)->toBeArray()
        ->and($payload['id'])->toBe('evt_123');
});

it('WebhookReceived returns empty array when payload is null', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Pending,
        'payload' => null,
    ]);

    $event = new WebhookReceived($webhook);

    expect($event->payload())->toBe([]);
});

it('WebhookReceived gets nested value using dot notation', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Pending,
        'payload' => [
            'data' => [
                'object' => [
                    'amount' => 1000,
                    'currency' => 'usd',
                ],
            ],
        ],
    ]);

    $event = new WebhookReceived($webhook);

    expect($event->get('data.object.amount'))->toBe(1000)
        ->and($event->get('data.object.currency'))->toBe('usd');
});

it('WebhookReceived returns default for missing keys', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Pending,
        'payload' => ['key' => 'value'],
    ]);

    $event = new WebhookReceived($webhook);

    expect($event->get('nonexistent'))->toBeNull()
        ->and($event->get('nonexistent', 'default'))->toBe('default');
});

it('WebhookProcessed provides access to webhook', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'github',
        'status' => WebhookStatus::Processed,
    ]);

    $event = new WebhookProcessed($webhook);

    expect($event->webhook)->toBe($webhook)
        ->and($event->webhook->provider)->toBe('github');
});

it('WebhookFailed provides access to webhook and exception', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'slack',
        'status' => WebhookStatus::Failed,
    ]);

    $exception = new \RuntimeException('Test error');
    $event = new WebhookFailed($webhook, $exception);

    expect($event->webhook)->toBe($webhook)
        ->and($event->exception)->toBe($exception)
        ->and($event->exception->getMessage())->toBe('Test error');
});
