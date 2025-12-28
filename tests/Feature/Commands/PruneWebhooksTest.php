<?php

use Vherbaut\InboundWebhooks\Enums\WebhookStatus;
use Vherbaut\InboundWebhooks\Models\InboundWebhook;

beforeEach(function () {
    config(['inbound-webhooks.storage.retention_days' => 30]);
});

/**
 * Helper function to create an old webhook.
 *
 * @param  array  $attributes
 * @param  int  $daysOld
 * @return InboundWebhook
 */
function createOldWebhook(array $attributes, int $daysOld): InboundWebhook
{
    $webhook = InboundWebhook::create($attributes);
    $webhook->created_at = now()->subDays($daysOld);
    $webhook->save();

    return $webhook;
}

it('prunes webhooks older than configured days', function () {
    createOldWebhook([
        'provider' => 'stripe',
        'status' => WebhookStatus::Processed,
    ], 40);

    InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Processed,
    ]);

    $this->artisan('webhooks:prune')
        ->expectsOutputToContain('Found 1 webhooks older than 30 days.')
        ->expectsConfirmation('Do you want to delete 1 webhook records?', 'yes')
        ->expectsOutputToContain('Deleted 1 webhook records.')
        ->assertExitCode(0);

    expect(InboundWebhook::count())->toBe(1);
});

it('respects --days option', function () {
    createOldWebhook([
        'provider' => 'stripe',
        'status' => WebhookStatus::Processed,
    ], 15);

    InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Processed,
    ]);

    $this->artisan('webhooks:prune --days=10')
        ->expectsOutputToContain('Found 1 webhooks older than 10 days.')
        ->expectsConfirmation('Do you want to delete 1 webhook records?', 'yes')
        ->assertExitCode(0);

    expect(InboundWebhook::count())->toBe(1);
});

it('filters by status', function () {
    createOldWebhook([
        'provider' => 'stripe',
        'status' => WebhookStatus::Failed,
    ], 40);

    createOldWebhook([
        'provider' => 'stripe',
        'status' => WebhookStatus::Processed,
    ], 40);

    $this->artisan('webhooks:prune --status=failed')
        ->expectsOutputToContain('Found 1 webhooks older than 30 days.')
        ->expectsConfirmation('Do you want to delete 1 webhook records?', 'yes')
        ->assertExitCode(0);

    expect(InboundWebhook::count())->toBe(1)
        ->and(InboundWebhook::first()->status)->toBe(WebhookStatus::Processed);
});

it('filters by provider', function () {
    createOldWebhook([
        'provider' => 'stripe',
        'status' => WebhookStatus::Processed,
    ], 40);

    createOldWebhook([
        'provider' => 'github',
        'status' => WebhookStatus::Processed,
    ], 40);

    $this->artisan('webhooks:prune --provider=stripe')
        ->expectsOutputToContain('Found 1 webhooks older than 30 days.')
        ->expectsConfirmation('Do you want to delete 1 webhook records?', 'yes')
        ->assertExitCode(0);

    expect(InboundWebhook::count())->toBe(1)
        ->and(InboundWebhook::first()->provider)->toBe('github');
});

it('supports dry-run mode', function () {
    createOldWebhook([
        'provider' => 'stripe',
        'status' => WebhookStatus::Processed,
    ], 40);

    $this->artisan('webhooks:prune --dry-run')
        ->expectsOutputToContain('Found 1 webhooks older than 30 days.')
        ->expectsOutputToContain('Dry run - no records will be deleted.')
        ->assertExitCode(0);

    expect(InboundWebhook::count())->toBe(1);
});

it('shows nothing to prune when no old webhooks exist', function () {
    InboundWebhook::create([
        'provider' => 'stripe',
        'status' => WebhookStatus::Processed,
    ]);

    $this->artisan('webhooks:prune')
        ->expectsOutputToContain('No webhooks to prune.')
        ->assertExitCode(0);

    expect(InboundWebhook::count())->toBe(1);
});

it('handles retention set to forever', function () {
    config(['inbound-webhooks.storage.retention_days' => null]);

    createOldWebhook([
        'provider' => 'stripe',
        'status' => WebhookStatus::Processed,
    ], 365);

    $this->artisan('webhooks:prune')
        ->expectsOutputToContain('Retention is set to forever. Nothing to prune.')
        ->assertExitCode(0);

    expect(InboundWebhook::count())->toBe(1);
});

it('aborts when user declines confirmation', function () {
    createOldWebhook([
        'provider' => 'stripe',
        'status' => WebhookStatus::Processed,
    ], 40);

    $this->artisan('webhooks:prune')
        ->expectsConfirmation('Do you want to delete 1 webhook records?', 'no')
        ->expectsOutputToContain('Aborted.')
        ->assertExitCode(0);

    expect(InboundWebhook::count())->toBe(1);
});

it('returns failure for invalid status', function () {
    $this->artisan('webhooks:prune --status=invalid')
        ->expectsOutputToContain("Invalid status 'invalid'. Valid values: pending, processing, processed, failed")
        ->assertExitCode(1);
});

it('combines multiple filters', function () {
    createOldWebhook([
        'provider' => 'stripe',
        'status' => WebhookStatus::Failed,
    ], 40);

    createOldWebhook([
        'provider' => 'stripe',
        'status' => WebhookStatus::Processed,
    ], 40);

    createOldWebhook([
        'provider' => 'github',
        'status' => WebhookStatus::Failed,
    ], 40);

    $this->artisan('webhooks:prune --provider=stripe --status=failed')
        ->expectsOutputToContain('Found 1 webhooks older than 30 days.')
        ->expectsConfirmation('Do you want to delete 1 webhook records?', 'yes')
        ->assertExitCode(0);

    expect(InboundWebhook::count())->toBe(2);
});
