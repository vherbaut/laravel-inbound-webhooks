<?php

namespace Vherbaut\InboundWebhooks\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Vherbaut\InboundWebhooks\Enums\WebhookStatus;
use Vherbaut\InboundWebhooks\Models\InboundWebhook;

/**
 * Artisan command to prune old webhook records from the database.
 *
 * This command provides advanced filtering options beyond the standard
 * model:prune command, allowing pruning by status and provider.
 *
 * @example php artisan webhooks:prune --days=30
 * @example php artisan webhooks:prune --status=processed --dry-run
 * @example php artisan webhooks:prune --provider=stripe --status=failed
 */
class PruneWebhooks extends Command
{
    /**
     * @var string
     */
    protected $signature = 'webhooks:prune
        {--days= : Number of days to retain (default from config)}
        {--status= : Only prune webhooks with this status (pending, processing, processed, failed)}
        {--provider= : Only prune webhooks from this provider}
        {--dry-run : Show what would be deleted without deleting}';

    /**
     * @var string
     */
    protected $description = 'Prune old webhook records from the database with advanced filtering';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $days = $this->option('days') ?? config('inbound-webhooks.storage.retention_days');

        if ($days === null) {
            $this->info('Retention is set to forever. Nothing to prune.');

            return self::SUCCESS;
        }

        $query = InboundWebhook::query()->olderThan((int) $days);

        if ($statusOption = $this->option('status')) {
            $status = WebhookStatus::tryFrom($statusOption);

            if ($status === null) {
                $this->error("Invalid status '{$statusOption}'. Valid values: pending, processing, processed, failed");

                return self::FAILURE;
            }

            $query->status($status);
        }

        if ($provider = $this->option('provider')) {
            $query->provider($provider);
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('No webhooks to prune.');

            return self::SUCCESS;
        }

        $this->info("Found {$count} webhooks older than {$days} days.");

        if ($this->option('dry-run')) {
            $this->warn('Dry run - no records will be deleted.');

            $this->displayPruneSummary($query);

            return self::SUCCESS;
        }

        if (!$this->confirm("Do you want to delete {$count} webhook records?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info("Deleted {$deleted} webhook records.");

        return self::SUCCESS;
    }

    /**
     * Display a summary table of webhooks that would be pruned.
     *
     * @param Builder<InboundWebhook> $query
     */
    protected function displayPruneSummary(Builder $query): void
    {
        /** @var Collection<int, object{provider: string, status: string|WebhookStatus, count: int}> $results */
        $results = $query->clone()
            ->selectRaw('provider, status, count(*) as count')
            ->groupBy('provider', 'status')
            ->get();

        $summary = $results
            ->map(fn (object $row): array => [
                $row->provider,
                $row->status instanceof WebhookStatus ? $row->status->value : (string) $row->status,
                $row->count,
            ])
            ->toArray();

        $this->table(['Provider', 'Status', 'Count'], $summary);
    }
}