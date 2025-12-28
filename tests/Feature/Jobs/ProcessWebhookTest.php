<?php

use Illuminate\Support\Facades\Event;
use Vherbaut\InboundWebhooks\Enums\WebhookStatus;
use Vherbaut\InboundWebhooks\Events\WebhookFailed;
use Vherbaut\InboundWebhooks\Events\WebhookProcessed;
use Vherbaut\InboundWebhooks\Events\WebhookReceived;
use Vherbaut\InboundWebhooks\Jobs\ProcessWebhook;
use Vherbaut\InboundWebhooks\Models\InboundWebhook;

it('marks webhook as processing then processed', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'event_type' => 'payment_intent.succeeded',
        'status' => WebhookStatus::Pending,
        'attempts' => 0,
    ]);

    Event::fake([WebhookReceived::class, WebhookProcessed::class, WebhookFailed::class]);

    $job = new ProcessWebhook($webhook);
    $job->handle();

    $fresh = $webhook->fresh();

    expect($fresh->status)->toBe(WebhookStatus::Processed)
        ->and($fresh->attempts)->toBe(1)
        ->and($fresh->processed_at)->not->toBeNull();
});

it('dispatches WebhookReceived event', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'event_type' => 'payment_intent.succeeded',
        'status' => WebhookStatus::Pending,
        'payload' => ['id' => 'pi_123'],
    ]);

    Event::fake([WebhookReceived::class, WebhookProcessed::class, WebhookFailed::class]);

    $job = new ProcessWebhook($webhook);
    $job->handle();

    Event::assertDispatched(WebhookReceived::class, function ($event) use ($webhook) {
        return $event->webhook->id === $webhook->id
            && $event->provider() === 'stripe'
            && $event->eventType() === 'payment_intent.succeeded';
    });
});

it('dispatches WebhookProcessed event on success', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Pending,
    ]);

    Event::fake([WebhookReceived::class, WebhookProcessed::class, WebhookFailed::class]);

    $job = new ProcessWebhook($webhook);
    $job->handle();

    Event::assertDispatched(WebhookProcessed::class, function ($event) use ($webhook) {
        return $event->webhook->id === $webhook->id;
    });
});

it('dispatches mapped event when configured', function () {
    $customEventClass = new class
    {
        public InboundWebhook $webhook;

        public function __construct(?InboundWebhook $webhook = null)
        {
            if ($webhook) {
                $this->webhook = $webhook;
            }
        }
    };

    config(['inbound-webhooks.events' => [
        'stripe.payment_intent.succeeded' => get_class($customEventClass),
    ]]);

    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'event_type' => 'payment_intent.succeeded',
        'status' => WebhookStatus::Pending,
    ]);

    Event::fake([WebhookReceived::class, WebhookProcessed::class, WebhookFailed::class, get_class($customEventClass)]);

    $job = new ProcessWebhook($webhook);
    $job->handle();

    Event::assertDispatched(get_class($customEventClass));
});

it('does not dispatch mapped event when not configured', function () {
    config(['inbound-webhooks.events' => []]);

    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'event_type' => 'unknown.event',
        'status' => WebhookStatus::Pending,
    ]);

    Event::fake([WebhookReceived::class, WebhookProcessed::class, WebhookFailed::class]);

    $job = new ProcessWebhook($webhook);
    $job->handle();

    Event::assertDispatched(WebhookReceived::class);
    Event::assertDispatched(WebhookProcessed::class);
});

it('increments attempts on each processing', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Pending,
        'attempts' => 2,
    ]);

    Event::fake([WebhookReceived::class, WebhookProcessed::class, WebhookFailed::class]);

    $job = new ProcessWebhook($webhook);
    $job->handle();

    expect($webhook->fresh()->attempts)->toBe(3);
});

it('marks webhook as failed when failed method is called', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Processing,
    ]);

    Event::fake([WebhookReceived::class, WebhookProcessed::class, WebhookFailed::class]);

    $job = new ProcessWebhook($webhook);
    $exception = new \Exception('Job failed completely');

    $job->failed($exception);

    $fresh = $webhook->fresh();

    expect($fresh->status)->toBe(WebhookStatus::Failed)
        ->and($fresh->error_message)->toBe('Job failed completely');

    Event::assertDispatched(WebhookFailed::class, function ($event) use ($exception) {
        return $event->exception === $exception;
    });
});

it('handles null exception gracefully', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Processing,
    ]);

    Event::fake([WebhookReceived::class, WebhookProcessed::class, WebhookFailed::class]);

    $job = new ProcessWebhook($webhook);
    $job->failed(null);

    expect($webhook->fresh()->status)->toBe(WebhookStatus::Processing);
});

it('returns correct tags for job tracking', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'event_type' => 'payment_intent.succeeded',
        'status' => WebhookStatus::Pending,
    ]);

    $job = new ProcessWebhook($webhook);
    $tags = $job->tags();

    expect($tags)->toContain('inbound-webhook')
        ->and($tags)->toContain('provider:stripe')
        ->and($tags)->toContain('event:payment_intent.succeeded');
});

it('has correct retry configuration', function () {
    $webhook = InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Pending,
    ]);

    $job = new ProcessWebhook($webhook);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([10, 60, 300]);
});
