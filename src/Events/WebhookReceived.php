<?php

namespace Vherbaut\InboundWebhooks\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Vherbaut\InboundWebhooks\Models\InboundWebhook;

/**
 * Event dispatched when a webhook is received and ready for processing.
 *
 * This is the primary event for handling incoming webhooks. It provides
 * convenient helper methods to access the webhook data without directly
 * interacting with the model.
 *
 * @example
 * ```php
 * // In EventServiceProvider
 * protected $listen = [
 *     WebhookReceived::class => [
 *         HandleStripeWebhook::class,
 *         HandleGitHubWebhook::class,
 *     ],
 * ];
 *
 * // In your listener
 * public function handle(WebhookReceived $event): void
 * {
 *     if ($event->provider() !== 'stripe') {
 *         return;
 *     }
 *
 *     match ($event->eventType()) {
 *         'payment_intent.succeeded' => $this->handlePayment($event),
 *         'customer.created' => $this->handleCustomer($event),
 *         default => null,
 *     };
 * }
 * ```
 */
class WebhookReceived
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  InboundWebhook  $webhook  The received webhook model
     */
    public function __construct(
        public InboundWebhook $webhook
    ) {}

    /**
     * Get the provider name (e.g., "stripe", "github", "slack").
     *
     * @return string The provider identifier
     */
    public function provider(): string
    {
        return $this->webhook->provider;
    }

    /**
     * Get the event type from the webhook payload.
     *
     * The format depends on the provider:
     * - Stripe: "payment_intent.succeeded"
     * - GitHub: "push" or "pull_request.opened"
     * - Slack: "message" or "app_mention"
     *
     * @return string|null The event type, or null if not available
     */
    public function eventType(): ?string
    {
        return $this->webhook->event_type;
    }

    /**
     * Get the full webhook payload.
     *
     * @return array<string, mixed> The complete payload data
     */
    public function payload(): array
    {
        return $this->webhook->payload ?? [];
    }

    /**
     * Get a value from the payload using dot notation.
     *
     * Provides convenient access to nested payload data without
     * manual array traversal.
     *
     * @param  string  $key  The dot-notation key (e.g., "data.object.id")
     * @param  mixed  $default  Default value if key doesn't exist
     *
     * @return mixed The value at the specified key, or the default
     *
     * @example
     * ```php
     * $customerId = $event->get('data.object.customer');
     * $amount = $event->get('data.object.amount', 0);
     * ```
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->payload(), $key, $default);
    }
}
