<?php

use LucaLongo\LaravelLicensingClient\LaravelLicensingClient;
use LucaLongo\LaravelLicensingClient\Services\{
    FingerprintGenerator,
    LicensingApiClient,
    TokenStorage,
    TokenValidator
};
use LucaLongo\LaravelLicensingClient\Exceptions\LicensingException;
use Mockery\MockInterface;

beforeEach(function () {
    $this->fingerprintGenerator = Mockery::mock(FingerprintGenerator::class);
    $this->apiClient = Mockery::mock(LicensingApiClient::class);
    $this->tokenStorage = Mockery::mock(TokenStorage::class);
    $this->tokenValidator = Mockery::mock(TokenValidator::class);

    $this->client = new LaravelLicensingClient(
        $this->fingerprintGenerator,
        $this->apiClient,
        $this->tokenStorage,
        $this->tokenValidator
    );

    $this->fingerprintGenerator->shouldReceive('generate')
        ->andReturn('test-fingerprint')
        ->byDefault();
});

it('activates a license successfully', function () {
    config(['licensing-client.license_key' => 'TEST-KEY']);

    $this->fingerprintGenerator->shouldReceive('getMetadata')
        ->once()
        ->andReturn(['meta' => 'data']);

    $this->apiClient->shouldReceive('activate')
        ->with('TEST-KEY', 'test-fingerprint', ['meta' => 'data'])
        ->once()
        ->andReturn(['token' => 'activated-token']);

    $this->tokenStorage->shouldReceive('store')
        ->with('activated-token', 'TEST-KEY')
        ->once();

    $this->tokenStorage->shouldReceive('storeLastHeartbeat')
        ->once();

    $result = $this->client->activate();

    expect($result)->toBeTrue();
});

it('throws exception when no license key provided for activation', function () {
    config(['licensing-client.license_key' => null]);

    $this->client->activate();
})->throws(LicensingException::class, 'No license key provided');

it('deactivates a license successfully', function () {
    config(['licensing-client.license_key' => 'TEST-KEY']);

    $this->apiClient->shouldReceive('deactivate')
        ->with('TEST-KEY', 'test-fingerprint')
        ->once();

    $this->tokenStorage->shouldReceive('delete')
        ->with('TEST-KEY')
        ->once();

    $result = $this->client->deactivate();

    expect($result)->toBeTrue();
});

it('checks if license is valid', function () {
    config(['licensing-client.license_key' => 'TEST-KEY']);

    $this->tokenStorage->shouldReceive('retrieve')
        ->with('TEST-KEY')
        ->once()
        ->andReturn('valid-token');

    $this->tokenValidator->shouldReceive('isValid')
        ->with('valid-token')
        ->once()
        ->andReturn(true);

    $result = $this->client->isValid();

    expect($result)->toBeTrue();
});

it('returns false when no token stored', function () {
    config(['licensing-client.license_key' => 'TEST-KEY']);

    $this->tokenStorage->shouldReceive('retrieve')
        ->with('TEST-KEY')
        ->once()
        ->andReturn(null);

    $result = $this->client->isValid();

    expect($result)->toBeFalse();
});

it('validates license with exception on failure', function () {
    config(['licensing-client.license_key' => 'TEST-KEY']);

    $this->tokenStorage->shouldReceive('retrieve')
        ->with('TEST-KEY')
        ->once()
        ->andReturn('valid-token');

    $this->tokenValidator->shouldReceive('validate')
        ->with('valid-token')
        ->once()
        ->andReturn(['license' => 'data']);

    $result = $this->client->validate();

    expect($result)->toBe(['license' => 'data']);
});

it('throws exception when validating without stored token', function () {
    config(['licensing-client.license_key' => 'TEST-KEY']);

    $this->tokenStorage->shouldReceive('retrieve')
        ->with('TEST-KEY')
        ->once()
        ->andReturn(null);

    $this->client->validate();
})->throws(LicensingException::class, 'The license has not been activated.');

it('refreshes a license token', function () {
    config(['licensing-client.license_key' => 'TEST-KEY']);

    $this->apiClient->shouldReceive('refresh')
        ->with('TEST-KEY', 'test-fingerprint')
        ->once()
        ->andReturn(['token' => 'refreshed-token']);

    $this->tokenStorage->shouldReceive('store')
        ->with('refreshed-token', 'TEST-KEY')
        ->once();

    $this->tokenStorage->shouldReceive('storeLastHeartbeat')
        ->once();

    $result = $this->client->refresh();

    expect($result)->toBeTrue();
});

it('sends heartbeat when enabled', function () {
    config(['licensing-client.license_key' => 'TEST-KEY']);
    config(['licensing-client.heartbeat.enabled' => true]);

    $this->tokenStorage->shouldReceive('getLastHeartbeat')
        ->once()
        ->andReturn(null); // Force heartbeat to be needed

    $this->apiClient->shouldReceive('heartbeat')
        ->once()
        ->andReturn(['success' => true, 'token' => 'new-token']);

    $this->tokenStorage->shouldReceive('storeLastHeartbeat')
        ->once();

    $this->tokenStorage->shouldReceive('store')
        ->with('new-token', 'TEST-KEY')
        ->once();

    $result = $this->client->heartbeat();

    expect($result)->toBeTrue();
});

it('skips heartbeat when disabled', function () {
    config(['licensing-client.heartbeat.enabled' => false]);

    $this->apiClient->shouldNotReceive('heartbeat');

    $result = $this->client->heartbeat();

    expect($result)->toBeTrue();
});

it('gets license information', function () {
    config(['licensing-client.license_key' => 'TEST-KEY']);

    $this->tokenStorage->shouldReceive('retrieve')
        ->with('TEST-KEY')
        ->once()
        ->andReturn('valid-token');

    $licenseInfo = [
        'customer_email' => 'test@example.com',
        'expires_at' => now()->addYear()->toIso8601String(),
    ];

    $this->tokenValidator->shouldReceive('extractLicenseInfo')
        ->with('valid-token')
        ->once()
        ->andReturn($licenseInfo);

    $result = $this->client->getLicenseInfo();

    expect($result)->toBe($licenseInfo);
});

it('checks if license is expiring soon', function () {
    config(['licensing-client.license_key' => 'TEST-KEY']);

    $this->tokenStorage->shouldReceive('retrieve')
        ->with('TEST-KEY')
        ->once()
        ->andReturn('valid-token');

    $this->tokenValidator->shouldReceive('isExpiringSoon')
        ->with('valid-token', 7)
        ->once()
        ->andReturn(true);

    $result = $this->client->isExpiringSoon(7);

    expect($result)->toBeTrue();
});

it('clears all stored data', function () {
    $this->tokenStorage->shouldReceive('clearAll')
        ->once();

    $this->client->clearAll();
});

it('checks if in grace period', function () {
    $this->tokenStorage->shouldReceive('getGracePeriodData')
        ->once()
        ->andReturn([
            'started_at' => now()->subDay()->toIso8601String(),
        ]);

    config(['licensing-client.grace_period_days' => 7]);

    $result = $this->client->isInGracePeriod();

    expect($result)->toBeTrue();
});

it('starts grace period', function () {
    $this->tokenStorage->shouldReceive('storeGracePeriodData')
        ->once()
        ->with(Mockery::on(function ($data) {
            return isset($data['started_at']) && isset($data['reason']);
        }));

    $this->client->startGracePeriod();
});

it('checks server health', function () {
    $this->apiClient->shouldReceive('health')
        ->once()
        ->andReturn(true);

    $result = $this->client->isServerHealthy();

    expect($result)->toBeTrue();
});