<?php

namespace Vherbaut\InboundWebhooks\Drivers;

use Illuminate\Http\Request;
use Vherbaut\InboundWebhooks\Exceptions\InvalidSignatureException;

/**
 * Contract for webhook driver implementations.
 *
 * All webhook drivers must implement this interface to handle signature
 * validation, event type extraction, and payload parsing for their
 * respective providers.
 *
 * Built-in implementations:
 * - StripeDriver
 * - GitHubDriver
 * - SlackDriver
 * - TwilioDriver
 * - HmacDriver (generic)
 *
 * @see \Vherbaut\InboundWebhooks\Drivers\AbstractDriver Base implementation
 */
interface DriverInterface
{
    /**
     * Validate the webhook signature.
     *
     * @throws InvalidSignatureException
     */
    public function validateSignature(Request $request): void;

    /**
     * Extract the event type from the webhook payload.
     */
    public function getEventType(Request $request): ?string;

    /**
     * Extract the external ID (provider's webhook/event ID) from the request.
     */
    public function getExternalId(Request $request): ?string;

    /**
     * Get the parsed payload from the request.
     */
    public function getPayload(Request $request): array;

    /**
     * Get headers that should be stored with the webhook.
     */
    public function getStorableHeaders(Request $request): array;
}
