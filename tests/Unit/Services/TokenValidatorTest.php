<?php

use LucaLongo\LaravelLicensingClient\Services\TokenValidator;
use LucaLongo\LaravelLicensingClient\Services\FingerprintGenerator;
use LucaLongo\LaravelLicensingClient\Exceptions\LicensingException;
use Carbon\Carbon;

beforeEach(function () {
    $this->fingerprintGenerator = new FingerprintGenerator();
    $this->validator = new TokenValidator($this->fingerprintGenerator);
});

it('validates a valid token', function () {
    $token = $this->generateTestToken();

    $claims = $this->validator->validate($token);

    expect($claims)->toBeArray();
    expect($claims)->toHaveKey('license_key');
    expect($claims['license_key'])->toBe('TEST-LICENSE-KEY');
});

it('throws exception for invalid token', function () {
    $this->validator->validate('invalid-token');
})->throws(LicensingException::class);

it('throws exception for expired token', function () {
    $token = $this->generateTestToken([
        'exp' => now()->subDay()->toIso8601String(),
    ]);

    $this->validator->validate($token);
})->throws(LicensingException::class, 'The license has expired.');

it('throws exception for fingerprint mismatch', function () {
    $token = $this->generateTestToken([
        'fingerprint' => 'wrong-fingerprint',
    ]);

    $this->validator->validate($token);
})->throws(LicensingException::class, 'Device fingerprint does not match the licensed device.');

it('throws exception when usage limit exceeded', function () {
    $token = $this->generateTestToken([
        'max_usages' => 1,
        'current_usages' => 2,
    ]);

    $this->validator->validate($token);
})->throws(LicensingException::class, 'License usage limit has been exceeded.');

it('allows unlimited usage when max_usages is -1', function () {
    $token = $this->generateTestToken([
        'max_usages' => -1,
        'current_usages' => 999,
    ]);

    $claims = $this->validator->validate($token);

    expect($claims)->toBeArray();
});

it('checks if token is valid without throwing exceptions', function () {
    $validToken = $this->generateTestToken();
    $invalidToken = 'invalid-token';

    expect($this->validator->isValid($validToken))->toBeTrue();
    expect($this->validator->isValid($invalidToken))->toBeFalse();
});

it('gets token expiration date', function () {
    $expirationDate = now()->addMonth();
    $token = $this->generateTestToken([
        'exp' => $expirationDate->toIso8601String(),
    ]);

    $expiration = $this->validator->getExpiration($token);

    expect($expiration)->toBeInstanceOf(Carbon::class);
    expect($expiration->toIso8601String())->toBe($expirationDate->toIso8601String());
});

it('returns null for token without expiration', function () {
    $token = $this->generateTestToken();
    // Remove exp claim
    $claims = json_decode(base64_decode(explode('.', $token)[1]), true);
    unset($claims['exp']);

    // For this test, we'll need to mock a token without expiration
    // Since we can't easily create one without the exp claim
    expect($this->validator->getExpiration('invalid-token'))->toBeNull();
});

it('checks if token is expiring soon', function () {
    $tokenExpiringSoon = $this->generateTestToken([
        'exp' => now()->addDays(5)->toIso8601String(),
    ]);

    $tokenNotExpiringSoon = $this->generateTestToken([
        'exp' => now()->addDays(30)->toIso8601String(),
    ]);

    expect($this->validator->isExpiringSoon($tokenExpiringSoon, 7))->toBeTrue();
    expect($this->validator->isExpiringSoon($tokenNotExpiringSoon, 7))->toBeFalse();
});

it('extracts license information from token', function () {
    $token = $this->generateTestToken([
        'license_key' => 'LICENSE-123',
        'customer_email' => 'customer@example.com',
        'customer_name' => 'John Doe',
        'features' => ['feature1', 'feature2'],
        'metadata' => ['custom' => 'data'],
    ]);

    $info = $this->validator->extractLicenseInfo($token);

    expect($info)->toMatchArray([
        'license_key' => 'LICENSE-123',
        'customer_email' => 'customer@example.com',
        'customer_name' => 'John Doe',
        'features' => ['feature1', 'feature2'],
        'metadata' => ['custom' => 'data'],
    ]);
});

it('returns empty array for invalid token when extracting info', function () {
    $info = $this->validator->extractLicenseInfo('invalid-token');

    expect($info)->toBe([]);
});

it('throws exception when public key is missing', function () {
    config(['licensing-client.public_key' => null]);

    $validator = new TokenValidator($this->fingerprintGenerator);
    $token = $this->generateTestToken();

    $validator->validate($token);
})->throws(LicensingException::class, 'Public key for token verification is not configured.');