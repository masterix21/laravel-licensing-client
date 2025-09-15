<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use LucaLongo\LaravelLicensingClient\Facades\LaravelLicensingClient;
use LucaLongo\LaravelLicensingClient\Services\TokenStorage;

it('can complete a full license lifecycle', function () {
    // Mock HTTP responses
    Http::fake([
        '*/api/licensing/v1/activate' => Http::response([
            'token' => $this->generateTestToken([
                'license_key' => 'INTEGRATION-TEST-KEY',
                'customer_email' => 'integration@test.com',
                'customer_name' => 'Integration Test',
                'max_usages' => 5,
                'current_usages' => 1,
            ]),
            'success' => true,
        ], 200),

        '*/api/licensing/v1/refresh' => Http::response([
            'token' => $this->generateTestToken([
                'license_key' => 'INTEGRATION-TEST-KEY',
                'exp' => now()->addYear()->toIso8601String(),
            ]),
            'success' => true,
        ], 200),

        '*/api/licensing/v1/heartbeat' => Http::response([
            'success' => true,
        ], 200),

        '*/api/licensing/v1/deactivate' => Http::response([
            'success' => true,
        ], 200),

        '*/api/licensing/v1/health' => Http::response([
            'status' => 'healthy',
        ], 200),
    ]);

    // Test activation
    $activated = LaravelLicensingClient::activate('INTEGRATION-TEST-KEY');
    expect($activated)->toBeTrue();

    // Test validation
    $isValid = LaravelLicensingClient::isValid('INTEGRATION-TEST-KEY');
    expect($isValid)->toBeTrue();

    // Test getting license info
    $info = LaravelLicensingClient::getLicenseInfo('INTEGRATION-TEST-KEY');
    expect($info)->toHaveKey('customer_email');
    expect($info['customer_email'])->toBe('integration@test.com');

    // Test refresh
    $refreshed = LaravelLicensingClient::refresh('INTEGRATION-TEST-KEY');
    expect($refreshed)->toBeTrue();

    // Test heartbeat
    $heartbeat = LaravelLicensingClient::heartbeat('INTEGRATION-TEST-KEY');
    expect($heartbeat)->toBeTrue();

    // Test server health
    $healthy = LaravelLicensingClient::isServerHealthy();
    expect($healthy)->toBeTrue();

    // Test deactivation
    $deactivated = LaravelLicensingClient::deactivate('INTEGRATION-TEST-KEY');
    expect($deactivated)->toBeTrue();

    // Verify license is no longer valid after deactivation
    $isValidAfterDeactivation = LaravelLicensingClient::isValid('INTEGRATION-TEST-KEY');
    expect($isValidAfterDeactivation)->toBeFalse();
});

it('handles grace period when server is unreachable', function () {
    Http::fake([
        '*/api/licensing/v1/*' => Http::response(null, 500),
        '*/api/licensing/v1/health' => Http::response(null, 500),
    ]);

    // Store a valid token first
    $tokenStorage = app(TokenStorage::class);
    $tokenStorage->store($this->generateTestToken(), 'GRACE-TEST-KEY');

    // License should be valid initially
    $isValid = LaravelLicensingClient::isValid('GRACE-TEST-KEY');
    expect($isValid)->toBeTrue();

    // Server is unreachable
    $healthy = LaravelLicensingClient::isServerHealthy();
    expect($healthy)->toBeFalse();

    // Start grace period
    LaravelLicensingClient::startGracePeriod();

    // Should be in grace period
    $inGracePeriod = LaravelLicensingClient::isInGracePeriod();
    expect($inGracePeriod)->toBeTrue();
});

it('handles expired license correctly', function () {
    $expiredToken = $this->generateTestToken([
        'exp' => now()->subDay()->toIso8601String(),
    ]);

    Http::fake([
        '*/api/licensing/v1/activate' => Http::response([
            'token' => $expiredToken,
            'success' => true,
        ], 200),
    ]);

    // Activate with expired token
    LaravelLicensingClient::activate('EXPIRED-KEY');

    // Should not be valid
    $isValid = LaravelLicensingClient::isValid('EXPIRED-KEY');
    expect($isValid)->toBeFalse();
});

it('detects when license is expiring soon', function () {
    $expiringToken = $this->generateTestToken([
        'exp' => now()->addDays(5)->toIso8601String(),
    ]);

    Http::fake([
        '*/api/licensing/v1/activate' => Http::response([
            'token' => $expiringToken,
            'success' => true,
        ], 200),
    ]);

    LaravelLicensingClient::activate('EXPIRING-KEY');

    $isExpiringSoon = LaravelLicensingClient::isExpiringSoon(7, 'EXPIRING-KEY');
    expect($isExpiringSoon)->toBeTrue();

    $isExpiringSoon = LaravelLicensingClient::isExpiringSoon(3, 'EXPIRING-KEY');
    expect($isExpiringSoon)->toBeFalse();
});

it('handles usage limits correctly', function () {
    $limitedToken = $this->generateTestToken([
        'max_usages' => 2,
        'current_usages' => 2,
    ]);

    $tokenStorage = app(TokenStorage::class);
    $tokenStorage->store($limitedToken, 'LIMITED-KEY');

    $isValid = LaravelLicensingClient::isValid('LIMITED-KEY');
    expect($isValid)->toBeFalse();
});

it('clears all stored data', function () {
    $tokenStorage = app(TokenStorage::class);
    $tokenStorage->store($this->generateTestToken(), 'CLEAR-TEST-KEY');

    // Verify token exists
    expect($tokenStorage->exists('CLEAR-TEST-KEY'))->toBeTrue();

    // Clear all data
    LaravelLicensingClient::clearAll();

    // Verify token is gone
    expect($tokenStorage->exists('CLEAR-TEST-KEY'))->toBeFalse();
});

it('can use middleware to protect routes', function () {
    Http::fake([
        '*/api/licensing/v1/activate' => Http::response([
            'token' => $this->generateTestToken(),
            'success' => true,
        ], 200),
    ]);

    LaravelLicensingClient::activate('MIDDLEWARE-TEST-KEY');

    // Create a test route with middleware
    Route::middleware('license')->get('/protected', fn () => 'Protected content');

    $response = $this->get('/protected');
    $response->assertStatus(200);
    $response->assertSee('Protected content');
});

it('blocks access when license is invalid via middleware', function () {
    // Don't activate any license
    config(['licensing-client.license_key' => 'INVALID-KEY']);

    // Mock server as healthy so we don't trigger grace period
    Http::fake([
        '*/api/licensing/v1/health' => Http::response(['status' => 'healthy'], 200),
        '*/api/licensing/v1/refresh' => Http::response(['error' => 'Invalid license'], 404),
    ]);

    Route::middleware('license')->get('/protected', fn () => 'Protected content');

    $response = $this->get('/protected');
    $response->assertStatus(403);
});

it('allows excluded routes without license check', function () {
    config(['licensing-client.excluded_routes' => ['public/*']]);

    Route::middleware('license')->get('/public/page', fn () => 'Public content');

    $response = $this->get('/public/page');
    $response->assertStatus(200);
    $response->assertSee('Public content');
});
