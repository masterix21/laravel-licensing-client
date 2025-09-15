<?php

namespace LucaLongo\LaravelLicensingClient;

use LucaLongo\LaravelLicensingClient\Exceptions\LicensingException;
use LucaLongo\LaravelLicensingClient\Services\{
    FingerprintGenerator,
    LicensingApiClient,
    TokenStorage,
    TokenValidator
};

class LaravelLicensingClient
{
    public function __construct(
        protected FingerprintGenerator $fingerprintGenerator,
        protected LicensingApiClient $apiClient,
        protected TokenStorage $tokenStorage,
        protected TokenValidator $tokenValidator
    ) {
    }

    /**
     * Activate a license
     */
    public function activate(?string $licenseKey = null): bool
    {
        $licenseKey = $licenseKey ?? config('licensing-client.license_key');

        if (!$licenseKey) {
            throw LicensingException::invalidConfiguration('No license key provided');
        }

        try {
            $fingerprint = $this->fingerprintGenerator->generate();
            $metadata = $this->fingerprintGenerator->getMetadata();

            $response = $this->apiClient->activate($licenseKey, $fingerprint, $metadata);

            if (!isset($response['token'])) {
                throw LicensingException::activationFailed('No token received from server');
            }

            $this->tokenStorage->store($response['token'], $licenseKey);
            $this->tokenStorage->storeLastHeartbeat();

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
    public function deactivate(?string $licenseKey = null): bool
    {
        $licenseKey = $licenseKey ?? config('licensing-client.license_key');

        if (!$licenseKey) {
            return false;
        }

        try {
            $fingerprint = $this->fingerprintGenerator->generate();
            $this->apiClient->deactivate($licenseKey, $fingerprint);
            $this->tokenStorage->delete($licenseKey);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if the license is valid
     */
    public function isValid(?string $licenseKey = null): bool
    {
        $licenseKey = $licenseKey ?? config('licensing-client.license_key');

        if (!$licenseKey) {
            return false;
        }

        // Try offline validation first
        $token = $this->tokenStorage->retrieve($licenseKey);

        if (!$token) {
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

        if (!$licenseKey) {
            throw LicensingException::invalidConfiguration('No license key provided');
        }

        $token = $this->tokenStorage->retrieve($licenseKey);

        if (!$token) {
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

        if (!$licenseKey) {
            return false;
        }

        try {
            $fingerprint = $this->fingerprintGenerator->generate();
            $response = $this->apiClient->refresh($licenseKey, $fingerprint);

            if (!isset($response['token'])) {
                return false;
            }

            $this->tokenStorage->store($response['token'], $licenseKey);
            $this->tokenStorage->storeLastHeartbeat();

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
        if (!config('licensing-client.heartbeat.enabled')) {
            return true;
        }

        $licenseKey = $licenseKey ?? config('licensing-client.license_key');

        if (!$licenseKey) {
            return false;
        }

        // Check if heartbeat is needed
        if (!$this->shouldSendHeartbeat()) {
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

                // Refresh token if provided
                if (isset($response['token'])) {
                    $this->tokenStorage->store($response['token'], $licenseKey);
                }
            }

            return $response['success'] ?? false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get license information
     */
    public function getLicenseInfo(?string $licenseKey = null): array
    {
        $licenseKey = $licenseKey ?? config('licensing-client.license_key');

        if (!$licenseKey) {
            return [];
        }

        $token = $this->tokenStorage->retrieve($licenseKey);

        if (!$token) {
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

        if (!$licenseKey) {
            return false;
        }

        $token = $this->tokenStorage->retrieve($licenseKey);

        if (!$token) {
            return false;
        }

        return $this->tokenValidator->isExpiringSoon($token, $daysThreshold);
    }

    /**
     * Clear all stored license data
     */
    public function clearAll(): void
    {
        $this->tokenStorage->clearAll();
    }

    /**
     * Check if we're in grace period
     */
    public function isInGracePeriod(): bool
    {
        $gracePeriodData = $this->tokenStorage->getGracePeriodData();

        if (!$gracePeriodData) {
            return false;
        }

        $gracePeriodDays = config('licensing-client.grace_period_days', 7);
        $gracePeriodEnd = \Carbon\Carbon::parse($gracePeriodData['started_at'])
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
     * Check if heartbeat should be sent
     */
    protected function shouldSendHeartbeat(): bool
    {
        $lastHeartbeat = $this->tokenStorage->getLastHeartbeat();

        if (!$lastHeartbeat) {
            return true;
        }

        $interval = config('licensing-client.heartbeat.interval', 3600);
        return time() - $lastHeartbeat >= $interval;
    }
}
