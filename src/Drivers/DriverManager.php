<?php

namespace Vherbaut\InboundWebhooks\Drivers;

use Illuminate\Contracts\Foundation\Application;
use Vherbaut\InboundWebhooks\Exceptions\UnknownProviderException;

/**
 * Manager responsible for creating and caching webhook driver instances.
 *
 * This class handles driver resolution by provider name, supporting built-in
 * drivers (Stripe, GitHub, Slack, Twilio, HMAC), custom drivers registered
 * via the extend() method, and fully qualified class names.
 */
class DriverManager
{
    /**
     * The Laravel application instance.
     *
     * @var Application
     */
    protected Application $app;

    /**
     * Cache of resolved driver instances indexed by provider name.
     *
     * @var array<string, DriverInterface>
     */
    protected array $drivers = [];

    /**
     * Custom driver factory callbacks indexed by driver name.
     *
     * @var array<string, callable(array<string, mixed>): DriverInterface>
     */
    protected array $customDrivers = [];

    /**
     * Mapping of built-in driver names to their class names.
     *
     * @var array<string, class-string<DriverInterface>>
     */
    protected array $builtInDrivers = [
        'stripe' => StripeDriver::class,
        'github' => GitHubDriver::class,
        'slack' => SlackDriver::class,
        'twilio' => TwilioDriver::class,
        'hmac' => HmacDriver::class,
    ];

    /**
     * @param  Application  $app  The Laravel application instance
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get a driver instance for the given provider.
     *
     * Drivers are cached after first resolution, so subsequent calls
     * for the same provider return the same instance.
     *
     * @param  string  $provider  The provider name as configured in inbound-webhooks.providers
     *
     * @return DriverInterface The resolved driver instance
     *
     * @throws UnknownProviderException If the provider is not configured or driver not found
     */
    public function driver(string $provider): DriverInterface
    {
        if (isset($this->drivers[$provider])) {
            return $this->drivers[$provider];
        }

        $config = $this->getProviderConfig($provider);
        $driver = $this->createDriver($provider, $config);

        return $this->drivers[$provider] = $driver;
    }

    /**
     * Register a custom driver factory.
     *
     * The callback receives the provider configuration array and must
     * return a DriverInterface instance.
     *
     * @param  string  $name  The driver name to register
     * @param  callable(array<string, mixed>): DriverInterface  $callback  Factory callback
     *
     * @return self Fluent interface
     */
    public function extend(string $name, callable $callback): self
    {
        $this->customDrivers[$name] = $callback;

        return $this;
    }

    /**
     * Retrieve configuration for a provider.
     *
     * @param  string  $provider  The provider name
     *
     * @return array<string, mixed> The provider configuration
     *
     * @throws UnknownProviderException If the provider is not configured
     */
    protected function getProviderConfig(string $provider): array
    {
        /** @var array<string, mixed>|null $config */
        $config = config("inbound-webhooks.providers.{$provider}");

        if (empty($config)) {
            throw new UnknownProviderException("Provider [{$provider}] is not configured");
        }

        return $config;
    }

    /**
     * Create a driver instance based on configuration.
     *
     * Resolution order:
     * 1. Custom drivers registered via extend()
     * 2. Built-in drivers (stripe, github, slack, twilio, hmac)
     * 3. Fully qualified class names
     *
     * @param  string  $provider  The provider name
     * @param  array<string, mixed>  $config  The provider configuration
     *
     * @return DriverInterface The created driver instance
     *
     * @throws UnknownProviderException If no matching driver is found
     */
    protected function createDriver(string $provider, array $config): DriverInterface
    {
        /** @var string $driverName */
        $driverName = $config['driver'] ?? $provider;

        if (isset($this->customDrivers[$driverName])) {
            return call_user_func($this->customDrivers[$driverName], $config);
        }

        if (isset($this->builtInDrivers[$driverName])) {
            $driverClass = $this->builtInDrivers[$driverName];

            return new $driverClass($config);
        }

        if (class_exists($driverName)) {
            /** @var DriverInterface $driver */
            $driver = new $driverName($config);

            return $driver;
        }

        throw new UnknownProviderException("Driver [{$driverName}] for provider [{$provider}] not found");
    }

    /**
     * Check if a provider is configured.
     *
     * @param  string  $provider  The provider name to check
     *
     * @return bool True if the provider has configuration, false otherwise
     */
    public function hasProvider(string $provider): bool
    {
        return ! empty(config("inbound-webhooks.providers.{$provider}"));
    }

    /**
     * Get all configured provider names.
     *
     * @return array<int, string> List of configured provider names
     */
    public function getProviders(): array
    {
        /** @var array<string, mixed> $providers */
        $providers = config('inbound-webhooks.providers', []);

        return array_keys($providers);
    }
}
