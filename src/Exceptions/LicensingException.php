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

    public static function licenseSuspended(): self
    {
        return new self('The license has been suspended.');
    }

    public static function licenseCancelled(): self
    {
        return new self('The license has been cancelled.');
    }

    public static function fingerprintConflict(): self
    {
        return new self('The fingerprint is already in use by another device.');
    }

    public static function offlineTokenDisabled(): self
    {
        return new self('Offline tokens are not enabled for this license.');
    }

    public static function rateLimited(): self
    {
        return new self('Too many requests to the licensing server. Please try again later.');
    }

    public static function forceOnlineRequired(): self
    {
        return new self('Online verification is required. Please connect to the internet.');
    }

    public static function invalidLicenseStatus(string $status): self
    {
        return new self("License status '{$status}' is not valid for use.");
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
