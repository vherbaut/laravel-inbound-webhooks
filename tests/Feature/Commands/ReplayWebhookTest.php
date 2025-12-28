<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Vherbaut\InboundWebhooks\Enums\WebhookStatus;
use Vherbaut\InboundWebhooks\Events\WebhookReceived;
use Vherbaut\InboundWebhooks\Jobs\ProcessWebhook;
use Vherbaut\InboundWebhooks\Models\InboundWebhook;

it('replays webhook by uuid', function () {
    Queue::fake();

    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'event_type' => 'payment_intent.succeeded',
        'status' => WebhookStatus::Failed,
        'error_message' => 'Previous error',
    ]);

    $this->artisan("webhooks:replay {$webhook->uuid}")
        ->assertExitCode(0);

    Queue::assertPushed(ProcessWebhook::class);

    expect($webhook->fresh()->status)->toBe(WebhookStatus::Pending)
        ->and($webhook->fresh()->error_message)->toBeNull();
});

it('replays webhook by id', function () {
    Queue::fake();

    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Failed,
    ]);

    $this->artisan("webhooks:replay {$webhook->id}")
        ->assertExitCode(0);

    Queue::assertPushed(ProcessWebhook::class);
});

it('processes synchronously with --sync flag', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'event_type' => 'test',
        'status' => WebhookStatus::Failed,
    ]);

    Event::fake([WebhookReceived::class, \Vherbaut\InboundWebhooks\Events\WebhookProcessed::class]);

    $this->artisan("webhooks:replay {$webhook->uuid} --sync")
        ->expectsOutputToContain('Processing webhook synchronously...')
        ->expectsOutputToContain('Webhook processed successfully.')
        ->assertExitCode(0);

    expect($webhook->fresh()->status)->toBe(WebhookStatus::Processed);
});

it('returns error when webhook not found', function () {
    $this->artisan('webhooks:replay non-existent-uuid')
        ->expectsOutputToContain('Webhook [non-existent-uuid] not found.')
        ->assertExitCode(1);
});

it('asks for confirmation when replaying processed webhook', function () {
    Queue::fake();

    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Processed,
        'processed_at' => now(),
    ]);

    $this->artisan("webhooks:replay {$webhook->uuid}")
        ->expectsOutputToContain('This webhook has already been processed.')
        ->expectsConfirmation('Do you want to replay it anyway?', 'yes')
        ->assertExitCode(0);

    Queue::assertPushed(ProcessWebhook::class);
});

it('aborts when user declines replaying processed webhook', function () {
    Queue::fake();

    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Processed,
    ]);

    $this->artisan("webhooks:replay {$webhook->uuid}")
        ->expectsConfirmation('Do you want to replay it anyway?', 'no')
        ->assertExitCode(0);

    Queue::assertNotPushed(ProcessWebhook::class);
});

it('skips confirmation with --force flag', function () {
    Queue::fake();

    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Processed,
    ]);

    $this->artisan("webhooks:replay {$webhook->uuid} --force")
        ->assertExitCode(0);

    Queue::assertPushed(ProcessWebhook::class);
});

it('displays webhook info before replay', function () {
    Queue::fake();

    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'event_type' => 'payment_intent.succeeded',
        'external_id' => 'evt_123',
        'status' => WebhookStatus::Failed,
        'attempts' => 2,
    ]);

    $this->artisan("webhooks:replay {$webhook->uuid}")
        ->expectsTable(
            ['Field', 'Value'],
            [
                ['UUID', $webhook->uuid],
                ['Provider', 'stripe'],
                ['Event Type', 'payment_intent.succeeded'],
                ['External ID', 'evt_123'],
                ['Status', 'failed'],
                ['Attempts', '2'],
                ['Created', $webhook->created_at->format('Y-m-d H:i:s')],
            ]
        )
        ->assertExitCode(0);
});

it('handles sync processing failure gracefully', function () {
    // Configure a non-existent event class
    config(['inbound-webhooks.events' => [
        'stripe.failing_event' => 'NonExistentEventClass',
    ]]);

    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'event_type' => 'failing_event',
        'status' => WebhookStatus::Failed,
    ]);

    $this->artisan("webhooks:replay {$webhook->uuid} --sync")
        ->expectsOutputToContain('Processing webhook synchronously...')
        ->expectsOutputToContain('Processing failed')
        ->assertExitCode(1);

    expect($webhook->fresh()->status)->toBe(WebhookStatus::Failed);
});

it('resets status before replay', function () {
    Queue::fake();

    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Failed,
        'error_message' => 'Old error',
    ]);

    $this->artisan("webhooks:replay {$webhook->uuid}")
        ->assertExitCode(0);

    $fresh = $webhook->fresh();

    expect($fresh->status)->toBe(WebhookStatus::Pending)
        ->and($fresh->error_message)->toBeNull();
});
