<?php

namespace Vherbaut\InboundWebhooks\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use Vherbaut\InboundWebhooks\InboundWebhooksServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            InboundWebhooksServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('inbound-webhooks.providers', [
            'stripe' => [
                'driver' => 'stripe',
                'secret' => 'whsec_test_secret',
                'tolerance' => 300,
            ],
            'github' => [
                'driver' => 'github',
                'secret' => 'github_test_secret',
            ],
            'slack' => [
                'driver' => 'slack',
                'signing_secret' => 'slack_test_secret',
                'tolerance' => 300,
            ],
            'twilio' => [
                'driver' => 'twilio',
                'auth_token' => 'twilio_test_token',
            ],
            'custom' => [
                'driver' => 'hmac',
                'secret' => 'custom_test_secret',
                'algorithm' => 'sha256',
                'header' => 'X-Custom-Signature',
            ],
        ]);

        $app['config']->set('inbound-webhooks.storage.retention_days', 30);
        $app['config']->set('inbound-webhooks.queue.connection', 'sync');
    }

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
