<?php

namespace LucaLongo\LaravelLicensingClient\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LucaLongo\LaravelLicensingClient\Exceptions\LicensingException;
use LucaLongo\LaravelLicensingClient\LaravelLicensingClient;

class CheckLicense
{
    public function __construct(
        protected LaravelLicensingClient $client
    ) {}

    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if ($this->isExcludedRoute($request)) {
            return $next($request);
        }

        $licenseKey = config('licensing-client.license_key');

        try {
            if ($this->client->isValid($licenseKey)) {
                // Token is valid offline, but check if online refresh is required
                if ($this->client->requiresOnlineRefresh($licenseKey)) {
                    if (! $this->client->refresh($licenseKey)) {
                        return $this->handleInvalidLicense($request);
                    }
                }

                return $this->continueWithValidLicense($request, $next, $licenseKey);
            }

            // Try to refresh the token
            if ($this->client->refresh($licenseKey)) {
                return $this->continueWithValidLicense($request, $next, $licenseKey);
            }

            // Check if we're in grace period (client-side, server unreachable)
            if ($this->client->isInGracePeriod()) {
                return $this->continueWithValidLicense($request, $next, $licenseKey);
            }

            // Server might be unreachable, check health
            if (! $this->client->isServerHealthy()) {
                $this->client->startGracePeriod();

                return $next($request);
            }

            return $this->handleInvalidLicense($request);
        } catch (LicensingException $e) {
            return $this->handleLicenseException($request, $e);
        }
    }

    /**
     * Continue with valid license processing
     */
    protected function continueWithValidLicense(Request $request, Closure $next, string $licenseKey): mixed
    {
        $this->client->heartbeat($licenseKey);

        if ($this->client->isExpiringSoon(7, $licenseKey)) {
            $this->addExpirationWarning($request);
        }

        return $next($request);
    }

    /**
     * Check if the current route is excluded
     */
    protected function isExcludedRoute(Request $request): bool
    {
        $excludedRoutes = config('licensing-client.excluded_routes', []);

        foreach ($excludedRoutes as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle invalid license
     */
    protected function handleInvalidLicense(Request $request): mixed
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Invalid or expired license',
                'code' => 'LICENSE_INVALID',
            ], 403);
        }

        abort(403, 'Invalid or expired license');
    }

    /**
     * Handle license exception
     */
    protected function handleLicenseException(Request $request, LicensingException $e): mixed
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => $e->getMessage(),
                'code' => 'LICENSE_ERROR',
            ], 403);
        }

        abort(403, $e->getMessage());
    }

    /**
     * Add expiration warning to request
     */
    protected function addExpirationWarning(Request $request): void
    {
        $licenseKey = config('licensing-client.license_key');
        $licenseInfo = $this->client->getLicenseInfo($licenseKey);
        $expiresAt = $licenseInfo['expires_at'] ?? $licenseInfo['license_expires_at'] ?? null;

        if ($expiresAt) {
            $request->attributes->set('license_expiring_soon', true);
            $request->attributes->set('license_expires_at', $expiresAt);
        }
    }
}
