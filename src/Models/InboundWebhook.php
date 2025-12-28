<?php

namespace Vherbaut\InboundWebhooks\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Vherbaut\InboundWebhooks\Enums\WebhookStatus;

/**
 * Eloquent model representing an inbound webhook received from an external provider.
 *
 * This model stores all incoming webhooks with their payload, headers, and processing status.
 * It supports automatic UUID generation, mass pruning for cleanup, and provides convenient
 * scopes for querying webhooks by provider, event type, or status.
 *
 * @property int $id Primary key
 * @property string $uuid Unique identifier for the webhook
 * @property string $provider The webhook provider (e.g., "stripe", "github")
 * @property string|null $event_type The event type from the provider
 * @property string|null $external_id The provider's unique ID for this webhook
 * @property array<string, string>|null $headers Relevant HTTP headers stored for auditing
 * @property array<string, mixed>|null $payload The full webhook payload
 * @property WebhookStatus $status Current processing status
 * @property string|null $error_message Error message if processing failed
 * @property int $attempts Number of processing attempts
 * @property Carbon|null $processed_at Timestamp when successfully processed
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static Builder<InboundWebhook> query()
 * @method static Builder<InboundWebhook> provider(string $provider)
 * @method static Builder<InboundWebhook> eventType(string $eventType)
 * @method static Builder<InboundWebhook> status(WebhookStatus $status)
 * @method static Builder<InboundWebhook> pending()
 * @method static Builder<InboundWebhook> failed()
 * @method static Builder<InboundWebhook> processed()
 * @method static Builder<InboundWebhook> olderThan(int $days)
 */
class InboundWebhook extends Model
{
    use HasUuids;
    use MassPrunable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'inbound_webhooks';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'provider',
        'event_type',
        'external_id',
        'headers',
        'payload',
        'status',
        'error_message',
        'attempts',
        'processed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'headers' => 'array',
        'payload' => 'array',
        'status' => WebhookStatus::class,
        'processed_at' => 'datetime',
    ];

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /*
    |--------------------------------------------------------------------------
    | Status Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if the webhook is pending processing.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === WebhookStatus::Pending;
    }

    /**
     * Check if the webhook is currently being processed.
     *
     * @return bool
     */
    public function isProcessing(): bool
    {
        return $this->status === WebhookStatus::Processing;
    }

    /**
     * Check if the webhook has been successfully processed.
     *
     * @return bool
     */
    public function isProcessed(): bool
    {
        return $this->status === WebhookStatus::Processed;
    }

    /**
     * Check if the webhook processing has failed.
     *
     * @return bool
     */
    public function isFailed(): bool
    {
        return $this->status === WebhookStatus::Failed;
    }

    /**
     * Mark the webhook as currently processing.
     *
     * @return $this
     */
    public function markAsProcessing(): self
    {
        $this->update([
            'status' => WebhookStatus::Processing,
            'attempts' => $this->attempts + 1,
        ]);

        return $this;
    }

    /**
     * Mark the webhook as successfully processed.
     *
     * @return $this
     */
    public function markAsProcessed(): self
    {
        $this->update([
            'status' => WebhookStatus::Processed,
            'processed_at' => now(),
            'error_message' => null,
        ]);

        return $this;
    }

    /**
     * Mark the webhook as failed with an error message.
     *
     * @param  string  $errorMessage  The error message describing the failure
     * @return $this
     */
    public function markAsFailed(string $errorMessage): self
    {
        $this->update([
            'status' => WebhookStatus::Failed,
            'error_message' => $errorMessage,
        ]);

        return $this;
    }

    /**
     * Reset the webhook status for retry processing.
     *
     * @return $this
     */
    public function resetForRetry(): self
    {
        $this->update([
            'status' => WebhookStatus::Pending,
            'error_message' => null,
        ]);

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope a query to only include webhooks from a specific provider.
     *
     * @param  Builder<InboundWebhook>  $query
     * @param  string  $provider  The provider name (e.g., 'stripe', 'github')
     * @return Builder<InboundWebhook>
     */
    public function scopeProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope a query to only include webhooks of a specific event type.
     *
     * @param  Builder<InboundWebhook>  $query
     * @param  string  $eventType  The event type (e.g., 'payment_intent.succeeded')
     * @return Builder<InboundWebhook>
     */
    public function scopeEventType(Builder $query, string $eventType): Builder
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope a query to only include webhooks with a specific status.
     *
     * @param  Builder<InboundWebhook>  $query
     * @param  WebhookStatus  $status  The webhook status
     * @return Builder<InboundWebhook>
     */
    public function scopeStatus(Builder $query, WebhookStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include pending webhooks.
     *
     * @param  Builder<InboundWebhook>  $query
     * @return Builder<InboundWebhook>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', WebhookStatus::Pending);
    }

    /**
     * Scope a query to only include failed webhooks.
     *
     * @param  Builder<InboundWebhook>  $query
     * @return Builder<InboundWebhook>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', WebhookStatus::Failed);
    }

    /**
     * Scope a query to only include processed webhooks.
     *
     * @param  Builder<InboundWebhook>  $query
     * @return Builder<InboundWebhook>
     */
    public function scopeProcessed(Builder $query): Builder
    {
        return $query->where('status', WebhookStatus::Processed);
    }

    /**
     * Scope a query to only include webhooks older than the specified days.
     *
     * @param  Builder<InboundWebhook>  $query
     * @param  int  $days  Number of days
     * @return Builder<InboundWebhook>
     */
    public function scopeOlderThan(Builder $query, int $days): Builder
    {
        return $query->where('created_at', '<', now()->subDays($days));
    }

    /*
    |--------------------------------------------------------------------------
    | Prunable
    |--------------------------------------------------------------------------
    */

    /**
     * Get the prunable model query.
     *
     * Determines which webhook records should be pruned based on the
     * configured retention period. Used by Laravel's model:prune command.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = config('inbound-webhooks.storage.retention_days');

        if ($days === null) {
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()->where('created_at', '<', now()->subDays($days));
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Get the route key name for Laravel route model binding.
     *
     * @return string
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Get the event identifier combining provider and event type.
     *
     * @return string
     */
    public function getEventIdentifier(): string
    {
        return "{$this->provider}.{$this->event_type}";
    }
}
