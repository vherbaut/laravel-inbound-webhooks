<?php

namespace Vherbaut\InboundWebhooks\Exceptions;

use Exception;

/**
 * Exception thrown when an unconfigured or unknown provider is requested.
 *
 * This exception is thrown by DriverManager when:
 * - The requested provider is not configured in `config/inbound-webhooks.php`
 * - The driver specified in the configuration doesn't exist
 * - No custom driver has been registered for the provider
 *
 * @see \Vherbaut\InboundWebhooks\Drivers\DriverManager::driver()
 */
class UnknownProviderException extends Exception {}
