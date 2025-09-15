<?php

use LucaLongo\LaravelLicensingClient\Services\LicensingApiClient;
use LucaLongo\LaravelLicensingClient\Exceptions\LicensingException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->apiClient = new LicensingApiClient();
});

it('activates a license successfully', function () {
    Http::fake([
        '*/api/licensing/v1/activate' => Http::response([
            'token' => 'activated-token',
            'expires_at' => now()->addYear()->toIso8601String(),
        ], 200),
    ]);

    $result = $this->apiClient->activate('LICENSE-KEY', 'fingerprint', ['meta' => 'data']);

    expect($result)->toHaveKey('token');
    expect($result['token'])->toBe('activated-token');
});

it('throws exception for invalid license key during activation', function () {
    Http::fake([
        '*/api/licensing/v1/activate' => Http::response([
            'error' => 'License not found',
        ], 404),
    ]);

    $this->apiClient->activate('INVALID-KEY', 'fingerprint');
})->throws(LicensingException::class, 'The provided license key is invalid.');

it('throws exception when usage limit exceeded during activation', function () {
    Http::fake([
        '*/api/licensing/v1/activate' => Http::response([
            'error' => 'Usage limit exceeded',
        ], 409),
    ]);

    $this->apiClient->activate('LICENSE-KEY', 'fingerprint');
})->throws(LicensingException::class, 'License usage limit has been exceeded.');

it('deactivates a license successfully', function () {
    Http::fake([
        '*/api/licensing/v1/deactivate' => Http::response([
            'success' => true,
        ], 200),
    ]);

    $result = $this->apiClient->deactivate('LICENSE-KEY', 'fingerprint');

    expect($result)->toHaveKey('success');
    expect($result['success'])->toBeTrue();
});

it('refreshes a token successfully', function () {
    Http::fake([
        '*/api/licensing/v1/refresh' => Http::response([
            'token' => 'refreshed-token',
            'expires_at' => now()->addYear()->toIso8601String(),
        ], 200),
    ]);

    $result = $this->apiClient->refresh('LICENSE-KEY', 'fingerprint');

    expect($result)->toHaveKey('token');
    expect($result['token'])->toBe('refreshed-token');
});

it('throws exception for fingerprint mismatch during refresh', function () {
    Http::fake([
        '*/api/licensing/v1/refresh' => Http::response([
            'error' => 'Fingerprint mismatch',
        ], 403),
    ]);

    $this->apiClient->refresh('LICENSE-KEY', 'wrong-fingerprint');
})->throws(LicensingException::class, 'Device fingerprint does not match the licensed device.');

it('sends heartbeat successfully', function () {
    Http::fake([
        '*/api/licensing/v1/heartbeat' => Http::response([
            'success' => true,
            'token' => 'updated-token',
        ], 200),
    ]);

    $result = $this->apiClient->heartbeat('LICENSE-KEY', 'fingerprint', ['data' => 'value']);

    expect($result)->toHaveKey('success');
    expect($result['success'])->toBeTrue();
    expect($result)->toHaveKey('token');
});

it('returns error array on heartbeat failure without throwing', function () {
    Http::fake([
        '*/api/licensing/v1/heartbeat' => Http::response(null, 500),
    ]);

    $result = $this->apiClient->heartbeat('LICENSE-KEY', 'fingerprint');

    expect($result)->toHaveKey('success');
    expect($result['success'])->toBeFalse();
    expect($result)->toHaveKey('error');
});

it('validates a license successfully', function () {
    Http::fake([
        '*/api/licensing/v1/validate' => Http::response([
            'valid' => true,
            'expires_at' => now()->addYear()->toIso8601String(),
        ], 200),
    ]);

    $result = $this->apiClient->validate('LICENSE-KEY', 'fingerprint');

    expect($result)->toHaveKey('valid');
    expect($result['valid'])->toBeTrue();
});

it('throws exception for expired license during validation', function () {
    Http::fake([
        '*/api/licensing/v1/validate' => Http::response([
            'error' => 'License expired',
        ], 410),
    ]);

    $this->apiClient->validate('LICENSE-KEY', 'fingerprint');
})->throws(LicensingException::class, 'The license has expired.');

it('gets license information successfully', function () {
    Http::fake([
        '*/api/licensing/v1/licenses/*' => Http::response([
            'license_key' => 'LICENSE-KEY',
            'customer_email' => 'customer@example.com',
            'expires_at' => now()->addYear()->toIso8601String(),
        ], 200),
    ]);

    $result = $this->apiClient->getLicenseInfo('LICENSE-KEY');

    expect($result)->toHaveKey('license_key');
    expect($result['customer_email'])->toBe('customer@example.com');
});

it('checks server health successfully', function () {
    Http::fake([
        '*/api/licensing/v1/health' => Http::response([
            'status' => 'healthy',
        ], 200),
    ]);

    $result = $this->apiClient->health();

    expect($result)->toBeTrue();
});

it('returns false when server is unhealthy', function () {
    Http::fake([
        '*/api/licensing/v1/health' => Http::response([
            'status' => 'unhealthy',
        ], 200),
    ]);

    $result = $this->apiClient->health();

    expect($result)->toBeFalse();
});

it('returns false on health check failure', function () {
    Http::fake([
        '*/api/licensing/v1/health' => Http::response(null, 500),
    ]);

    $result = $this->apiClient->health();

    expect($result)->toBeFalse();
});