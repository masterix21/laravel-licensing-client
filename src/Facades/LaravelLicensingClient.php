<?php

namespace LucaLongo\LaravelLicensingClient\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool activate(?string $licenseKey = null)
 * @method static bool deactivate(?string $licenseKey = null)
 * @method static bool isValid(?string $licenseKey = null)
 * @method static array validate(?string $licenseKey = null)
 * @method static bool refresh(?string $licenseKey = null)
 * @method static bool heartbeat(?string $licenseKey = null)
 * @method static array getLicenseInfo(?string $licenseKey = null)
 * @method static bool isExpiringSoon(int $daysThreshold = 7, ?string $licenseKey = null)
 * @method static void clearAll()
 * @method static bool isInGracePeriod()
 * @method static void startGracePeriod()
 * @method static bool isServerHealthy()
 *
 * @see \LucaLongo\LaravelLicensingClient\LaravelLicensingClient
 */
class LaravelLicensingClient extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \LucaLongo\LaravelLicensingClient\LaravelLicensingClient::class;
    }
}
