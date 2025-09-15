<?php

namespace LucaLongo\LaravelLicensingClient\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;
use LucaLongo\LaravelLicensingClient\Exceptions\LicensingException;

class LicensingApiClient
{
    protected string $baseUrl;
    protected string $apiVersion;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('licensing-client.server_url'), '/');
        $this->apiVersion = config('licensing-client.api_version', 'v1');
        $this->timeout = config('licensing-client.timeout', 30);
    }

    /**
     * Activate a license
     */
    public function activate(string $licenseKey, string $fingerprint, array $metadata = []): array
    {
        try {
            $response = $this->makeRequest()
                ->post($this->getEndpoint('activate'), [
                    'license_key' => $licenseKey,
                    'fingerprint' => $fingerprint,
                    'metadata' => $metadata,
                ]);

            $response->throw();

            return $response->json();
        } catch (RequestException $e) {
            $this->logError('License activation failed', $e);

            if ($e->response->status() === 404) {
                throw LicensingException::invalidLicenseKey();
            }

            if ($e->response->status() === 409) {
                throw LicensingException::usageLimitExceeded();
            }

            throw LicensingException::activationFailed($e->getMessage());
        }
    }

    /**
     * Deactivate a license
     */
    public function deactivate(string $licenseKey, string $fingerprint): array
    {
        try {
            $response = $this->makeRequest()
                ->post($this->getEndpoint('deactivate'), [
                    'license_key' => $licenseKey,
                    'fingerprint' => $fingerprint,
                ]);

            $response->throw();

            return $response->json();
        } catch (RequestException $e) {
            $this->logError('License deactivation failed', $e);
            throw LicensingException::deactivationFailed($e->getMessage());
        }
    }

    /**
     * Refresh a license token
     */
    public function refresh(string $licenseKey, string $fingerprint): array
    {
        try {
            $response = $this->makeRequest()
                ->post($this->getEndpoint('refresh'), [
                    'license_key' => $licenseKey,
                    'fingerprint' => $fingerprint,
                ]);

            $response->throw();

            return $response->json();
        } catch (RequestException $e) {
            $this->logError('Token refresh failed', $e);

            if ($e->response->status() === 404) {
                throw LicensingException::invalidLicenseKey();
            }

            if ($e->response->status() === 403) {
                throw LicensingException::fingerprintMismatch();
            }

            throw LicensingException::serverUnreachable();
        }
    }

    /**
     * Send heartbeat
     */
    public function heartbeat(string $licenseKey, string $fingerprint, array $data = []): array
    {
        try {
            $response = $this->makeRequest()
                ->post($this->getEndpoint('heartbeat'), [
                    'license_key' => $licenseKey,
                    'fingerprint' => $fingerprint,
                    'data' => $data,
                ]);

            $response->throw();

            return $response->json();
        } catch (RequestException $e) {
            $this->logError('Heartbeat failed', $e);
            // Don't throw exception for heartbeat failures
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Validate a license
     */
    public function validate(string $licenseKey, string $fingerprint): array
    {
        try {
            $response = $this->makeRequest()
                ->post($this->getEndpoint('validate'), [
                    'license_key' => $licenseKey,
                    'fingerprint' => $fingerprint,
                ]);

            $response->throw();

            return $response->json();
        } catch (RequestException $e) {
            $this->logError('License validation failed', $e);

            if ($e->response->status() === 404) {
                throw LicensingException::invalidLicenseKey();
            }

            if ($e->response->status() === 403) {
                throw LicensingException::fingerprintMismatch();
            }

            if ($e->response->status() === 410) {
                throw LicensingException::licenseExpired();
            }

            throw LicensingException::serverUnreachable();
        }
    }

    /**
     * Get license information
     */
    public function getLicenseInfo(string $licenseKey): array
    {
        try {
            $response = $this->makeRequest()
                ->get($this->getEndpoint("licenses/{$licenseKey}"));

            $response->throw();

            return $response->json();
        } catch (RequestException $e) {
            $this->logError('Failed to get license info', $e);

            if ($e->response->status() === 404) {
                throw LicensingException::invalidLicenseKey();
            }

            throw LicensingException::serverUnreachable();
        }
    }

    /**
     * Check server health
     */
    public function health(): bool
    {
        try {
            $response = $this->makeRequest()
                ->get($this->getEndpoint('health'));

            $data = $response->json();

            return $data['status'] === 'healthy';
        } catch (\Exception $e) {
            $this->logError('Health check failed', $e);
            return false;
        }
    }

    /**
     * Get the full endpoint URL
     */
    protected function getEndpoint(string $path): string
    {
        return "/api/licensing/{$this->apiVersion}/" . ltrim($path, '/');
    }

    /**
     * Create HTTP request instance
     */
    protected function makeRequest()
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);
    }

    /**
     * Log error if debug mode is enabled
     */
    protected function logError(string $message, \Exception $exception): void
    {
        if (config('licensing-client.debug')) {
            Log::error($message, [
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }
}