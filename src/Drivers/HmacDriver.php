<?php

namespace Vherbaut\InboundWebhooks\Drivers;

use Illuminate\Http\Request;
use Vherbaut\InboundWebhooks\Exceptions\InvalidSignatureException;

/**
 * Generic HMAC driver for custom webhook providers.
 *
 * This driver provides a flexible HMAC-based signature validation that can be
 * configured to work with most webhook providers using standard HMAC signatures.
 *
 * Configuration options:
 * - `secret` (required): The shared secret key for HMAC computation
 * - `algorithm` (optional): Hash algorithm, defaults to 'sha256'
 * - `header` (optional): Header name containing the signature, defaults to 'X-Signature'
 * - `prefix` (optional): Prefix to strip from signature (e.g., 'sha256=')
 * - `event_key` (optional): Payload key for event type, defaults to 'event'
 * - `event_header` (optional): Header name containing the event type
 * - `id_key` (optional): Payload key for external ID, defaults to 'id'
 * - `id_header` (optional): Header name containing the external ID
 *
 * @see https://en.wikipedia.org/wiki/HMAC
 */
class HmacDriver extends AbstractDriver
{
    /**
     * Validate the webhook signature using HMAC.
     *
     * Computes an HMAC signature of the raw request body using the configured
     * secret and algorithm, then compares it with the signature from the request header.
     *
     * @param  Request  $request  The incoming webhook request
     *
     * @throws InvalidSignatureException If the signature header is missing, secret is not configured, or signature is invalid
     */
    public function validateSignature(Request $request): void
    {
        /** @var string $headerName */
        $headerName = $this->config['header'] ?? 'X-Signature';
        $signature = $request->header($headerName);

        if (empty($signature)) {
            throw new InvalidSignatureException("Missing {$headerName} header");
        }

        /** @var string|null $secret */
        $secret = $this->config['secret'] ?? null;

        if (empty($secret)) {
            throw new InvalidSignatureException('Webhook secret not configured');
        }

        /** @var string $algorithm */
        $algorithm = $this->config['algorithm'] ?? 'sha256';

        /** @var string $prefix */
        $prefix = $this->config['prefix'] ?? '';

        if ($prefix !== '' && str_starts_with($signature, $prefix)) {
            $signature = substr($signature, strlen($prefix));
        }

        $payload = $request->getContent();
        $computedSignature = $this->computeHmac($payload, $secret, $algorithm);

        if (! $this->compareSignatures($computedSignature, $signature)) {
            throw new InvalidSignatureException('Invalid webhook signature');
        }
    }

    /**
     * Extract the event type from the webhook request.
     *
     * Checks for the event type in the following order:
     * 1. Header specified by `event_header` config
     * 2. Payload key specified by `event_key` config (default: 'event')
     *
     * @param  Request  $request  The incoming webhook request
     *
     * @return string|null The event type, or null if not found
     */
    public function getEventType(Request $request): ?string
    {
        /** @var string $eventKey */
        $eventKey = $this->config['event_key'] ?? 'event';

        /** @var string|null $eventHeader */
        $eventHeader = $this->config['event_header'] ?? null;

        if ($eventHeader !== null && $request->hasHeader($eventHeader)) {
            $value = $request->header($eventHeader);

            return is_string($value) ? $value : null;
        }

        return $request->input($eventKey);
    }

    /**
     * Extract the external ID from the webhook request.
     *
     * Checks for the external ID in the following order:
     * 1. Header specified by `id_header` config
     * 2. Payload key specified by `id_key` config (default: 'id')
     *
     * @param  Request  $request  The incoming webhook request
     *
     * @return string|null The external ID, or null if not found
     */
    public function getExternalId(Request $request): ?string
    {
        /** @var string $idKey */
        $idKey = $this->config['id_key'] ?? 'id';

        /** @var string|null $idHeader */
        $idHeader = $this->config['id_header'] ?? null;

        if ($idHeader !== null && $request->hasHeader($idHeader)) {
            $value = $request->header($idHeader);

            return is_string($value) ? $value : null;
        }

        return $request->input($idKey);
    }

    /**
     * Get the list of headers to store for auditing.
     *
     * Includes the signature header and optionally the event and ID headers
     * if configured.
     *
     * @return array<int, string> List of header names
     */
    protected function getRelevantHeaders(): array
    {
        $headers = parent::getRelevantHeaders();

        /** @var string $headerName */
        $headerName = $this->config['header'] ?? 'X-Signature';
        $headers[] = $headerName;

        if (! empty($this->config['event_header'])) {
            /** @var string $eventHeader */
            $eventHeader = $this->config['event_header'];
            $headers[] = $eventHeader;
        }

        if (! empty($this->config['id_header'])) {
            /** @var string $idHeader */
            $idHeader = $this->config['id_header'];
            $headers[] = $idHeader;
        }

        return $headers;
    }
}
