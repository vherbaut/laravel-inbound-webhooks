<?php

namespace Vherbaut\InboundWebhooks\Drivers;

use Illuminate\Http\Request;
use Vherbaut\InboundWebhooks\Exceptions\InvalidSignatureException;

/**
 * Driver for handling Stripe webhook signature validation.
 *
 * Implements Stripe's webhook signature verification using HMAC-SHA256 with
 * timestamp validation to prevent replay attacks.
 *
 * Configuration options:
 * - `secret` (required): Your Stripe webhook endpoint signing secret (whsec_...)
 * - `tolerance` (optional): Maximum age of requests in seconds, defaults to 300 (5 minutes)
 *
 * @see https://stripe.com/docs/webhooks/signatures
 */
class StripeDriver extends AbstractDriver
{
    /**
     * HTTP header containing the Stripe webhook signature.
     *
     * @var string
     */
    protected const SIGNATURE_HEADER = 'Stripe-Signature';

    /**
     * Validate the Stripe webhook signature.
     *
     * Stripe's signature header contains:
     * - `t`: Unix timestamp when the signature was generated
     * - `v1`: HMAC-SHA256 signature of "{timestamp}.{payload}"
     *
     * The signature is validated by recomputing the HMAC and comparing
     * with a timing-safe function to prevent timing attacks.
     *
     * @param Request $request The incoming webhook request
     *
     * @throws InvalidSignatureException If the header is missing, secret is not configured,
     *                                   format is invalid, timestamp is outside tolerance,
     *                                   or signature does not match
     */
    public function validateSignature(Request $request): void
    {
        $signature = $request->header(self::SIGNATURE_HEADER);

        if (empty($signature)) {
            throw new InvalidSignatureException('Missing Stripe-Signature header');
        }

        /** @var string|null $secret */
        $secret = $this->config['secret'] ?? null;

        if (empty($secret)) {
            throw new InvalidSignatureException('Stripe webhook secret not configured');
        }

        $payload = $request->getContent();
        $parsedSignature = $this->parseSignature($signature);

        if (! isset($parsedSignature['t']) || ! isset($parsedSignature['v1'])) {
            throw new InvalidSignatureException('Invalid Stripe signature format');
        }

        /** @var int $timestamp */
        $timestamp = (int) $parsedSignature['t'];

        /** @var string $expectedSignature */
        $expectedSignature = $parsedSignature['v1'];

        /** @var int $tolerance */
        $tolerance = $this->config['tolerance'] ?? 300;

        if (abs(time() - $timestamp) > $tolerance) {
            throw new InvalidSignatureException('Stripe webhook timestamp is outside tolerance');
        }

        $signedPayload = "{$timestamp}.{$payload}";
        $computedSignature = $this->computeHmac($signedPayload, $secret);

        if (! $this->compareSignatures($computedSignature, $expectedSignature)) {
            throw new InvalidSignatureException('Invalid Stripe signature');
        }
    }

    /**
     * Extract the event type from the Stripe webhook payload.
     *
     * Stripe event types follow the format "resource.action" (e.g.,
     * "payment_intent.succeeded", "customer.subscription.deleted").
     *
     * @param Request $request The incoming webhook request
     *
     * @return string|null The Stripe event type
     */
    public function getEventType(Request $request): ?string
    {
        return $request->input('type');
    }

    /**
     * Extract the external ID from the Stripe webhook payload.
     *
     * Returns the Stripe event ID (e.g., "evt_1234567890").
     *
     * @param Request $request The incoming webhook request
     *
     * @return string|null The Stripe event ID
     */
    public function getExternalId(Request $request): ?string
    {
        return $request->input('id');
    }

    /**
     * Get the list of Stripe-specific headers to store for auditing.
     *
     * @return array<int, string> List of header names
     */
    protected function getRelevantHeaders(): array
    {
        return array_merge(parent::getRelevantHeaders(), [
            self::SIGNATURE_HEADER,
        ]);
    }

    /**
     * Parse the Stripe signature header into its components.
     *
     * The header format is: "t=timestamp,v1=signature,v0=legacy_signature"
     * Each component is a key=value pair separated by commas.
     *
     * @param string $signature The raw Stripe-Signature header value
     *
     * @return array<string, string> Parsed signature components indexed by key
     */
    protected function parseSignature(string $signature): array
    {
        $parts = explode(',', $signature);
        $parsed = [];

        foreach ($parts as $part) {
            $pair = explode('=', $part, 2);

            if (count($pair) === 2) {
                $parsed[$pair[0]] = $pair[1];
            }
        }

        return $parsed;
    }
}
