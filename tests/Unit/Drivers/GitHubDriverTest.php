<?php

use Illuminate\Http\Request;
use Vherbaut\InboundWebhooks\Drivers\GitHubDriver;
use Vherbaut\InboundWebhooks\Exceptions\InvalidSignatureException;

beforeEach(function () {
    $this->secret = 'github_test_secret';
    $this->driver = new GitHubDriver([
        'secret' => $this->secret,
    ]);
});

it('validates correct signature', function () {
    $payload = json_encode(['action' => 'opened', 'repository' => ['id' => 123]]);
    $signature = createGitHubSignature($payload, $this->secret);

    $request = Request::create('/webhooks/github', 'POST', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $payload);

    $this->driver->validateSignature($request);

    expect(true)->toBeTrue();
});

it('throws exception when signature header is missing', function () {
    $request = Request::create('/webhooks/github', 'POST', [], [], [], [], '{}');

    expect(fn () => $this->driver->validateSignature($request))
        ->toThrow(InvalidSignatureException::class, 'Missing X-Hub-Signature-256 header');
});

it('throws exception when secret is not configured', function () {
    $driver = new GitHubDriver(['secret' => null]);
    $request = Request::create('/webhooks/github', 'POST', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256=abc',
    ], '{}');

    expect(fn () => $driver->validateSignature($request))
        ->toThrow(InvalidSignatureException::class, 'GitHub webhook secret not configured');
});

it('throws exception for invalid signature format', function () {
    $request = Request::create('/webhooks/github', 'POST', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => 'invalid_format',
    ], '{}');

    expect(fn () => $this->driver->validateSignature($request))
        ->toThrow(InvalidSignatureException::class, 'Invalid GitHub signature format');
});

it('throws exception for invalid signature', function () {
    $payload = '{}';
    $signature = createGitHubSignature($payload, 'wrong_secret');

    $request = Request::create('/webhooks/github', 'POST', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $payload);

    expect(fn () => $this->driver->validateSignature($request))
        ->toThrow(InvalidSignatureException::class, 'Invalid GitHub signature');
});

it('returns event header only when no action', function () {
    $request = Request::create('/webhooks/github', 'POST', [], [], [], [
        'HTTP_X_GITHUB_EVENT' => 'ping',
    ]);

    expect($this->driver->getEventType($request))->toBe('ping');
});

it('combines event header with action', function () {
    $request = Request::create('/webhooks/github', 'POST', [
        'action' => 'opened',
    ], [], [], [
        'HTTP_X_GITHUB_EVENT' => 'pull_request',
    ]);

    expect($this->driver->getEventType($request))->toBe('pull_request.opened');
});

it('returns null when event header is missing', function () {
    $request = Request::create('/webhooks/github', 'POST', []);

    expect($this->driver->getEventType($request))->toBeNull();
});

it('extracts delivery id from header', function () {
    $request = Request::create('/webhooks/github', 'POST', [], [], [], [
        'HTTP_X_GITHUB_DELIVERY' => '72d3162e-cc78-11e3-81ab-4c9367dc0958',
    ]);

    expect($this->driver->getExternalId($request))->toBe('72d3162e-cc78-11e3-81ab-4c9367dc0958');
});

it('returns null when delivery header is missing', function () {
    $request = Request::create('/webhooks/github', 'POST', []);

    expect($this->driver->getExternalId($request))->toBeNull();
});

it('returns relevant GitHub headers', function () {
    $request = Request::create('/webhooks/github', 'POST', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256=abc',
        'HTTP_X_GITHUB_EVENT' => 'push',
        'HTTP_X_GITHUB_DELIVERY' => 'uuid-123',
        'HTTP_X_GITHUB_HOOK_ID' => '12345',
        'HTTP_CONTENT_TYPE' => 'application/json',
    ]);

    $headers = $this->driver->getStorableHeaders($request);

    expect($headers)->toHaveKey('X-Hub-Signature-256')
        ->and($headers)->toHaveKey('X-GitHub-Event')
        ->and($headers)->toHaveKey('X-GitHub-Delivery')
        ->and($headers)->toHaveKey('X-GitHub-Hook-ID');
});
