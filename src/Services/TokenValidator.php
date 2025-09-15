<?php

namespace LucaLongo\LaravelLicensingClient\Services;

use Carbon\Carbon;
use ParagonIE\Paseto\Keys\AsymmetricPublicKey;
use ParagonIE\Paseto\Parser;
use ParagonIE\Paseto\ProtocolCollection;
use ParagonIE\Paseto\Protocol\Version4;
use ParagonIE\Paseto\Purpose;
use ParagonIE\Paseto\Rules\IssuedBy;
use LucaLongo\LaravelLicensingClient\Exceptions\LicensingException;

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
     * Validate a PASETO token
     */
    public function validate(string $token): array
    {
        if (!$this->publicKey) {
            throw LicensingException::publicKeyMissing();
        }

        try {
            $parsedToken = $this->parser->parse($token);
            $claims = $parsedToken->getClaims();

            // Validate fingerprint
            if (!$this->validateFingerprint($claims)) {
                throw LicensingException::fingerprintMismatch();
            }

            // Validate expiration
            if (!$this->validateExpiration($claims)) {
                throw LicensingException::licenseExpired();
            }

            // Validate usage limits
            if (!$this->validateUsageLimits($claims)) {
                throw LicensingException::usageLimitExceeded();
            }

            return $claims;
        } catch (\Exception $e) {
            if ($e instanceof LicensingException) {
                throw $e;
            }

            // Check if the exception is related to expiration
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
     * Get token expiration time
     */
    public function getExpiration(string $token): ?Carbon
    {
        try {
            $claims = $this->validate($token);

            if (!isset($claims['exp'])) {
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

        if (!$expiration) {
            return false;
        }

        // Check if token is expiring within the threshold but hasn't expired yet
        $now = now();
        $daysUntilExpiration = $now->diffInDays($expiration, false);
        return $daysUntilExpiration > 0 && $daysUntilExpiration <= $daysThreshold;
    }

    /**
     * Extract license information from token
     */
    public function extractLicenseInfo(string $token): array
    {
        try {
            $claims = $this->validate($token);

            return [
                'license_key' => $claims['license_key'] ?? null,
                'customer_email' => $claims['customer_email'] ?? null,
                'customer_name' => $claims['customer_name'] ?? null,
                'expires_at' => $claims['exp'] ?? null,
                'issued_at' => $claims['iat'] ?? null,
                'max_usages' => $claims['max_usages'] ?? null,
                'current_usages' => $claims['current_usages'] ?? null,
                'features' => $claims['features'] ?? [],
                'metadata' => $claims['metadata'] ?? [],
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

        if (!$publicKeyString) {
            return;
        }

        try {
            $this->publicKey = AsymmetricPublicKey::fromEncodedString($publicKeyString, new Version4());

            $this->parser = Parser::getPublic($this->publicKey, ProtocolCollection::v4());
        } catch (\Exception $e) {
            throw LicensingException::invalidConfiguration('Invalid public key format');
        }
    }

    /**
     * Validate fingerprint claim
     */
    protected function validateFingerprint(array $claims): bool
    {
        if (!isset($claims['fingerprint'])) {
            return false;
        }

        $currentFingerprint = $this->fingerprintGenerator->generate();
        return hash_equals($claims['fingerprint'], $currentFingerprint);
    }

    /**
     * Validate expiration
     */
    protected function validateExpiration(array $claims): bool
    {
        if (!isset($claims['exp'])) {
            return true; // No expiration means perpetual license
        }

        return Carbon::parse($claims['exp'])->isFuture();
    }

    /**
     * Validate usage limits
     */
    protected function validateUsageLimits(array $claims): bool
    {
        if (!isset($claims['max_usages']) || !isset($claims['current_usages'])) {
            return true; // No usage limits
        }

        // -1 means unlimited
        if ($claims['max_usages'] === -1) {
            return true;
        }

        return $claims['current_usages'] < $claims['max_usages'];
    }
}