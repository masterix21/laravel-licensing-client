<?php

namespace LucaLongo\LaravelLicensingClient\Exceptions;

use Exception;

class LicensingException extends Exception
{
    public static function invalidLicenseKey(): self
    {
        return new self('The provided license key is invalid.');
    }

    public static function licenseExpired(): self
    {
        return new self('The license has expired.');
    }

    public static function licenseNotActivated(): self
    {
        return new self('The license has not been activated.');
    }

    public static function serverUnreachable(): self
    {
        return new self('Unable to reach the licensing server.');
    }

    public static function invalidToken(): self
    {
        return new self('The license token is invalid or corrupted.');
    }

    public static function fingerprintMismatch(): self
    {
        return new self('Device fingerprint does not match the licensed device.');
    }

    public static function usageLimitExceeded(): self
    {
        return new self('License usage limit has been exceeded.');
    }

    public static function invalidConfiguration(string $message): self
    {
        return new self("Invalid configuration: {$message}");
    }

    public static function activationFailed(string $message): self
    {
        return new self("License activation failed: {$message}");
    }

    public static function deactivationFailed(string $message): self
    {
        return new self("License deactivation failed: {$message}");
    }

    public static function tokenStorageFailed(string $message): self
    {
        return new self("Failed to store license token: {$message}");
    }

    public static function publicKeyMissing(): self
    {
        return new self('Public key for token verification is not configured.');
    }
}
