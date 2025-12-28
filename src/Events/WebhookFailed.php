<?php

namespace Vherbaut\InboundWebhooks\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;
use Vherbaut\InboundWebhooks\Models\InboundWebhook;

/**
 * Event dispatched when webhook processing fails.
 *
 * This event is fired when an exception occurs during webhook processing,
 * allowing you to implement custom error handling, notifications, or logging.
 *
 * @example
 * ```php
 * // In EventServiceProvider
 * protected $listen = [
 *     WebhookFailed::class => [
 *         NotifyOnWebhookFailure::class,
 *     ],
 * ];
 *
 * // In your listener
 * public function handle(WebhookFailed $event): void
 * {
 *     Log::error('Webhook failed', [
 *         'provider' => $event->webhook->provider,
 *         'event_type' => $event->webhook->event_type,
 *         'error' => $event->exception->getMessage(),
 *     ]);
 * }
 * ```
 */
class WebhookFailed
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  InboundWebhook  $webhook  The webhook that failed to process
     * @param  Throwable  $exception  The exception that caused the failure
     */
    public function __construct(
        public InboundWebhook $webhook,
        public Throwable $exception
    ) {}
}
