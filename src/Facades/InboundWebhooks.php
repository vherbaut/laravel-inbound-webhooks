<?php

namespace Vherbaut\InboundWebhooks\Facades;

use Illuminate\Support\Facades\Facade;
use Vherbaut\InboundWebhooks\Drivers\DriverInterface;
use Vherbaut\InboundWebhooks\Drivers\DriverManager;

/**
 * @method static DriverInterface driver(string $provider)
 * @method static bool hasProvider(string $provider)
 * @method static array getProviders()
 * @method static DriverManager extend(string $name, callable $callback)
 *
 * @see DriverManager
 */
class InboundWebhooks extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return DriverManager::class;
    }
}
