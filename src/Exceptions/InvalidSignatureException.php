<?php

namespace Vherbaut\InboundWebhooks\Exceptions;

use Exception;

/**
 * Exception thrown when webhook signature validation fails.
 *
 * This exception is thrown by drivers when:
 * - The signature header is missing
 * - The signature format is invalid
 * - The computed signature doesn't match the provided signature
 * - The timestamp is outside the tolerance window (replay attack protection)
 *
 * @see \Vherbaut\InboundWebhooks\Drivers\DriverInterface::validateSignature()
 */
class InvalidSignatureException extends Exception
{
}
