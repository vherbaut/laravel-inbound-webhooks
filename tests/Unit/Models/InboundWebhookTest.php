<?php

use Illuminate\Support\Str;
use Vherbaut\InboundWebhooks\Enums\WebhookStatus;
use Vherbaut\InboundWebhooks\Models\InboundWebhook;

it('generates uuid automatically on creation', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'event_type' => 'payment_intent.succeeded',
        'status' => WebhookStatus::Pending,
    ]);

    expect($webhook->uuid)->not->toBeNull()
        ->and(Str::isUuid($webhook->uuid))->toBeTrue();
});

it('does not override provided uuid', function () {
    $customUuid = (string) Str::uuid();

    $webhook = InboundWebhook::create([
        'uuid' => $customUuid,
        'provider' => 'stripe',
        'status' => WebhookStatus::Pending,
    ]);

    expect($webhook->uuid)->toBe($customUuid);
});

it('casts status to WebhookStatus enum', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Pending,
    ]);

    expect($webhook->status)->toBeInstanceOf(WebhookStatus::class)
        ->and($webhook->status)->toBe(WebhookStatus::Pending);
});

it('casts headers and payload to arrays', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Pending,
        'headers' => ['Content-Type' => 'application/json'],
        'payload' => ['id' => 'evt_123', 'type' => 'test'],
    ]);

    expect($webhook->headers)->toBeArray()
        ->and($webhook->headers)->toBe(['Content-Type' => 'application/json'])
        ->and($webhook->payload)->toBeArray()
        ->and($webhook->payload['id'])->toBe('evt_123');
});

it('casts processed_at to datetime', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Processed,
        'processed_at' => now(),
    ]);

    expect($webhook->processed_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('correctly identifies pending status', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Pending,
    ]);

    expect($webhook->isPending())->toBeTrue()
        ->and($webhook->isProcessing())->toBeFalse()
        ->and($webhook->isProcessed())->toBeFalse()
        ->and($webhook->isFailed())->toBeFalse();
});

it('correctly identifies processing status', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Processing,
    ]);

    expect($webhook->isProcessing())->toBeTrue()
        ->and($webhook->isPending())->toBeFalse();
});

it('correctly identifies processed status', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Processed,
    ]);

    expect($webhook->isProcessed())->toBeTrue()
        ->and($webhook->isPending())->toBeFalse();
});

it('correctly identifies failed status', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Failed,
    ]);

    expect($webhook->isFailed())->toBeTrue()
        ->and($webhook->isPending())->toBeFalse();
});

it('marks webhook as processing and increments attempts', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Pending,
        'attempts' => 0,
    ]);

    $webhook->markAsProcessing();

    expect($webhook->fresh()->status)->toBe(WebhookStatus::Processing)
        ->and($webhook->fresh()->attempts)->toBe(1);
});

it('marks webhook as processed with timestamp', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Processing,
        'error_message' => 'Previous error',
    ]);

    $webhook->markAsProcessed();
    $fresh = $webhook->fresh();

    expect($fresh->status)->toBe(WebhookStatus::Processed)
        ->and($fresh->processed_at)->not->toBeNull()
        ->and($fresh->error_message)->toBeNull();
});

it('marks webhook as failed with error message', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Processing,
    ]);

    $webhook->markAsFailed('Something went wrong');
    $fresh = $webhook->fresh();

    expect($fresh->status)->toBe(WebhookStatus::Failed)
        ->and($fresh->error_message)->toBe('Something went wrong');
});

it('resets webhook for retry', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Failed,
        'error_message' => 'Previous error',
    ]);

    $webhook->resetForRetry();
    $fresh = $webhook->fresh();

    expect($fresh->status)->toBe(WebhookStatus::Pending)
        ->and($fresh->error_message)->toBeNull();
});

it('returns self for method chaining', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Pending,
    ]);

    expect($webhook->markAsProcessing())->toBeInstanceOf(InboundWebhook::class)
        ->and($webhook->markAsProcessed())->toBeInstanceOf(InboundWebhook::class)
        ->and($webhook->markAsFailed('error'))->toBeInstanceOf(InboundWebhook::class)
        ->and($webhook->resetForRetry())->toBeInstanceOf(InboundWebhook::class);
});

it('filters by provider', function () {
    InboundWebhook::create(['provider' => 'stripe', 'event_type' => 'payment', 'status' => WebhookStatus::Pending]);
    InboundWebhook::create(['provider' => 'stripe', 'event_type' => 'refund', 'status' => WebhookStatus::Processed]);
    InboundWebhook::create(['provider' => 'github', 'event_type' => 'push', 'status' => WebhookStatus::Failed]);

    $webhooks = InboundWebhook::provider('stripe')->get();

    expect($webhooks)->toHaveCount(2)
        ->and($webhooks->pluck('provider')->unique()->first())->toBe('stripe');
});

it('filters by event type', function () {
    InboundWebhook::create(['provider' => 'stripe', 'event_type' => 'payment', 'status' => WebhookStatus::Pending]);
    InboundWebhook::create(['provider' => 'github', 'event_type' => 'push', 'status' => WebhookStatus::Failed]);
    InboundWebhook::create(['provider' => 'github', 'event_type' => 'push', 'status' => WebhookStatus::Processing]);

    $webhooks = InboundWebhook::eventType('push')->get();

    expect($webhooks)->toHaveCount(2)
        ->and($webhooks->pluck('event_type')->unique()->first())->toBe('push');
});

it('filters by status enum', function () {
    InboundWebhook::create(['provider' => 'stripe', 'status' => WebhookStatus::Pending]);
    InboundWebhook::create(['provider' => 'github', 'status' => WebhookStatus::Failed]);

    $webhooks = InboundWebhook::status(WebhookStatus::Failed)->get();

    expect($webhooks)->toHaveCount(1)
        ->and($webhooks->first()->status)->toBe(WebhookStatus::Failed);
});

it('filters pending webhooks', function () {
    InboundWebhook::create(['provider' => 'stripe', 'status' => WebhookStatus::Pending]);
    InboundWebhook::create(['provider' => 'stripe', 'status' => WebhookStatus::Processed]);

    $webhooks = InboundWebhook::pending()->get();

    expect($webhooks)->toHaveCount(1)
        ->and($webhooks->first()->status)->toBe(WebhookStatus::Pending);
});

it('filters processed webhooks', function () {
    InboundWebhook::create(['provider' => 'stripe', 'status' => WebhookStatus::Pending]);
    InboundWebhook::create(['provider' => 'stripe', 'status' => WebhookStatus::Processed]);

    $webhooks = InboundWebhook::processed()->get();

    expect($webhooks)->toHaveCount(1)
        ->and($webhooks->first()->status)->toBe(WebhookStatus::Processed);
});

it('filters failed webhooks', function () {
    InboundWebhook::create(['provider' => 'stripe', 'status' => WebhookStatus::Pending]);
    InboundWebhook::create(['provider' => 'github', 'status' => WebhookStatus::Failed]);

    $webhooks = InboundWebhook::failed()->get();

    expect($webhooks)->toHaveCount(1)
        ->and($webhooks->first()->status)->toBe(WebhookStatus::Failed);
});

it('filters webhooks older than specified days', function () {
    $recent = InboundWebhook::create([
        'provider' => 'recent',
        'status' => WebhookStatus::Processed,
    ]);
    $recent->created_at = now()->subDays(10);
    $recent->save();

    $old = InboundWebhook::create([
        'provider' => 'old',
        'status' => WebhookStatus::Processed,
    ]);
    $old->created_at = now()->subDays(40);
    $old->save();

    $oldWebhooks = InboundWebhook::olderThan(30)->get();

    expect($oldWebhooks)->toHaveCount(1)
        ->and($oldWebhooks->first()->provider)->toBe('old');
});

it('chains multiple scopes', function () {
    InboundWebhook::create(['provider' => 'stripe', 'status' => WebhookStatus::Pending]);
    InboundWebhook::create(['provider' => 'stripe', 'status' => WebhookStatus::Processed]);
    InboundWebhook::create(['provider' => 'github', 'status' => WebhookStatus::Pending]);

    $webhooks = InboundWebhook::provider('stripe')
        ->status(WebhookStatus::Pending)
        ->get();

    expect($webhooks)->toHaveCount(1)
        ->and($webhooks->first()->provider)->toBe('stripe')
        ->and($webhooks->first()->status)->toBe(WebhookStatus::Pending);
});

it('uses uuid as route key', function () {
    $webhook = new InboundWebhook;

    expect($webhook->getRouteKeyName())->toBe('uuid');
});

it('generates correct event identifier', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'event_type' => 'payment_intent.succeeded',
        'status' => WebhookStatus::Pending,
    ]);

    expect($webhook->getEventIdentifier())->toBe('stripe.payment_intent.succeeded');
});

it('returns query for prunable records based on config', function () {
    config(['inbound-webhooks.storage.retention_days' => 30]);

    $old = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Processed,
    ]);
    $old->created_at = now()->subDays(40);
    $old->save();

    $recent = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Processed,
    ]);
    $recent->created_at = now()->subDays(10);
    $recent->save();

    $prunable = (new InboundWebhook)->prunable()->get();

    expect($prunable)->toHaveCount(1);
});

it('returns empty query when retention is null', function () {
    config(['inbound-webhooks.storage.retention_days' => null]);

    InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Processed,
        'created_at' => now()->subDays(100),
    ]);

    $prunable = (new InboundWebhook)->prunable()->get();

    expect($prunable)->toHaveCount(0);
});
