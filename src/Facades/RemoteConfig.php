<?php

namespace Vipertecpro\AppAnalytics\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void setDefaults(array $defaults)
 * @method static array defaults()
 * @method static void fetchAndActivate(?int $minimumFetchInterval = null)
 * @method static void refresh()
 * @method static string|null string(string $key, ?string $default = null)
 * @method static bool bool(string $key, bool $default = false)
 * @method static int int(string $key, int $default = 0)
 * @method static float float(string $key, float $default = 0.0)
 * @method static array json(string $key, array $default = [])
 * @method static string source(string $key)
 * @method static array all()
 *
 * @see \Vipertecpro\AppAnalytics\RemoteConfig
 */
class RemoteConfig extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Vipertecpro\AppAnalytics\RemoteConfig::class;
    }
}
