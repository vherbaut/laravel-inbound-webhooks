<?php

namespace Vherbaut\InboundWebhooks\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;
use Vherbaut\InboundWebhooks\Events\WebhookFailed;
use Vherbaut\InboundWebhooks\Events\WebhookProcessed;
use Vherbaut\InboundWebhooks\Events\WebhookReceived;
use Vherbaut\InboundWebhooks\Models\InboundWebhook;

/**
 * Queued job for processing incoming webhooks asynchronously.
 *
 * This job is dispatched by the WebhookController after storing the webhook.
 * It handles the event dispatching lifecycle:
 * 1. Marks the webhook as processing
 * 2. Dispatches WebhookReceived event
 * 3. Dispatches any mapped custom event
 * 4. Marks the webhook as processed and dispatches WebhookProcessed
 * 5. On failure, marks as failed and dispatches WebhookFailed
 *
 * The job uses exponential backoff for retries: 10s, 60s, 300s.
 */
class ProcessWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * The backoff times in seconds between retry attempts.
     *
     * Uses exponential backoff: 10 seconds, 1 minute, 5 minutes.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * Create a new job instance.
     *
     * Configures the queue connection and queue name from the package config.
     *
     * @param InboundWebhook $webhook The webhook to process
     */
    public function __construct(
        public InboundWebhook $webhook
    ) {
        /** @var string $connection */
        $connection = config('inbound-webhooks.queue.connection', 'default');

        /** @var string $queue */
        $queue = config('inbound-webhooks.queue.queue', 'webhooks');

        $this->onConnection($connection);
        $this->onQueue($queue);
    }

    /**
     * Execute the job.
     *
     * Dispatches the WebhookReceived event for listeners to handle the webhook.
     * If a mapped event class is configured, that event is also dispatched.
     * Updates the webhook status throughout the process.
     *
     * @throws Throwable Re-throws any exception after marking the webhook as failed
     */
    public function handle(): void
    {
        $this->webhook->markAsProcessing();

        try {
            event(new WebhookReceived($this->webhook));

            $mappedEvent = $this->getMappedEvent();

            if ($mappedEvent !== null) {
                event(new $mappedEvent($this->webhook));
            }

            $this->webhook->markAsProcessed();
            event(new WebhookProcessed($this->webhook));
        } catch (Throwable $e) {
            $this->handleFailure($e);

            throw $e;
        }
    }

    /**
     * Handle a job failure after all retries are exhausted.
     *
     * Called by Laravel's queue system when the job ultimately fails.
     *
     * @param Throwable|null $exception The exception that caused the failure
     */
    public function failed(?Throwable $exception): void
    {
        if ($exception !== null) {
            $this->handleFailure($exception);
        }
    }

    /**
     * Get the mapped event class for this webhook.
     *
     * Looks up the event identifier (provider.event_type) in the
     * configured events mapping to find a custom event class.
     *
     * @return class-string|null The fully qualified class name, or null if not mapped
     */
    protected function getMappedEvent(): ?string
    {
        $identifier = $this->webhook->getEventIdentifier();

        /** @var array<string, class-string> $events */
        $events = config('inbound-webhooks.events', []);

        return $events[$identifier] ?? null;
    }

    /**
     * Handle webhook processing failure.
     *
     * Updates the webhook status to failed with the error message
     * and dispatches the WebhookFailed event for error handling.
     *
     * @param Throwable $e The exception that caused the failure
     */
    protected function handleFailure(Throwable $e): void
    {
        $this->webhook->markAsFailed($e->getMessage());
        event(new WebhookFailed($this->webhook, $e));
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * Tags are used by Laravel Horizon for filtering and monitoring.
     *
     * @return array<int, string> List of tags for this job
     */
    public function tags(): array
    {
        return [
            'inbound-webhook',
            "provider:{$this->webhook->provider}",
            "event:{$this->webhook->event_type}",
        ];
    }
}
