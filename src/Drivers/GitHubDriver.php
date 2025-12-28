<?php

namespace Vherbaut\InboundWebhooks\Drivers;

use Illuminate\Http\Request;
use Vherbaut\InboundWebhooks\Exceptions\InvalidSignatureException;

/**
 * Driver for handling GitHub webhook signature validation.
 *
 * Implements GitHub's webhook signature verification using HMAC-SHA256.
 * Supports all GitHub webhook events including repository, organization,
 * and GitHub App events.
 *
 * Configuration options:
 * - `secret` (required): Your GitHub webhook secret
 *
 * @see https://docs.github.com/en/webhooks/using-webhooks/validating-webhook-deliveries
 */
class GitHubDriver extends AbstractDriver
{
    /**
     * HTTP header containing the HMAC-SHA256 signature.
     *
     * @var string
     */
    protected const SIGNATURE_HEADER = 'X-Hub-Signature-256';

    /**
     * HTTP header containing the event type (e.g., "push", "pull_request").
     *
     * @var string
     */
    protected const EVENT_HEADER = 'X-GitHub-Event';

    /**
     * HTTP header containing the unique delivery GUID.
     *
     * @var string
     */
    protected const DELIVERY_HEADER = 'X-GitHub-Delivery';

    /**
     * Validate the GitHub webhook signature.
     *
     * GitHub sends a signature in the format "sha256=<hex>" computed from
     * the raw request body using HMAC-SHA256 with the webhook secret.
     *
     * @param Request $request The incoming webhook request
     *
     * @throws InvalidSignatureException If the header is missing, secret is not configured,
     *                                   format is invalid, or signature does not match
     */
    public function validateSignature(Request $request): void
    {
        $signature = $request->header(self::SIGNATURE_HEADER);

        if (empty($signature)) {
            throw new InvalidSignatureException('Missing X-Hub-Signature-256 header');
        }

        /** @var string|null $secret */
        $secret = $this->config['secret'] ?? null;

        if (empty($secret)) {
            throw new InvalidSignatureException('GitHub webhook secret not configured');
        }

        if (! str_starts_with($signature, 'sha256=')) {
            throw new InvalidSignatureException('Invalid GitHub signature format');
        }

        $expectedSignature = substr($signature, 7);
        $payload = $request->getContent();
        $computedSignature = $this->computeHmac($payload, $secret);

        if (! $this->compareSignatures($computedSignature, $expectedSignature)) {
            throw new InvalidSignatureException('Invalid GitHub signature');
        }
    }

    /**
     * Extract the event type from the GitHub webhook request.
     *
     * Combines the event header with the action field from the payload
     * to create a more specific event type (e.g., "pull_request.opened",
     * "issues.closed").
     *
     * @param Request $request The incoming webhook request
     *
     * @return string|null The event type in format "event.action" or just "event"
     */
    public function getEventType(Request $request): ?string
    {
        $event = $request->header(self::EVENT_HEADER);

        if (! is_string($event)) {
            return null;
        }

        /** @var string|null $action */
        $action = $request->input('action');

        if ($action) {
            return "{$event}.{$action}";
        }

        return $event;
    }

    /**
     * Extract the external ID from the GitHub webhook request.
     *
     * Returns the unique delivery GUID from the X-GitHub-Delivery header.
     * This ID is unique for each webhook delivery attempt.
     *
     * @param Request $request The incoming webhook request
     *
     * @return string|null The GitHub delivery GUID
     */
    public function getExternalId(Request $request): ?string
    {
        $deliveryId = $request->header(self::DELIVERY_HEADER);

        return is_string($deliveryId) ? $deliveryId : null;
    }

    /**
     * Get the list of GitHub-specific headers to store for auditing.
     *
     * Includes signature, event type, delivery ID, and additional metadata
     * headers useful for debugging and auditing.
     *
     * @return array<int, string> List of header names
     */
    protected function getRelevantHeaders(): array
    {
        return array_merge(parent::getRelevantHeaders(), [
            self::SIGNATURE_HEADER,
            self::EVENT_HEADER,
            self::DELIVERY_HEADER,
            'X-GitHub-Hook-ID',
            'X-GitHub-Hook-Installation-Target-Type',
        ]);
    }
}
