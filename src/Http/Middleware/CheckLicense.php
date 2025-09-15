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
        // Check if route is excluded
        if ($this->isExcludedRoute($request)) {
            return $next($request);
        }

        $licenseKey = config('licensing-client.license_key');

        try {
            // Try to validate the license
            if (! $this->client->isValid($licenseKey)) {
                // Try to refresh the token
                if (! $this->client->refresh($licenseKey)) {
                    // Check if we're in grace period
                    if (! $this->client->isInGracePeriod()) {
                        // Server might be unreachable, check health
                        if (! $this->client->isServerHealthy()) {
                            $this->client->startGracePeriod();

                            return $next($request);
                        }

                        return $this->handleInvalidLicense($request);
                    }
                }
            }

            // Send heartbeat if needed
            $this->client->heartbeat($licenseKey);

            // Check if license is expiring soon
            if ($this->client->isExpiringSoon(7, $licenseKey)) {
                $this->addExpirationWarning($request);
            }

            return $next($request);
        } catch (LicensingException $e) {
            return $this->handleLicenseException($request, $e);
        }
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
        $expiresAt = $licenseInfo['expires_at'] ?? null;

        if ($expiresAt) {
            $request->attributes->set('license_expiring_soon', true);
            $request->attributes->set('license_expires_at', $expiresAt);
        }
    }
}
