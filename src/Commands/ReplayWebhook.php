<?php

namespace Vherbaut\InboundWebhooks\Commands;

use Illuminate\Console\Command;
use Throwable;
use Vherbaut\InboundWebhooks\Jobs\ProcessWebhook;
use Vherbaut\InboundWebhooks\Models\InboundWebhook;

/**
 * Artisan command to replay a previously received webhook.
 *
 * This command allows replaying webhooks for debugging or
 * reprocessing after fixing issues.
 *
 * @example php artisan webhooks:replay abc-123-uuid
 * @example php artisan webhooks:replay abc-123-uuid --sync
 * @example php artisan webhooks:replay abc-123-uuid --force
 */
class ReplayWebhook extends Command
{
    /**
     * @var string
     */
    protected $signature = 'webhooks:replay
        {id : The UUID or ID of the webhook to replay}
        {--sync : Process synchronously instead of queueing}
        {--force : Replay even if already processed}';

    /**
     * @var string
     */
    protected $description = 'Replay a previously received webhook';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $id = $this->argument('id');

        $webhook = InboundWebhook::query()
            ->where('uuid', $id)
            ->orWhere('id', $id)
            ->first();

        if (! $webhook) {
            $this->error("Webhook [{$id}] not found.");

            return self::FAILURE;
        }

        $this->displayWebhookInfo($webhook);

        if ($webhook->isProcessed() && ! $this->option('force')) {
            $this->warn('This webhook has already been processed.');

            if (! $this->confirm('Do you want to replay it anyway?')) {
                return self::SUCCESS;
            }
        }

        $webhook->resetForRetry();

        if ($this->option('sync')) {
            return $this->processSync($webhook);
        }

        ProcessWebhook::dispatch($webhook);
        $this->info('Webhook queued for processing.');

        return self::SUCCESS;
    }

    /**
     * Process the webhook synchronously.
     *
     * @param  InboundWebhook  $webhook
     * @return int
     */
    protected function processSync(InboundWebhook $webhook): int
    {
        $this->info('Processing webhook synchronously...');

        try {
            (new ProcessWebhook($webhook))->handle();
            $this->info('Webhook processed successfully.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Processing failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Display webhook information in a table.
     */
    protected function displayWebhookInfo(InboundWebhook $webhook): void
    {
        $this->newLine();
        $this->table(
            ['Field', 'Value'],
            [
                ['UUID', $webhook->uuid],
                ['Provider', $webhook->provider],
                ['Event Type', $webhook->event_type ?? 'N/A'],
                ['External ID', $webhook->external_id ?? 'N/A'],
                ['Status', $webhook->status->value],
                ['Attempts', (string) $webhook->attempts],
                ['Created', $webhook->created_at->format('Y-m-d H:i:s')],
            ]
        );
        $this->newLine();
    }
}
