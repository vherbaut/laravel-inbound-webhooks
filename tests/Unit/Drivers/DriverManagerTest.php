<?php

use Vherbaut\InboundWebhooks\Drivers\DriverInterface;
use Vherbaut\InboundWebhooks\Drivers\DriverManager;
use Vherbaut\InboundWebhooks\Drivers\GitHubDriver;
use Vherbaut\InboundWebhooks\Drivers\HmacDriver;
use Vherbaut\InboundWebhooks\Drivers\SlackDriver;
use Vherbaut\InboundWebhooks\Drivers\StripeDriver;
use Vherbaut\InboundWebhooks\Drivers\TwilioDriver;
use Vherbaut\InboundWebhooks\Exceptions\UnknownProviderException;

beforeEach(function () {
    $this->manager = app(DriverManager::class);
});

it('returns StripeDriver for stripe provider', function () {
    $driver = $this->manager->driver('stripe');

    expect($driver)->toBeInstanceOf(StripeDriver::class)
        ->and($driver)->toBeInstanceOf(DriverInterface::class);
});

it('returns GitHubDriver for github provider', function () {
    $driver = $this->manager->driver('github');

    expect($driver)->toBeInstanceOf(GitHubDriver::class);
});

it('returns SlackDriver for slack provider', function () {
    $driver = $this->manager->driver('slack');

    expect($driver)->toBeInstanceOf(SlackDriver::class);
});

it('returns TwilioDriver for twilio provider', function () {
    $driver = $this->manager->driver('twilio');

    expect($driver)->toBeInstanceOf(TwilioDriver::class);
});

it('returns HmacDriver for custom provider with hmac driver', function () {
    $driver = $this->manager->driver('custom');

    expect($driver)->toBeInstanceOf(HmacDriver::class);
});

it('caches driver instances', function () {
    $driver1 = $this->manager->driver('stripe');
    $driver2 = $this->manager->driver('stripe');

    expect($driver1)->toBe($driver2);
});

it('throws exception for unconfigured provider', function () {
    expect(fn () => $this->manager->driver('unknown'))
        ->toThrow(UnknownProviderException::class, 'Provider [unknown] is not configured');
});

it('registers custom driver', function () {
    config(['inbound-webhooks.providers.custom_provider' => [
        'driver' => 'my_custom',
        'secret' => 'test',
    ]]);

    $this->manager->extend('my_custom', function ($config) {
        return new class($config) implements DriverInterface {
            public function __construct(public array $config) {}
            public function validateSignature(\Illuminate\Http\Request $request): void {}
            public function getEventType(\Illuminate\Http\Request $request): ?string { return 'custom'; }
            public function getExternalId(\Illuminate\Http\Request $request): ?string { return null; }
            public function getPayload(\Illuminate\Http\Request $request): array { return []; }
            public function getStorableHeaders(\Illuminate\Http\Request $request): array { return []; }
        };
    });

    $driver = $this->manager->driver('custom_provider');

    expect($driver)->toBeInstanceOf(DriverInterface::class)
        ->and($driver->config['secret'])->toBe('test');
});

it('returns self for method chaining when extending', function () {
    $result = $this->manager->extend('test', fn ($config) => null);

    expect($result)->toBe($this->manager);
});

it('returns true for configured providers', function () {
    expect($this->manager->hasProvider('stripe'))->toBeTrue()
        ->and($this->manager->hasProvider('github'))->toBeTrue()
        ->and($this->manager->hasProvider('slack'))->toBeTrue()
        ->and($this->manager->hasProvider('twilio'))->toBeTrue();
});

it('returns false for unconfigured providers', function () {
    expect($this->manager->hasProvider('unknown'))->toBeFalse()
        ->and($this->manager->hasProvider('paypal'))->toBeFalse();
});

it('returns all configured provider names', function () {
    $providers = $this->manager->getProviders();

    expect($providers)->toBeArray()
        ->and($providers)->toContain('stripe')
        ->and($providers)->toContain('github')
        ->and($providers)->toContain('slack')
        ->and($providers)->toContain('twilio')
        ->and($providers)->toContain('custom');
});
