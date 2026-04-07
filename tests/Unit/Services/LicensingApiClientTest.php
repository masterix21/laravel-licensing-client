<?php

use Illuminate\Support\Facades\Http;
use LucaLongo\LaravelLicensingClient\Exceptions\LicensingException;
use LucaLongo\LaravelLicensingClient\Services\LicensingApiClient;

beforeEach(function () {
    $this->apiClient = new LicensingApiClient;
});

it('activates a license successfully', function () {
    Http::fake([
        '*/api/licensing/v1/activate' => Http::response([
            'success' => true,
            'data' => [
                'token' => 'activated-token',
                'token_expires_at' => now()->addYear()->toIso8601String(),
                'license' => ['id' => 'ulid-1', 'status' => 'active'],
                'usage' => ['id' => 1, 'fingerprint' => 'fp', 'status' => 'active'],
            ],
        ], 200),
    ]);

    $result = $this->apiClient->activate('LICENSE-KEY', 'fingerprint', ['meta' => 'data']);

    expect($result)->toHaveKey('data');
    expect($result['data']['token'])->toBe('activated-token');
});

it('throws exception for invalid license key during activation', function () {
    Http::fake([
        '*/api/licensing/v1/activate' => Http::response([
            'success' => false,
            'error' => ['code' => 'INVALID_KEY', 'message' => 'License key is invalid or not found'],
        ], 404),
    ]);

    $this->apiClient->activate('INVALID-KEY', 'fingerprint');
})->throws(LicensingException::class, 'The provided license key is invalid.');

it('throws exception when usage limit exceeded during activation', function () {
    Http::fake([
        '*/api/licensing/v1/activate' => Http::response([
            'success' => false,
            'error' => ['code' => 'USAGE_LIMIT_REACHED', 'message' => 'License has reached maximum usages'],
        ], 409),
    ]);

    $this->apiClient->activate('LICENSE-KEY', 'fingerprint');
})->throws(LicensingException::class, 'License usage limit has been exceeded.');

it('throws exception for suspended license during activation', function () {
    Http::fake([
        '*/api/licensing/v1/activate' => Http::response([
            'success' => false,
            'error' => ['code' => 'SUSPENDED_LICENSE', 'message' => 'License is suspended'],
        ], 423),
    ]);

    $this->apiClient->activate('LICENSE-KEY', 'fingerprint');
})->throws(LicensingException::class, 'The license has been suspended.');

it('deactivates a license successfully', function () {
    Http::fake([
        '*/api/licensing/v1/deactivate' => Http::response([
            'success' => true,
            'data' => ['message' => 'Usage revoked successfully'],
        ], 200),
    ]);

    $result = $this->apiClient->deactivate('LICENSE-KEY', 'fingerprint');

    expect($result)->toHaveKey('success');
    expect($result['success'])->toBeTrue();
});

it('deactivates a license with reason', function () {
    Http::fake([
        '*/api/licensing/v1/deactivate' => Http::response([
            'success' => true,
            'data' => ['message' => 'Usage revoked successfully'],
        ], 200),
    ]);

    $result = $this->apiClient->deactivate('LICENSE-KEY', 'fingerprint', 'switching device');

    Http::assertSent(function ($request) {
        return $request['reason'] === 'switching device';
    });

    expect($result['success'])->toBeTrue();
});

it('refreshes a token successfully', function () {
    Http::fake([
        '*/api/licensing/v1/refresh' => Http::response([
            'success' => true,
            'data' => [
                'token' => 'refreshed-token',
                'token_expires_at' => now()->addYear()->toIso8601String(),
                'refresh_after' => now()->addDays(6)->toIso8601String(),
            ],
        ], 200),
    ]);

    $result = $this->apiClient->refresh('LICENSE-KEY', 'fingerprint');

    expect($result)->toHaveKey('data');
    expect($result['data']['token'])->toBe('refreshed-token');
});

it('throws exception for fingerprint mismatch during refresh', function () {
    Http::fake([
        '*/api/licensing/v1/refresh' => Http::response([
            'success' => false,
            'error' => ['code' => 'FINGERPRINT_MISMATCH', 'message' => 'Fingerprint does not match'],
        ], 403),
    ]);

    $this->apiClient->refresh('LICENSE-KEY', 'wrong-fingerprint');
})->throws(LicensingException::class, 'Device fingerprint does not match the licensed device.');

it('sends heartbeat successfully', function () {
    Http::fake([
        '*/api/licensing/v1/heartbeat' => Http::response([
            'success' => true,
            'data' => [
                'usage' => ['id' => 1, 'fingerprint' => 'fp', 'last_seen_at' => now()->toIso8601String()],
            ],
        ], 200),
    ]);

    $result = $this->apiClient->heartbeat('LICENSE-KEY', 'fingerprint', ['data' => 'value']);

    expect($result)->toHaveKey('success');
    expect($result['success'])->toBeTrue();
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
            'success' => true,
            'data' => [
                'license' => ['id' => 'ulid-1', 'status' => 'active'],
                'usage' => ['id' => 1, 'status' => 'active'],
            ],
        ], 200),
    ]);

    $result = $this->apiClient->validate('LICENSE-KEY', 'fingerprint');

    expect($result)->toHaveKey('success');
    expect($result['success'])->toBeTrue();
});

it('throws exception for expired license during validation', function () {
    Http::fake([
        '*/api/licensing/v1/validate' => Http::response([
            'success' => false,
            'error' => ['code' => 'EXPIRED_LICENSE', 'message' => 'License is expired'],
        ], 410),
    ]);

    $this->apiClient->validate('LICENSE-KEY', 'fingerprint');
})->throws(LicensingException::class, 'The license has expired.');

it('gets license information successfully', function () {
    Http::fake([
        '*/api/licensing/v1/licenses/show' => Http::response([
            'success' => true,
            'data' => [
                'license' => [
                    'id' => 'ulid-1',
                    'status' => 'active',
                    'max_usages' => 5,
                    'features' => ['feature_a'],
                    'active_usages' => 2,
                    'available_seats' => 3,
                ],
            ],
        ], 200),
    ]);

    $result = $this->apiClient->getLicenseInfo('LICENSE-KEY', 'fingerprint');

    expect($result)->toHaveKey('data');
    expect($result['data']['license']['status'])->toBe('active');
});

it('checks server health successfully', function () {
    Http::fake([
        '*/api/licensing/v1/health' => Http::response([
            'success' => true,
            'data' => [
                'status' => 'healthy',
                'checks' => ['database' => ['status' => 'ok']],
            ],
        ], 200),
    ]);

    $result = $this->apiClient->health();

    expect($result)->toBeTrue();
});

it('returns false when server is unhealthy', function () {
    Http::fake([
        '*/api/licensing/v1/health' => Http::response([
            'success' => true,
            'data' => [
                'status' => 'degraded',
            ],
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

it('throws rate limited exception on 429', function () {
    Http::fake([
        '*/api/licensing/v1/activate' => Http::response([
            'success' => false,
            'error' => ['code' => 'RATE_LIMITED', 'message' => 'Too many requests'],
        ], 429),
    ]);

    $this->apiClient->activate('LICENSE-KEY', 'fingerprint');
})->throws(LicensingException::class, 'Too many requests to the licensing server.');

it('throws cancelled license exception on 423 with CANCELLED_LICENSE code', function () {
    Http::fake([
        '*/api/licensing/v1/activate' => Http::response([
            'success' => false,
            'error' => ['code' => 'CANCELLED_LICENSE', 'message' => 'Cancelled'],
        ], 423),
    ]);

    $this->apiClient->activate('LICENSE-KEY', 'fingerprint');
})->throws(LicensingException::class, 'The license has been cancelled.');

it('throws fingerprint conflict on 409 with FINGERPRINT_CONFLICT code', function () {
    Http::fake([
        '*/api/licensing/v1/activate' => Http::response([
            'success' => false,
            'error' => ['code' => 'FINGERPRINT_CONFLICT', 'message' => 'Conflict'],
        ], 409),
    ]);

    $this->apiClient->activate('LICENSE-KEY', 'fingerprint');
})->throws(LicensingException::class, 'The fingerprint is already in use by another device.');

it('throws offline token disabled on 409 with OFFLINE_TOKEN_DISABLED code', function () {
    Http::fake([
        '*/api/licensing/v1/refresh' => Http::response([
            'success' => false,
            'error' => ['code' => 'OFFLINE_TOKEN_DISABLED', 'message' => 'Offline tokens disabled'],
        ], 409),
    ]);

    $this->apiClient->refresh('LICENSE-KEY', 'fingerprint');
})->throws(LicensingException::class, 'Offline tokens are not enabled for this license.');

it('throws invalid configuration on 422', function () {
    Http::fake([
        '*/api/licensing/v1/validate' => Http::response([
            'success' => false,
            'error' => ['code' => 'VALIDATION_FAILED', 'message' => 'The fingerprint field is required'],
        ], 422),
    ]);

    $this->apiClient->validate('LICENSE-KEY', 'fingerprint');
})->throws(LicensingException::class, 'The fingerprint field is required');

it('throws expired license on 410 during activation', function () {
    Http::fake([
        '*/api/licensing/v1/activate' => Http::response([
            'success' => false,
            'error' => ['code' => 'EXPIRED_LICENSE', 'message' => 'Expired'],
        ], 410),
    ]);

    $this->apiClient->activate('LICENSE-KEY', 'fingerprint');
})->throws(LicensingException::class, 'The license has expired.');

it('throws not active on 403 without fingerprint mismatch', function () {
    Http::fake([
        '*/api/licensing/v1/validate' => Http::response([
            'success' => false,
            'error' => ['code' => 'LICENSE_NOT_ACTIVE', 'message' => 'Not active'],
        ], 403),
    ]);

    $this->apiClient->validate('LICENSE-KEY', 'fingerprint');
})->throws(LicensingException::class, "License status 'not_active' is not valid for use.");

it('sends deactivate without reason when null', function () {
    Http::fake([
        '*/api/licensing/v1/deactivate' => Http::response([
            'success' => true,
            'data' => ['message' => 'Revoked'],
        ], 200),
    ]);

    $this->apiClient->deactivate('LICENSE-KEY', 'fingerprint');

    Http::assertSent(function ($request) {
        return ! array_key_exists('reason', $request->data());
    });
});
