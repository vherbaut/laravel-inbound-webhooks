<?php

use Illuminate\Http\Request;
use Vherbaut\InboundWebhooks\Drivers\StripeDriver;
use Vherbaut\InboundWebhooks\Exceptions\InvalidSignatureException;

beforeEach(function () {
    $this->secret = 'whsec_test_secret';
    $this->driver = new StripeDriver([
        'secret' => $this->secret,
        'tolerance' => 300,
    ]);
});

it('validates correct signature', function () {
    $payload = json_encode(['id' => 'evt_123', 'type' => 'payment_intent.succeeded']);
    $signature = createStripeSignature($payload, $this->secret);

    $request = Request::create('/webhooks/stripe', 'POST', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => $signature,
    ], $payload);

    $this->driver->validateSignature($request);

    expect(true)->toBeTrue();
});

it('throws exception when signature header is missing', function () {
    $request = Request::create('/webhooks/stripe', 'POST', [], [], [], [], '{}');

    expect(fn () => $this->driver->validateSignature($request))
        ->toThrow(InvalidSignatureException::class, 'Missing Stripe-Signature header');
});

it('throws exception when secret is not configured', function () {
    $driver = new StripeDriver(['secret' => null]);
    $request = Request::create('/webhooks/stripe', 'POST', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => 't=123,v1=abc',
    ], '{}');

    expect(fn () => $driver->validateSignature($request))
        ->toThrow(InvalidSignatureException::class, 'Stripe webhook secret not configured');
});

it('throws exception for invalid signature format', function () {
    $request = Request::create('/webhooks/stripe', 'POST', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => 'invalid_format',
    ], '{}');

    expect(fn () => $this->driver->validateSignature($request))
        ->toThrow(InvalidSignatureException::class, 'Invalid Stripe signature format');
});

it('throws exception when timestamp is outside tolerance', function () {
    $payload = '{}';
    $oldTimestamp = time() - 600;
    $signature = createStripeSignature($payload, $this->secret, $oldTimestamp);

    $request = Request::create('/webhooks/stripe', 'POST', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => $signature,
    ], $payload);

    expect(fn () => $this->driver->validateSignature($request))
        ->toThrow(InvalidSignatureException::class, 'Stripe webhook timestamp is outside tolerance');
});

it('throws exception for invalid signature', function () {
    $payload = '{}';
    $signature = createStripeSignature($payload, 'wrong_secret');

    $request = Request::create('/webhooks/stripe', 'POST', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => $signature,
    ], $payload);

    expect(fn () => $this->driver->validateSignature($request))
        ->toThrow(InvalidSignatureException::class, 'Invalid Stripe signature');
});

it('extracts event type from payload', function () {
    $request = Request::create('/webhooks/stripe', 'POST', [
        'type' => 'payment_intent.succeeded',
    ]);

    expect($this->driver->getEventType($request))->toBe('payment_intent.succeeded');
});

it('returns null when type is not present', function () {
    $request = Request::create('/webhooks/stripe', 'POST', []);

    expect($this->driver->getEventType($request))->toBeNull();
});

it('extracts external id from payload', function () {
    $request = Request::create('/webhooks/stripe', 'POST', [
        'id' => 'evt_1234567890',
    ]);

    expect($this->driver->getExternalId($request))->toBe('evt_1234567890');
});

it('returns null when id is not present', function () {
    $request = Request::create('/webhooks/stripe', 'POST', []);

    expect($this->driver->getExternalId($request))->toBeNull();
});

it('returns all request data as payload', function () {
    $request = Request::create('/webhooks/stripe', 'POST', [
        'id' => 'evt_123',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_123']],
    ]);

    $payload = $this->driver->getPayload($request);

    expect($payload)->toBeArray()
        ->and($payload['id'])->toBe('evt_123')
        ->and($payload['type'])->toBe('payment_intent.succeeded');
});

it('returns relevant headers including Stripe-Signature', function () {
    $request = Request::create('/webhooks/stripe', 'POST', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => 't=123,v1=abc',
        'HTTP_CONTENT_TYPE' => 'application/json',
        'HTTP_USER_AGENT' => 'Stripe/1.0',
    ]);

    $headers = $this->driver->getStorableHeaders($request);

    expect($headers)->toHaveKey('Stripe-Signature')
        ->and($headers)->toHaveKey('Content-Type')
        ->and($headers)->toHaveKey('User-Agent');
});
