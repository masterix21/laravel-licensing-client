<?php

namespace LucaLongo\LaravelLicensingClient\Services;

use Carbon\Carbon;
use LucaLongo\LaravelLicensingClient\Exceptions\LicensingException;
use ParagonIE\Paseto\Keys\Base\AsymmetricPublicKey;
use ParagonIE\Paseto\Parser;
use ParagonIE\Paseto\Protocol\Version4;
use ParagonIE\Paseto\ProtocolCollection;

class TokenValidator
{
    protected ?AsymmetricPublicKey $publicKey = null;

    protected Parser $parser;

    public function __construct(
        protected FingerprintGenerator $fingerprintGenerator
    ) {
        $this->initializeParser();
    }

    /**
     * Validate a PASETO token and return its claims
     */
    public function validate(string $token): array
    {
        if (! $this->publicKey) {
            throw LicensingException::publicKeyMissing();
        }

        try {
            $parsedToken = $this->parser->parse($token);
            $claims = $parsedToken->getClaims();

            if (! $this->validateFingerprint($claims)) {
                throw LicensingException::fingerprintMismatch();
            }

            if (! $this->validateExpiration($claims)) {
                throw LicensingException::licenseExpired();
            }

            if (! $this->validateStatus($claims)) {
                throw LicensingException::invalidLicenseStatus($claims['status'] ?? 'unknown');
            }

            if (! $this->validateUsageLimits($claims)) {
                throw LicensingException::usageLimitExceeded();
            }

            return $claims;
        } catch (\Exception $e) {
            if ($e instanceof LicensingException) {
                throw $e;
            }

            if (str_contains($e->getMessage(), 'exp') || str_contains($e->getMessage(), 'expired')) {
                throw LicensingException::licenseExpired();
            }

            throw LicensingException::invalidToken();
        }
    }

    /**
     * Check if a token is valid without throwing exceptions
     */
    public function isValid(string $token): bool
    {
        try {
            $this->validate($token);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if the token requires an online refresh (force_online_after exceeded)
     */
    public function requiresOnlineRefresh(string $token): bool
    {
        try {
            $parsedToken = $this->parser->parse($token);
            $claims = $parsedToken->getClaims();

            if (! isset($claims['force_online_after'])) {
                return false;
            }

            return Carbon::parse($claims['force_online_after'])->isPast();
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Get token expiration time
     */
    public function getExpiration(string $token): ?Carbon
    {
        try {
            $claims = $this->validate($token);

            if (! isset($claims['exp'])) {
                return null;
            }

            return Carbon::parse($claims['exp']);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if token is expiring soon
     */
    public function isExpiringSoon(string $token, int $daysThreshold = 7): bool
    {
        $expiration = $this->getExpiration($token);

        if (! $expiration) {
            return false;
        }

        $now = now();
        $daysUntilExpiration = $now->diffInDays($expiration, false);

        return $daysUntilExpiration > 0 && $daysUntilExpiration <= $daysThreshold;
    }

    /**
     * Extract license information from token claims
     */
    public function extractLicenseInfo(string $token): array
    {
        try {
            $claims = $this->validate($token);

            return [
                'license_id' => $claims['license_id'] ?? null,
                'license_key_hash' => $claims['license_key_hash'] ?? null,
                'status' => $claims['status'] ?? null,
                'max_usages' => $claims['max_usages'] ?? null,
                'expires_at' => $claims['exp'] ?? null,
                'issued_at' => $claims['iat'] ?? null,
                'license_expires_at' => $claims['license_expires_at'] ?? null,
                'force_online_after' => $claims['force_online_after'] ?? null,
                'grace_until' => $claims['grace_until'] ?? null,
                'usage_fingerprint' => $claims['usage_fingerprint'] ?? null,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Initialize the PASETO parser
     */
    protected function initializeParser(): void
    {
        $publicKeyString = config('licensing-client.public_key');

        if (! $publicKeyString) {
            return;
        }

        try {
            $this->publicKey = AsymmetricPublicKey::fromEncodedString($publicKeyString, new Version4);

            $this->parser = Parser::getPublic($this->publicKey, ProtocolCollection::v4());
        } catch (\Exception $e) {
            throw LicensingException::invalidConfiguration('Invalid public key format');
        }
    }

    /**
     * Update the public key used for token validation (for key rotation)
     */
    public function updatePublicKey(string $publicKeyString): void
    {
        try {
            $this->publicKey = AsymmetricPublicKey::fromEncodedString($publicKeyString, new Version4);
            $this->parser = Parser::getPublic($this->publicKey, ProtocolCollection::v4());
        } catch (\Exception $e) {
            throw LicensingException::invalidConfiguration('Invalid public key format');
        }
    }

    /**
     * Validate fingerprint claim matches current device
     */
    protected function validateFingerprint(array $claims): bool
    {
        if (! isset($claims['usage_fingerprint'])) {
            return false;
        }

        $currentFingerprint = $this->fingerprintGenerator->generate();

        return hash_equals($claims['usage_fingerprint'], $currentFingerprint);
    }

    /**
     * Validate token expiration
     */
    protected function validateExpiration(array $claims): bool
    {
        if (! isset($claims['exp'])) {
            return true;
        }

        return Carbon::parse($claims['exp'])->isFuture();
    }

    /**
     * Validate license status is usable (active or grace)
     */
    protected function validateStatus(array $claims): bool
    {
        if (! isset($claims['status'])) {
            return true;
        }

        return in_array($claims['status'], ['active', 'grace'], true);
    }

    /**
     * Validate usage limits
     */
    protected function validateUsageLimits(array $claims): bool
    {
        if (! isset($claims['max_usages'])) {
            return true;
        }

        if ($claims['max_usages'] === -1) {
            return true;
        }

        return true;
    }
}
