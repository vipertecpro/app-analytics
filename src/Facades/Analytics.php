<?php

namespace Vipertecpro\AppAnalytics\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void logEvent(string $name, array $parameters = [])
 * @method static void logScreenView(string $screenName, ?string $screenClass = null)
 * @method static void setUserProperty(string $name, ?string $value)
 * @method static void setUserId(?string $id)
 * @method static void setCollectionEnabled(bool $enabled)
 * @method static void setConsent(bool $analytics, bool $ads, ?bool $adPersonalization = null)
 * @method static void resetData()
 * @method static array status()
 * @method static bool isConfigured()
 *
 * @see \Vipertecpro\AppAnalytics\Analytics
 */
class Analytics extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Vipertecpro\AppAnalytics\Analytics::class;
    }
}
