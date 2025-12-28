<?php

use Illuminate\Http\Request;
use Vherbaut\InboundWebhooks\Drivers\SlackDriver;
use Vherbaut\InboundWebhooks\Exceptions\InvalidSignatureException;

beforeEach(function () {
    $this->secret = 'slack_test_secret';
    $this->driver = new SlackDriver([
        'signing_secret' => $this->secret,
        'tolerance' => 300,
    ]);
});

it('validates correct signature', function () {
    $payload = json_encode(['type' => 'event_callback', 'event' => ['type' => 'message']]);
    $timestamp = (string) time();
    $signature = createSlackSignature($payload, $this->secret, (int) $timestamp);

    $request = Request::create('/webhooks/slack', 'POST', [], [], [], [
        'HTTP_X_SLACK_SIGNATURE' => $signature,
        'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $timestamp,
    ], $payload);

    $this->driver->validateSignature($request);

    expect(true)->toBeTrue();
});

it('throws exception when signature headers are missing', function () {
    $request = Request::create('/webhooks/slack', 'POST', [], [], [], [], '{}');

    expect(fn () => $this->driver->validateSignature($request))
        ->toThrow(InvalidSignatureException::class, 'Missing Slack signature headers');
});

it('throws exception when signing secret is not configured', function () {
    $driver = new SlackDriver(['signing_secret' => null]);
    $request = Request::create('/webhooks/slack', 'POST', [], [], [], [
        'HTTP_X_SLACK_SIGNATURE' => 'v0=abc',
        'HTTP_X_SLACK_REQUEST_TIMESTAMP' => time(),
    ], '{}');

    expect(fn () => $driver->validateSignature($request))
        ->toThrow(InvalidSignatureException::class, 'Slack signing secret not configured');
});

it('throws exception when timestamp is outside tolerance', function () {
    $payload = '{}';
    $oldTimestamp = time() - 600;
    $signature = createSlackSignature($payload, $this->secret, $oldTimestamp);

    $request = Request::create('/webhooks/slack', 'POST', [], [], [], [
        'HTTP_X_SLACK_SIGNATURE' => $signature,
        'HTTP_X_SLACK_REQUEST_TIMESTAMP' => (string) $oldTimestamp,
    ], $payload);

    expect(fn () => $this->driver->validateSignature($request))
        ->toThrow(InvalidSignatureException::class, 'Slack request timestamp is outside tolerance');
});

it('throws exception for invalid signature format', function () {
    $request = Request::create('/webhooks/slack', 'POST', [], [], [], [
        'HTTP_X_SLACK_SIGNATURE' => 'invalid_format',
        'HTTP_X_SLACK_REQUEST_TIMESTAMP' => (string) time(),
    ], '{}');

    expect(fn () => $this->driver->validateSignature($request))
        ->toThrow(InvalidSignatureException::class, 'Invalid Slack signature format');
});

it('throws exception for invalid signature', function () {
    $payload = '{}';
    $timestamp = time();
    $signature = createSlackSignature($payload, 'wrong_secret', $timestamp);

    $request = Request::create('/webhooks/slack', 'POST', [], [], [], [
        'HTTP_X_SLACK_SIGNATURE' => $signature,
        'HTTP_X_SLACK_REQUEST_TIMESTAMP' => (string) $timestamp,
    ], $payload);

    expect(fn () => $this->driver->validateSignature($request))
        ->toThrow(InvalidSignatureException::class, 'Invalid Slack signature');
});

it('returns url_verification for challenge requests', function () {
    $request = Request::create('/webhooks/slack', 'POST', [
        'type' => 'url_verification',
        'challenge' => 'test_challenge',
    ]);

    expect($this->driver->getEventType($request))->toBe('url_verification');
});

it('extracts event type from event callback', function () {
    $request = Request::create('/webhooks/slack', 'POST', [
        'type' => 'event_callback',
        'event' => ['type' => 'message'],
    ]);

    expect($this->driver->getEventType($request))->toBe('message');
});

it('extracts type from interactive component payload', function () {
    $payload = json_encode(['type' => 'block_actions', 'action' => 'button_click']);

    $request = Request::create('/webhooks/slack', 'POST', [
        'payload' => $payload,
    ]);

    expect($this->driver->getEventType($request))->toBe('block_actions');
});

it('returns type for other requests', function () {
    $request = Request::create('/webhooks/slack', 'POST', [
        'type' => 'app_rate_limited',
    ]);

    expect($this->driver->getEventType($request))->toBe('app_rate_limited');
});

it('extracts event_id from payload', function () {
    $request = Request::create('/webhooks/slack', 'POST', [
        'event_id' => 'Ev1234567890',
    ]);

    expect($this->driver->getExternalId($request))->toBe('Ev1234567890');
});

it('extracts event.event_ts as fallback', function () {
    $request = Request::create('/webhooks/slack', 'POST', [
        'event' => ['event_ts' => '1234567890.123456'],
    ]);

    expect($this->driver->getExternalId($request))->toBe('1234567890.123456');
});

it('returns null when no id is present', function () {
    $request = Request::create('/webhooks/slack', 'POST', []);

    expect($this->driver->getExternalId($request))->toBeNull();
});

it('returns all data for regular requests', function () {
    $request = Request::create('/webhooks/slack', 'POST', [
        'type' => 'event_callback',
        'event' => ['type' => 'message'],
    ]);

    $payload = $this->driver->getPayload($request);

    expect($payload)->toBeArray()
        ->and($payload['type'])->toBe('event_callback');
});

it('decodes JSON payload from interactive components', function () {
    $jsonPayload = json_encode(['type' => 'block_actions', 'user' => ['id' => 'U123']]);

    $request = Request::create('/webhooks/slack', 'POST', [
        'payload' => $jsonPayload,
    ]);

    $payload = $this->driver->getPayload($request);

    expect($payload)->toBeArray()
        ->and($payload['type'])->toBe('block_actions');
});

it('returns relevant Slack headers', function () {
    $request = Request::create('/webhooks/slack', 'POST', [], [], [], [
        'HTTP_X_SLACK_SIGNATURE' => 'v0=abc',
        'HTTP_X_SLACK_REQUEST_TIMESTAMP' => '1234567890',
        'HTTP_CONTENT_TYPE' => 'application/json',
    ]);

    $headers = $this->driver->getStorableHeaders($request);

    expect($headers)->toHaveKey('X-Slack-Signature')
        ->and($headers)->toHaveKey('X-Slack-Request-Timestamp');
});
