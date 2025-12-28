<?php

use Illuminate\Http\Request;
use Vherbaut\InboundWebhooks\Drivers\TwilioDriver;
use Vherbaut\InboundWebhooks\Exceptions\InvalidSignatureException;

beforeEach(function () {
    $this->authToken = 'twilio_test_token';
    $this->driver = new TwilioDriver([
        'auth_token' => $this->authToken,
    ]);
});

it('validates correct signature', function () {
    $url = 'https://example.com/webhooks/twilio';
    $params = [
        'AccountSid' => 'AC123',
        'MessageSid' => 'SM123',
        'Body' => 'Hello',
    ];
    $signature = createTwilioSignature($url, $params, $this->authToken);

    $request = Request::create($url, 'POST', $params, [], [], [
        'HTTP_X_TWILIO_SIGNATURE' => $signature,
    ]);

    $this->driver->validateSignature($request);

    expect(true)->toBeTrue();
});

it('throws exception when signature header is missing', function () {
    $request = Request::create('/webhooks/twilio', 'POST', []);

    expect(fn () => $this->driver->validateSignature($request))
        ->toThrow(InvalidSignatureException::class, 'Missing X-Twilio-Signature header');
});

it('throws exception when auth token is not configured', function () {
    $driver = new TwilioDriver(['auth_token' => null]);
    $request = Request::create('/webhooks/twilio', 'POST', [], [], [], [
        'HTTP_X_TWILIO_SIGNATURE' => 'abc123',
    ]);

    expect(fn () => $driver->validateSignature($request))
        ->toThrow(InvalidSignatureException::class, 'Twilio auth token not configured');
});

it('throws exception for invalid signature', function () {
    $request = Request::create('/webhooks/twilio', 'POST', [
        'MessageSid' => 'SM123',
    ], [], [], [
        'HTTP_X_TWILIO_SIGNATURE' => 'invalid_signature',
    ]);

    expect(fn () => $this->driver->validateSignature($request))
        ->toThrow(InvalidSignatureException::class, 'Invalid Twilio signature');
});

it('returns message event type with status', function () {
    $request = Request::create('/webhooks/twilio', 'POST', [
        'MessageSid' => 'SM123',
        'MessageStatus' => 'delivered',
    ]);

    expect($this->driver->getEventType($request))->toBe('message.delivered');
});

it('returns message.received when MessageSid present but no status', function () {
    $request = Request::create('/webhooks/twilio', 'POST', [
        'MessageSid' => 'SM123',
    ]);

    expect($this->driver->getEventType($request))->toBe('message.received');
});

it('returns message event type with SmsStatus fallback', function () {
    $request = Request::create('/webhooks/twilio', 'POST', [
        'MessageSid' => 'SM123',
        'SmsStatus' => 'sent',
    ]);

    expect($this->driver->getEventType($request))->toBe('message.sent');
});

it('returns call event type with status', function () {
    $request = Request::create('/webhooks/twilio', 'POST', [
        'CallSid' => 'CA123',
        'CallStatus' => 'completed',
    ]);

    expect($this->driver->getEventType($request))->toBe('call.completed');
});

it('returns call.incoming when CallSid present but no status', function () {
    $request = Request::create('/webhooks/twilio', 'POST', [
        'CallSid' => 'CA123',
    ]);

    expect($this->driver->getEventType($request))->toBe('call.incoming');
});

it('returns status_callback for status callback requests', function () {
    $request = Request::create('/webhooks/twilio', 'POST', [
        'StatusCallback' => 'https://example.com/callback',
    ]);

    expect($this->driver->getEventType($request))->toBe('status_callback');
});

it('returns null for unknown request types', function () {
    $request = Request::create('/webhooks/twilio', 'POST', [
        'UnknownField' => 'value',
    ]);

    expect($this->driver->getEventType($request))->toBeNull();
});

it('extracts MessageSid', function () {
    $request = Request::create('/webhooks/twilio', 'POST', [
        'MessageSid' => 'SM1234567890',
    ]);

    expect($this->driver->getExternalId($request))->toBe('SM1234567890');
});

it('extracts CallSid', function () {
    $request = Request::create('/webhooks/twilio', 'POST', [
        'CallSid' => 'CA1234567890',
    ]);

    expect($this->driver->getExternalId($request))->toBe('CA1234567890');
});

it('extracts SmsSid as fallback', function () {
    $request = Request::create('/webhooks/twilio', 'POST', [
        'SmsSid' => 'SM1234567890',
    ]);

    expect($this->driver->getExternalId($request))->toBe('SM1234567890');
});

it('prioritizes MessageSid over CallSid', function () {
    $request = Request::create('/webhooks/twilio', 'POST', [
        'MessageSid' => 'SM123',
        'CallSid' => 'CA456',
    ]);

    expect($this->driver->getExternalId($request))->toBe('SM123');
});

it('returns null when no id is present', function () {
    $request = Request::create('/webhooks/twilio', 'POST', []);

    expect($this->driver->getExternalId($request))->toBeNull();
});

it('returns relevant Twilio headers', function () {
    $request = Request::create('/webhooks/twilio', 'POST', [], [], [], [
        'HTTP_X_TWILIO_SIGNATURE' => 'abc123',
        'HTTP_CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        'HTTP_USER_AGENT' => 'TwilioProxy',
    ]);

    $headers = $this->driver->getStorableHeaders($request);

    expect($headers)->toHaveKey('X-Twilio-Signature')
        ->and($headers)->toHaveKey('Content-Type')
        ->and($headers)->toHaveKey('User-Agent');
});
