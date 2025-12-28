<?php

namespace Vherbaut\InboundWebhooks\Drivers;

use Illuminate\Http\Request;
use Vherbaut\InboundWebhooks\Exceptions\InvalidSignatureException;

/**
 * Driver for handling Slack webhook signature validation and payload parsing.
 *
 * Implements Slack's signed secrets verification using HMAC-SHA256.
 * Supports Events API, Interactive Components, and Slash Commands.
 *
 * Configuration options:
 * - `signing_secret` (required): Your Slack app's signing secret
 * - `tolerance` (optional): Maximum age of requests in seconds, defaults to 300 (5 minutes)
 *
 * @see https://api.slack.com/authentication/verifying-requests-from-slack
 */
class SlackDriver extends AbstractDriver
{
    /**
     * HTTP header containing the Slack request signature.
     *
     * @var string
     */
    protected const SIGNATURE_HEADER = 'X-Slack-Signature';

    /**
     * HTTP header containing the request timestamp.
     *
     * @var string
     */
    protected const TIMESTAMP_HEADER = 'X-Slack-Request-Timestamp';

    /**
     * Validate the Slack webhook signature.
     *
     * Slack uses a versioned signature format (v0=...) computed from:
     * - The literal string "v0"
     * - The request timestamp
     * - The raw request body
     *
     * @param  Request  $request  The incoming webhook request
     *
     * @throws InvalidSignatureException If headers are missing, secret is not configured,
     *                                   timestamp is outside tolerance, or signature is invalid
     */
    public function validateSignature(Request $request): void
    {
        $signature = $request->header(self::SIGNATURE_HEADER);
        $timestamp = $request->header(self::TIMESTAMP_HEADER);

        if (empty($signature) || empty($timestamp)) {
            throw new InvalidSignatureException('Missing Slack signature headers');
        }

        /** @var string|null $secret */
        $secret = $this->config['signing_secret'] ?? null;

        if (empty($secret)) {
            throw new InvalidSignatureException('Slack signing secret not configured');
        }

        /** @var int $tolerance */
        $tolerance = $this->config['tolerance'] ?? 300;

        if (abs(time() - (int) $timestamp) > $tolerance) {
            throw new InvalidSignatureException('Slack request timestamp is outside tolerance');
        }

        if (! str_starts_with($signature, 'v0=')) {
            throw new InvalidSignatureException('Invalid Slack signature format');
        }

        $expectedSignature = substr($signature, 3);
        $payload = $request->getContent();

        $signatureBaseString = "v0:{$timestamp}:{$payload}";
        $computedSignature = $this->computeHmac($signatureBaseString, $secret);

        if (! $this->compareSignatures($computedSignature, $expectedSignature)) {
            throw new InvalidSignatureException('Invalid Slack signature');
        }
    }

    /**
     * Extract the event type from the Slack webhook request.
     *
     * Handles different Slack webhook formats:
     * - URL verification challenges: returns 'url_verification'
     * - Event callbacks: returns the nested event.type
     * - Interactive components: parses the JSON payload and returns the type
     * - Other requests: returns the top-level type
     *
     * @param  Request  $request  The incoming webhook request
     *
     * @return string|null The event type, or null if not determinable
     */
    public function getEventType(Request $request): ?string
    {
        if ($request->input('type') === 'url_verification') {
            return 'url_verification';
        }

        if ($request->input('type') === 'event_callback') {
            return $request->input('event.type');
        }

        $payload = $request->input('payload');

        if ($payload) {
            /** @var array<string, mixed>|null $decoded */
            $decoded = is_string($payload) ? json_decode($payload, true) : $payload;

            return $decoded['type'] ?? null;
        }

        return $request->input('type');
    }

    /**
     * Extract the external ID from the Slack webhook request.
     *
     * Attempts to find the event ID from:
     * 1. The top-level event_id field
     * 2. The nested event.event_ts field
     *
     * @param  Request  $request  The incoming webhook request
     *
     * @return string|null The external event ID, or null if not found
     */
    public function getExternalId(Request $request): ?string
    {
        return $request->input('event_id') ?? $request->input('event.event_ts');
    }

    /**
     * Extract the payload from the Slack webhook request.
     *
     * Handles both JSON payloads and form-encoded payloads from interactive
     * components (which send a JSON string in a 'payload' form field).
     *
     * @param  Request  $request  The incoming webhook request
     *
     * @return array<string, mixed> The parsed payload data
     */
    public function getPayload(Request $request): array
    {
        $payload = $request->input('payload');

        if ($payload && is_string($payload)) {
            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($payload, true);

            return $decoded ?? $request->all();
        }

        return $request->all();
    }

    /**
     * Get the list of Slack-specific headers to store for auditing.
     *
     * @return array<int, string> List of header names
     */
    protected function getRelevantHeaders(): array
    {
        return array_merge(parent::getRelevantHeaders(), [
            self::SIGNATURE_HEADER,
            self::TIMESTAMP_HEADER,
        ]);
    }
}
