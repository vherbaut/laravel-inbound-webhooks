<?php

use Illuminate\Http\Request;
use Vherbaut\InboundWebhooks\Drivers\HmacDriver;
use Vherbaut\InboundWebhooks\Exceptions\InvalidSignatureException;

beforeEach(function () {
    $this->secret = 'custom_test_secret';
    $this->driver = new HmacDriver([
        'secret' => $this->secret,
        'algorithm' => 'sha256',
        'header' => 'X-Custom-Signature',
        'prefix' => 'sha256=',
        'event_key' => 'event',
        'id_key' => 'id',
    ]);
});

it('validates correct signature with prefix', function () {
    $payload = json_encode(['event' => 'test', 'id' => '123']);
    $signature = 'sha256=' . hash_hmac('sha256', $payload, $this->secret);

    $request = Request::create('/webhooks/custom', 'POST', [], [], [], [
        'HTTP_X_CUSTOM_SIGNATURE' => $signature,
    ], $payload);

    $this->driver->validateSignature($request);

    expect(true)->toBeTrue();
});

it('validates correct signature without prefix', function () {
    $driver = new HmacDriver([
        'secret' => $this->secret,
        'algorithm' => 'sha256',
        'header' => 'X-Signature',
    ]);

    $payload = json_encode(['data' => 'test']);
    $signature = hash_hmac('sha256', $payload, $this->secret);

    $request = Request::create('/webhooks/custom', 'POST', [], [], [], [
        'HTTP_X_SIGNATURE' => $signature,
    ], $payload);

    $driver->validateSignature($request);

    expect(true)->toBeTrue();
});

it('throws exception when signature header is missing', function () {
    $request = Request::create('/webhooks/custom', 'POST', [], [], [], [], '{}');

    expect(fn () => $this->driver->validateSignature($request))
        ->toThrow(InvalidSignatureException::class, 'Missing X-Custom-Signature header');
});

it('throws exception when secret is not configured', function () {
    $driver = new HmacDriver(['secret' => null, 'header' => 'X-Sig']);
    $request = Request::create('/webhooks/custom', 'POST', [], [], [], [
        'HTTP_X_SIG' => 'abc',
    ], '{}');

    expect(fn () => $driver->validateSignature($request))
        ->toThrow(InvalidSignatureException::class, 'Webhook secret not configured');
});

it('throws exception for invalid signature', function () {
    $request = Request::create('/webhooks/custom', 'POST', [], [], [], [
        'HTTP_X_CUSTOM_SIGNATURE' => 'sha256=invalid_signature',
    ], '{}');

    expect(fn () => $this->driver->validateSignature($request))
        ->toThrow(InvalidSignatureException::class, 'Invalid webhook signature');
});

it('supports different algorithms', function () {
    $driver = new HmacDriver([
        'secret' => $this->secret,
        'algorithm' => 'sha512',
        'header' => 'X-Signature',
    ]);

    $payload = json_encode(['data' => 'test']);
    $signature = hash_hmac('sha512', $payload, $this->secret);

    $request = Request::create('/webhooks/custom', 'POST', [], [], [], [
        'HTTP_X_SIGNATURE' => $signature,
    ], $payload);

    $driver->validateSignature($request);

    expect(true)->toBeTrue();
});

it('extracts event type from configured key', function () {
    $request = Request::create('/webhooks/custom', 'POST', [
        'event' => 'user.created',
    ]);

    expect($this->driver->getEventType($request))->toBe('user.created');
});

it('extracts event type from header when configured', function () {
    $driver = new HmacDriver([
        'secret' => $this->secret,
        'header' => 'X-Signature',
        'event_header' => 'X-Event-Type',
    ]);

    $request = Request::create('/webhooks/custom', 'POST', [], [], [], [
        'HTTP_X_EVENT_TYPE' => 'order.completed',
    ]);

    expect($driver->getEventType($request))->toBe('order.completed');
});

it('uses custom event_key', function () {
    $driver = new HmacDriver([
        'secret' => $this->secret,
        'header' => 'X-Signature',
        'event_key' => 'type',
    ]);

    $request = Request::create('/webhooks/custom', 'POST', [
        'type' => 'custom.event',
    ]);

    expect($driver->getEventType($request))->toBe('custom.event');
});

it('returns null when event is not present', function () {
    $request = Request::create('/webhooks/custom', 'POST', []);

    expect($this->driver->getEventType($request))->toBeNull();
});

it('extracts id from configured key', function () {
    $request = Request::create('/webhooks/custom', 'POST', [
        'id' => 'evt_123456',
    ]);

    expect($this->driver->getExternalId($request))->toBe('evt_123456');
});

it('extracts id from header when configured', function () {
    $driver = new HmacDriver([
        'secret' => $this->secret,
        'header' => 'X-Signature',
        'id_header' => 'X-Request-ID',
    ]);

    $request = Request::create('/webhooks/custom', 'POST', [], [], [], [
        'HTTP_X_REQUEST_ID' => 'req_789',
    ]);

    expect($driver->getExternalId($request))->toBe('req_789');
});

it('uses custom id_key', function () {
    $driver = new HmacDriver([
        'secret' => $this->secret,
        'header' => 'X-Signature',
        'id_key' => 'webhook_id',
    ]);

    $request = Request::create('/webhooks/custom', 'POST', [
        'webhook_id' => 'wh_abc',
    ]);

    expect($driver->getExternalId($request))->toBe('wh_abc');
});

it('returns null when id is not present', function () {
    $request = Request::create('/webhooks/custom', 'POST', []);

    expect($this->driver->getExternalId($request))->toBeNull();
});

it('returns configured signature header', function () {
    $request = Request::create('/webhooks/custom', 'POST', [], [], [], [
        'HTTP_X_CUSTOM_SIGNATURE' => 'sha256=abc',
        'HTTP_CONTENT_TYPE' => 'application/json',
    ]);

    $headers = $this->driver->getStorableHeaders($request);

    expect($headers)->toHaveKey('X-Custom-Signature')
        ->and($headers)->toHaveKey('Content-Type');
});

it('includes event header when configured', function () {
    $driver = new HmacDriver([
        'secret' => $this->secret,
        'header' => 'X-Signature',
        'event_header' => 'X-Event-Type',
    ]);

    $request = Request::create('/webhooks/custom', 'POST', [], [], [], [
        'HTTP_X_SIGNATURE' => 'abc',
        'HTTP_X_EVENT_TYPE' => 'test.event',
    ]);

    $headers = $driver->getStorableHeaders($request);

    expect($headers)->toHaveKey('X-Event-Type');
});

it('includes id header when configured', function () {
    $driver = new HmacDriver([
        'secret' => $this->secret,
        'header' => 'X-Signature',
        'id_header' => 'X-Request-ID',
    ]);

    $request = Request::create('/webhooks/custom', 'POST', [], [], [], [
        'HTTP_X_SIGNATURE' => 'abc',
        'HTTP_X_REQUEST_ID' => 'req_123',
    ]);

    $headers = $driver->getStorableHeaders($request);

    expect($headers)->toHaveKey('X-Request-ID');
});