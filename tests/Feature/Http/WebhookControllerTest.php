<?php

use Illuminate\Support\Facades\Queue;
use Vherbaut\InboundWebhooks\Enums\WebhookStatus;
use Vherbaut\InboundWebhooks\Jobs\ProcessWebhook;
use Vherbaut\InboundWebhooks\Models\InboundWebhook;

it('accepts valid stripe webhook and dispatches job', function () {
    Queue::fake();

    $payload = json_encode([
        'id' => 'evt_123',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_123']],
    ]);

    $secret = config('inbound-webhooks.providers.stripe.secret');
    $signature = createStripeSignature($payload, $secret);

    $response = $this->postJson('/webhooks/stripe', json_decode($payload, true), [
        'Stripe-Signature' => $signature,
    ]);

    $response->assertStatus(200)
        ->assertJson(['status' => 'received'])
        ->assertJsonStructure(['status', 'id']);

    Queue::assertPushed(ProcessWebhook::class);

    expect(InboundWebhook::count())->toBe(1);

    $webhook = InboundWebhook::first();
    expect($webhook->provider)->toBe('stripe')
        ->and($webhook->event_type)->toBe('payment_intent.succeeded')
        ->and($webhook->external_id)->toBe('evt_123')
        ->and($webhook->status)->toBe(WebhookStatus::Pending);
});

it('accepts valid github webhook', function () {
    Queue::fake();

    $payload = json_encode([
        'action' => 'opened',
        'repository' => ['id' => 123, 'name' => 'test-repo'],
    ]);

    $secret = config('inbound-webhooks.providers.github.secret');
    $signature = createGitHubSignature($payload, $secret);

    $response = $this->postJson('/webhooks/github', json_decode($payload, true), [
        'X-Hub-Signature-256' => $signature,
        'X-GitHub-Event' => 'pull_request',
        'X-GitHub-Delivery' => 'uuid-123',
    ]);

    $response->assertStatus(200);

    $webhook = InboundWebhook::first();
    expect($webhook->provider)->toBe('github')
        ->and($webhook->event_type)->toBe('pull_request.opened')
        ->and($webhook->external_id)->toBe('uuid-123');
});

it('handles slack url verification challenge', function () {
    $payload = json_encode([
        'type' => 'url_verification',
        'challenge' => 'test_challenge_token',
    ]);

    $secret = config('inbound-webhooks.providers.slack.signing_secret');
    $timestamp = time();
    $signature = createSlackSignature($payload, $secret, $timestamp);

    $response = $this->postJson('/webhooks/slack', json_decode($payload, true), [
        'X-Slack-Signature' => $signature,
        'X-Slack-Request-Timestamp' => $timestamp,
    ]);

    $response->assertStatus(200)
        ->assertJson(['challenge' => 'test_challenge_token']);

    expect(InboundWebhook::count())->toBe(0);
});

it('accepts valid slack event callback', function () {
    Queue::fake();

    $payload = json_encode([
        'type' => 'event_callback',
        'event_id' => 'Ev123',
        'event' => ['type' => 'message'],
    ]);

    $secret = config('inbound-webhooks.providers.slack.signing_secret');
    $timestamp = time();
    $signature = createSlackSignature($payload, $secret, $timestamp);

    $response = $this->postJson('/webhooks/slack', json_decode($payload, true), [
        'X-Slack-Signature' => $signature,
        'X-Slack-Request-Timestamp' => $timestamp,
    ]);

    $response->assertStatus(200);

    $webhook = InboundWebhook::first();
    expect($webhook->provider)->toBe('slack')
        ->and($webhook->event_type)->toBe('message')
        ->and($webhook->external_id)->toBe('Ev123');
});

it('returns 401 for invalid signature', function () {
    $payload = json_encode(['id' => 'evt_123', 'type' => 'test']);

    $response = $this->postJson('/webhooks/stripe', json_decode($payload, true), [
        'Stripe-Signature' => 't=123,v1=invalid_signature',
    ]);

    $response->assertStatus(401)
        ->assertJson(['error' => 'Invalid signature']);

    expect(InboundWebhook::count())->toBe(0);
});

it('returns 401 when signature header is missing', function () {
    $response = $this->postJson('/webhooks/stripe', [
        'id' => 'evt_123',
        'type' => 'test',
    ]);

    $response->assertStatus(401)
        ->assertJson(['error' => 'Invalid signature']);
});

it('returns 404 for unknown provider', function () {
    $response = $this->postJson('/webhooks/unknown', [
        'test' => 'data',
    ]);

    $response->assertStatus(404)
        ->assertJson(['error' => 'Unknown provider']);
});

it('stores headers based on driver configuration', function () {
    Queue::fake();

    $payload = json_encode(['id' => 'evt_123', 'type' => 'test']);
    $secret = config('inbound-webhooks.providers.stripe.secret');
    $signature = createStripeSignature($payload, $secret);

    $this->postJson('/webhooks/stripe', json_decode($payload, true), [
        'Stripe-Signature' => $signature,
        'User-Agent' => 'Stripe/1.0',
        'Content-Type' => 'application/json',
    ]);

    $webhook = InboundWebhook::first();

    expect($webhook->headers)->toBeArray()
        ->and($webhook->headers)->toHaveKey('Stripe-Signature');
});

it('stores payload when configured', function () {
    Queue::fake();
    config(['inbound-webhooks.storage.store_payload' => true]);

    $payload = json_encode([
        'id' => 'evt_123',
        'type' => 'test',
        'data' => ['key' => 'value'],
    ]);
    $secret = config('inbound-webhooks.providers.stripe.secret');
    $signature = createStripeSignature($payload, $secret);

    $this->postJson('/webhooks/stripe', json_decode($payload, true), [
        'Stripe-Signature' => $signature,
    ]);

    $webhook = InboundWebhook::first();

    expect($webhook->payload)->toBeArray()
        ->and($webhook->payload['data'])->toBe(['key' => 'value']);
});

it('does not store payload when disabled', function () {
    Queue::fake();
    config(['inbound-webhooks.storage.store_payload' => false]);

    $payload = json_encode(['id' => 'evt_123', 'type' => 'test']);
    $secret = config('inbound-webhooks.providers.stripe.secret');
    $signature = createStripeSignature($payload, $secret);

    $this->postJson('/webhooks/stripe', json_decode($payload, true), [
        'Stripe-Signature' => $signature,
    ]);

    $webhook = InboundWebhook::first();

    expect($webhook->payload)->toBeNull();
});

it('returns uuid in response', function () {
    Queue::fake();

    $payload = json_encode(['id' => 'evt_123', 'type' => 'test']);
    $secret = config('inbound-webhooks.providers.stripe.secret');
    $signature = createStripeSignature($payload, $secret);

    $response = $this->postJson('/webhooks/stripe', json_decode($payload, true), [
        'Stripe-Signature' => $signature,
    ]);

    $webhook = InboundWebhook::first();

    $response->assertJson(['id' => $webhook->uuid]);
});
