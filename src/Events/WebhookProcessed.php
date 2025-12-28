<?php

namespace Vherbaut\InboundWebhooks\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Vherbaut\InboundWebhooks\Models\InboundWebhook;

/**
 * Event dispatched after a webhook has been successfully processed.
 *
 * This event is fired when webhook processing completes without errors,
 * allowing you to implement post-processing logic, analytics, or cleanup.
 *
 * @example
 * ```php
 * // In EventServiceProvider
 * protected $listen = [
 *     WebhookProcessed::class => [
 *         UpdateWebhookMetrics::class,
 *     ],
 * ];
 *
 * // In your listener
 * public function handle(WebhookProcessed $event): void
 * {
 *     Metrics::increment("webhooks.{$event->webhook->provider}.processed");
 * }
 * ```
 */
class WebhookProcessed
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param InboundWebhook $webhook The webhook that was successfully processed
     */
    public function __construct(
        public InboundWebhook $webhook
    ) {}
}
