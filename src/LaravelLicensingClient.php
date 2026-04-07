<?php

namespace LucaLongo\LaravelLicensingClient;

use Carbon\Carbon;
use LucaLongo\LaravelLicensingClient\Exceptions\LicensingException;
use LucaLongo\LaravelLicensingClient\Services\FingerprintGenerator;
use LucaLongo\LaravelLicensingClient\Services\LicensingApiClient;
use LucaLongo\LaravelLicensingClient\Services\TokenStorage;
use LucaLongo\LaravelLicensingClient\Services\TokenValidator;

class LaravelLicensingClient
{
    public function __construct(
        protected FingerprintGenerator $fingerprintGenerator,
        protected LicensingApiClient $apiClient,
        protected TokenStorage $tokenStorage,
        protected TokenValidator $tokenValidator
    ) {}

    /**
     * Activate a license
     */
    public function activate(?string $licenseKey = null): bool
    {
        $licenseKey = $licenseKey ?? config('licensing-client.license_key');

        if (! $licenseKey) {
            throw LicensingException::invalidConfiguration('No license key provided');
        }

        try {
            $fingerprint = $this->fingerprintGenerator->generate();
            $metadata = $this->fingerprintGenerator->getMetadata();

            $response = $this->apiClient->activate($licenseKey, $fingerprint, $metadata);
            $data = $response['data'] ?? [];

            if (! isset($data['token'])) {
                throw LicensingException::activationFailed('No token received from server');
            }

            $this->tokenStorage->store($data['token'], $licenseKey);
            $this->tokenStorage->storeLastHeartbeat();
            $this->storeServerMetadata($data);

            return true;
        } catch (LicensingException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw LicensingException::activationFailed($e->getMessage());
        }
    }

    /**
     * Deactivate the current license
     */
    public function deactivate(?string $licenseKey = null, ?string $reason = null): bool
    {
        $licenseKey = $licenseKey ?? config('licensing-client.license_key');

        if (! $licenseKey) {
            return false;
        }

        try {
            $fingerprint = $this->fingerprintGenerator->generate();
            $this->apiClient->deactivate($licenseKey, $fingerprint, $reason);
            $this->tokenStorage->delete($licenseKey);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if the license is valid (offline only)
     */
    public function isValid(?string $licenseKey = null): bool
    {
        $licenseKey = $licenseKey ?? config('licensing-client.license_key');

        if (! $licenseKey) {
            return false;
        }

        $token = $this->tokenStorage->retrieve($licenseKey);

        if (! $token) {
            return false;
        }

        return $this->tokenValidator->isValid($token);
    }

    /**
     * Validate the license (with exception on failure)
     */
    public function validate(?string $licenseKey = null): array
    {
        $licenseKey = $licenseKey ?? config('licensing-client.license_key');

        if (! $licenseKey) {
            throw LicensingException::invalidConfiguration('No license key provided');
        }

        $token = $this->tokenStorage->retrieve($licenseKey);

        if (! $token) {
            throw LicensingException::licenseNotActivated();
        }

        return $this->tokenValidator->validate($token);
    }

    /**
     * Refresh the license token
     */
    public function refresh(?string $licenseKey = null): bool
    {
        $licenseKey = $licenseKey ?? config('licensing-client.license_key');

        if (! $licenseKey) {
            return false;
        }

        try {
            $fingerprint = $this->fingerprintGenerator->generate();
            $response = $this->apiClient->refresh($licenseKey, $fingerprint);
            $data = $response['data'] ?? [];

            if (! isset($data['token'])) {
                return false;
            }

            $this->tokenStorage->store($data['token'], $licenseKey);
            $this->tokenStorage->storeLastHeartbeat();
            $this->storeServerMetadata($data);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Send heartbeat to server
     */
    public function heartbeat(?string $licenseKey = null): bool
    {
        if (! config('licensing-client.heartbeat.enabled')) {
            return true;
        }

        $licenseKey = $licenseKey ?? config('licensing-client.license_key');

        if (! $licenseKey) {
            return false;
        }

        if (! $this->shouldSendHeartbeat()) {
            return true;
        }

        try {
            $fingerprint = $this->fingerprintGenerator->generate();
            $response = $this->apiClient->heartbeat($licenseKey, $fingerprint, [
                'version' => app()->version(),
                'environment' => app()->environment(),
            ]);

            if ($response['success'] ?? false) {
                $this->tokenStorage->storeLastHeartbeat();
            }

            return $response['success'] ?? false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get license information from stored token
     */
    public function getLicenseInfo(?string $licenseKey = null): array
    {
        $licenseKey = $licenseKey ?? config('licensing-client.license_key');

        if (! $licenseKey) {
            return [];
        }

        $token = $this->tokenStorage->retrieve($licenseKey);

        if (! $token) {
            return [];
        }

        return $this->tokenValidator->extractLicenseInfo($token);
    }

    /**
     * Check if license is expiring soon
     */
    public function isExpiringSoon(int $daysThreshold = 7, ?string $licenseKey = null): bool
    {
        $licenseKey = $licenseKey ?? config('licensing-client.license_key');

        if (! $licenseKey) {
            return false;
        }

        $token = $this->tokenStorage->retrieve($licenseKey);

        if (! $token) {
            return false;
        }

        return $this->tokenValidator->isExpiringSoon($token, $daysThreshold);
    }

    /**
     * Check if the token requires an online refresh (force_online_after exceeded)
     */
    public function requiresOnlineRefresh(?string $licenseKey = null): bool
    {
        $licenseKey = $licenseKey ?? config('licensing-client.license_key');

        if (! $licenseKey) {
            return false;
        }

        $token = $this->tokenStorage->retrieve($licenseKey);

        if (! $token) {
            return false;
        }

        return $this->tokenValidator->requiresOnlineRefresh($token);
    }

    /**
     * Check if a proactive refresh should be done based on refresh_after
     */
    public function shouldRefreshProactively(): bool
    {
        $refreshAfter = $this->tokenStorage->getRefreshAfter();

        if (! $refreshAfter) {
            return false;
        }

        return Carbon::parse($refreshAfter)->isPast();
    }

    /**
     * Clear all stored license data
     */
    public function clearAll(): void
    {
        $this->tokenStorage->clearAll();
    }

    /**
     * Check if we're in grace period (client-side, server unreachable)
     */
    public function isInGracePeriod(): bool
    {
        $gracePeriodData = $this->tokenStorage->getGracePeriodData();

        if (! $gracePeriodData) {
            return false;
        }

        $gracePeriodDays = config('licensing-client.grace_period_days', 7);
        $gracePeriodEnd = Carbon::parse($gracePeriodData['started_at'])
            ->addDays($gracePeriodDays);

        return $gracePeriodEnd->isFuture();
    }

    /**
     * Start grace period
     */
    public function startGracePeriod(): void
    {
        $this->tokenStorage->storeGracePeriodData([
            'started_at' => now()->toIso8601String(),
            'reason' => 'server_unreachable',
        ]);
    }

    /**
     * Check server health
     */
    public function isServerHealthy(): bool
    {
        return $this->apiClient->health();
    }

    /**
     * Store metadata from server response (public_key_bundle, refresh_after)
     */
    protected function storeServerMetadata(array $data): void
    {
        if (isset($data['public_key_bundle'])) {
            $this->tokenStorage->storePublicKeyBundle($data['public_key_bundle']);
        }

        if (isset($data['refresh_after'])) {
            $this->tokenStorage->storeRefreshAfter($data['refresh_after']);
        }
    }

    /**
     * Check if heartbeat should be sent
     */
    protected function shouldSendHeartbeat(): bool
    {
        $lastHeartbeat = $this->tokenStorage->getLastHeartbeat();

        if (! $lastHeartbeat) {
            return true;
        }

        $interval = config('licensing-client.heartbeat.interval', 3600);

        return time() - $lastHeartbeat >= $interval;
    }
}
