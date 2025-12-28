<?php

namespace Vherbaut\InboundWebhooks\Drivers;

use Illuminate\Http\Request;
use Vherbaut\InboundWebhooks\Exceptions\InvalidSignatureException;

/**
 * Driver for handling Twilio webhook signature validation.
 *
 * Implements Twilio's request validation using HMAC-SHA1 with the Auth Token.
 * Supports SMS webhooks, Voice webhooks, and status callbacks.
 *
 * Configuration options:
 * - `auth_token` (required): Your Twilio Auth Token
 *
 * @see https://www.twilio.com/docs/usage/security#validating-requests
 */
class TwilioDriver extends AbstractDriver
{
    /**
     * HTTP header containing the Twilio request signature.
     *
     * @var string
     */
    protected const SIGNATURE_HEADER = 'X-Twilio-Signature';

    /**
     * Validate the Twilio webhook signature.
     *
     * Twilio's signature is computed from:
     * 1. The full URL of the request
     * 2. All POST parameters sorted alphabetically and concatenated (key + value)
     * 3. HMAC-SHA1 of the above, base64 encoded
     *
     * @param Request $request The incoming webhook request
     *
     * @throws InvalidSignatureException If the header is missing, auth token is not configured,
     *                                   or signature does not match
     */
    public function validateSignature(Request $request): void
    {
        $signature = $request->header(self::SIGNATURE_HEADER);

        if (empty($signature)) {
            throw new InvalidSignatureException('Missing X-Twilio-Signature header');
        }

        /** @var string|null $authToken */
        $authToken = $this->config['auth_token'] ?? null;

        if (empty($authToken)) {
            throw new InvalidSignatureException('Twilio auth token not configured');
        }

        $url = $request->fullUrl();

        /** @var array<string, mixed> $params */
        $params = $request->post();

        ksort($params);

        $dataString = $url;

        foreach ($params as $key => $value) {
            $dataString .= $key . $value;
        }

        $computedSignature = base64_encode(hash_hmac('sha1', $dataString, $authToken, true));

        if (! $this->compareSignatures($computedSignature, $signature)) {
            throw new InvalidSignatureException('Invalid Twilio signature');
        }
    }

    /**
     * Extract the event type from the Twilio webhook request.
     *
     * Determines the event type based on the presence of specific fields:
     * - SMS webhooks: "message.{status}" or "message.received"
     * - Voice webhooks: "call.{status}" or "call.incoming"
     * - Status callbacks: "status_callback"
     *
     * @param Request $request The incoming webhook request
     *
     * @return string|null The derived event type, or null if not determinable
     */
    public function getEventType(Request $request): ?string
    {
        if ($request->has('MessageSid')) {
            /** @var string|null $status */
            $status = $request->input('MessageStatus', $request->input('SmsStatus'));

            return $status ? "message.{$status}" : 'message.received';
        }

        if ($request->has('CallSid')) {
            /** @var string|null $status */
            $status = $request->input('CallStatus');

            return $status ? "call.{$status}" : 'call.incoming';
        }

        if ($request->has('StatusCallback')) {
            return 'status_callback';
        }

        return null;
    }

    /**
     * Extract the external ID from the Twilio webhook request.
     *
     * Returns the first available SID in order of priority:
     * 1. MessageSid (for SMS webhooks)
     * 2. CallSid (for Voice webhooks)
     * 3. SmsSid (legacy SMS webhook field)
     *
     * @param Request $request The incoming webhook request
     *
     * @return string|null The Twilio SID, or null if not found
     */
    public function getExternalId(Request $request): ?string
    {
        return $request->input('MessageSid')
            ?? $request->input('CallSid')
            ?? $request->input('SmsSid');
    }

    /**
     * Get the list of Twilio-specific headers to store for auditing.
     *
     * @return array<int, string> List of header names
     */
    protected function getRelevantHeaders(): array
    {
        return array_merge(parent::getRelevantHeaders(), [
            self::SIGNATURE_HEADER,
        ]);
    }
}
