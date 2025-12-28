<?php

namespace Vherbaut\InboundWebhooks;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Vherbaut\InboundWebhooks\Commands\PruneWebhooks;
use Vherbaut\InboundWebhooks\Commands\ReplayWebhook;
use Vherbaut\InboundWebhooks\Drivers\DriverManager;

/**
 * Service provider for the Laravel Inbound Webhooks package.
 *
 * This provider registers the DriverManager singleton, publishes configuration
 * and migrations, loads routes, and registers Artisan commands.
 *
 * @see https://github.com/vherbaut/laravel-inbound-webhooks
 */
class InboundWebhooksServiceProvider extends ServiceProvider
{
    /**
     * Register package services.
     *
     * Binds the DriverManager as a singleton and merges the default configuration.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/inbound-webhooks.php', 'inbound-webhooks');

        $this->app->singleton(DriverManager::class, function (Application $app) {
            return new DriverManager($app);
        });

        $this->app->alias(DriverManager::class, 'inbound-webhooks');
    }

    /**
     * Bootstrap package services.
     *
     * Registers publishable assets, routes, commands, and migrations.
     */
    public function boot(): void
    {
        $this->registerPublishables();
        $this->registerRoutes();
        $this->registerCommands();
        $this->registerMigrations();
    }

    /**
     * Register publishable configuration and migration files.
     *
     * Publish tags:
     * - `inbound-webhooks-config`: Configuration file
     * - `inbound-webhooks-migrations`: Database migrations
     */
    protected function registerPublishables(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/inbound-webhooks.php' => config_path('inbound-webhooks.php'),
            ], 'inbound-webhooks-config');

            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'inbound-webhooks-migrations');
        }
    }

    /**
     * Register the webhook routes.
     */
    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/webhooks.php');
    }

    /**
     * Register Artisan commands.
     *
     * Commands:
     * - `webhooks:replay`: Replay a previously received webhook
     * - `webhooks:prune`: Prune old webhook records
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ReplayWebhook::class,
                PruneWebhooks::class,
            ]);
        }
    }

    /**
     * Register package migrations.
     *
     * Migrations are automatically loaded without needing to publish.
     */
    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
