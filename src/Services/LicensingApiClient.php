<?php

namespace LucaLongo\LaravelLicensingClient\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

            throw $this->mapRequestException($e, 'activation');
        }
    }

    /**
     * Deactivate a license
     */
    public function deactivate(string $licenseKey, string $fingerprint, ?string $reason = null): array
    {
        try {
            $payload = [
                'license_key' => $licenseKey,
                'fingerprint' => $fingerprint,
            ];

            if ($reason !== null) {
                $payload['reason'] = $reason;
            }

            $response = $this->makeRequest()
                ->post($this->getEndpoint('deactivate'), $payload);

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

            throw $this->mapRequestException($e, 'refresh');
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

            throw $this->mapRequestException($e, 'validation');
        }
    }

    /**
     * Get license information
     */
    public function getLicenseInfo(string $licenseKey, string $fingerprint): array
    {
        try {
            $response = $this->makeRequest()
                ->post($this->getEndpoint('licenses/show'), [
                    'license_key' => $licenseKey,
                    'fingerprint' => $fingerprint,
                ]);

            $response->throw();

            return $response->json();
        } catch (RequestException $e) {
            $this->logError('Failed to get license info', $e);

            throw $this->mapRequestException($e, 'info');
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

            return ($data['data']['status'] ?? null) === 'healthy';
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
        return "/api/licensing/{$this->apiVersion}/".ltrim($path, '/');
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
     * Map a request exception to the appropriate LicensingException
     */
    protected function mapRequestException(RequestException $e, string $context): LicensingException
    {
        $status = $e->response->status();
        $errorCode = $e->response->json('error.code');

        if ($status === 429) {
            return LicensingException::rateLimited();
        }

        if ($status === 404) {
            return LicensingException::invalidLicenseKey();
        }

        if ($status === 410) {
            return LicensingException::licenseExpired();
        }

        if ($status === 423) {
            if ($errorCode === 'CANCELLED_LICENSE') {
                return LicensingException::licenseCancelled();
            }

            return LicensingException::licenseSuspended();
        }

        if ($status === 403) {
            if ($errorCode === 'FINGERPRINT_MISMATCH') {
                return LicensingException::fingerprintMismatch();
            }

            return LicensingException::invalidLicenseStatus('not_active');
        }

        if ($status === 409) {
            if ($errorCode === 'FINGERPRINT_CONFLICT') {
                return LicensingException::fingerprintConflict();
            }

            if ($errorCode === 'OFFLINE_TOKEN_DISABLED') {
                return LicensingException::offlineTokenDisabled();
            }

            return LicensingException::usageLimitExceeded();
        }

        if ($status === 400) {
            $message = $e->response->json('error.message') ?? 'Request failed';

            return LicensingException::activationFailed($message);
        }

        if ($status === 422) {
            $message = $e->response->json('error.message') ?? 'Validation failed';

            return LicensingException::invalidConfiguration($message);
        }

        if ($status >= 500) {
            return LicensingException::serverUnreachable();
        }

        return match ($context) {
            'activation' => LicensingException::activationFailed($e->getMessage()),
            'refresh' => LicensingException::serverUnreachable(),
            default => LicensingException::serverUnreachable(),
        };
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
