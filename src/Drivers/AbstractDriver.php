<?php

namespace Vherbaut\InboundWebhooks\Drivers;

use Illuminate\Http\Request;

/**
 * Abstract base driver providing common functionality for webhook providers.
 *
 * This class implements shared logic for signature validation helpers,
 * payload extraction, and header filtering. Concrete drivers should extend
 * this class and implement provider-specific signature validation and
 * event type extraction.
 */
abstract class AbstractDriver implements DriverInterface
{
    /**
     * Driver-specific configuration from the inbound-webhooks config file.
     *
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * @param  array<string, mixed>  $config  Driver-specific configuration
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Extract the parsed payload from the webhook request.
     *
     * @param  Request  $request  The incoming webhook request
     *
     * @return array<string, mixed> The parsed payload data
     */
    public function getPayload(Request $request): array
    {
        return $request->all();
    }

    /**
     * Extract headers that should be stored with the webhook for auditing.
     *
     * Filters the request headers to only include those defined as relevant
     * for this provider, avoiding storage of unnecessary or sensitive headers.
     *
     * @param  Request  $request  The incoming webhook request
     *
     * @return array<string, string> Associative array of header names to values
     */
    public function getStorableHeaders(Request $request): array
    {
        $headers = [];
        $relevantHeaders = $this->getRelevantHeaders();

        foreach ($relevantHeaders as $header) {
            if ($request->hasHeader($header)) {
                $value = $request->header($header);

                if (is_string($value)) {
                    $headers[$header] = $value;
                }
            }
        }

        return $headers;
    }

    /**
     * Get the list of header names that should be stored for this provider.
     *
     * Override this method in concrete drivers to include provider-specific
     * headers such as signature headers or request identifiers.
     *
     * @return array<int, string> List of header names to extract
     */
    protected function getRelevantHeaders(): array
    {
        return [
            'Content-Type',
            'User-Agent',
        ];
    }

    /**
     * Compute an HMAC signature for payload verification.
     *
     * @param  string  $payload  The raw payload content to sign
     * @param  string  $secret  The shared secret key
     * @param  string  $algorithm  The hashing algorithm (default: sha256)
     *
     * @return string The computed HMAC signature in hexadecimal format
     */
    protected function computeHmac(string $payload, string $secret, string $algorithm = 'sha256'): string
    {
        return hash_hmac($algorithm, $payload, $secret);
    }

    /**
     * Compare two signatures using a timing-safe comparison.
     *
     * This method prevents timing attacks by ensuring the comparison
     * takes constant time regardless of where the strings differ.
     *
     * @param  string  $expected  The expected signature value
     * @param  string  $actual  The actual signature from the request
     *
     * @return bool True if signatures match, false otherwise
     */
    protected function compareSignatures(string $expected, string $actual): bool
    {
        return hash_equals($expected, $actual);
    }
}
