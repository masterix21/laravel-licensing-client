<?php

use LucaLongo\LaravelLicensingClient\LaravelLicensingClient;

it('can activate a license via command', function () {
    $client = Mockery::mock(LaravelLicensingClient::class);
    $this->app->instance(LaravelLicensingClient::class, $client);

    $client->shouldReceive('activate')
        ->with('TEST-LICENSE-KEY')
        ->once()
        ->andReturn(true);

    $client->shouldReceive('getLicenseInfo')
        ->with('TEST-LICENSE-KEY')
        ->once()
        ->andReturn([
            'status' => 'active',
            'license_expires_at' => '2027-12-31',
            'expires_at' => '2027-01-07',
            'max_usages' => 5,
        ]);

    $this->artisan('license:activate', ['key' => 'TEST-LICENSE-KEY'])
        ->expectsOutput('Activating license...')
        ->expectsOutput('License activated successfully!')
        ->expectsTable(
            ['Property', 'Value'],
            [
                ['Status', 'active'],
                ['License Expires', '2027-12-31'],
                ['Token Expires', '2027-01-07'],
                ['Max Usages', 5],
            ]
        )
        ->assertSuccessful();
});

it('can deactivate a license via command', function () {
    $client = Mockery::mock(LaravelLicensingClient::class);
    $this->app->instance(LaravelLicensingClient::class, $client);

    $client->shouldReceive('deactivate')
        ->with('TEST-LICENSE-KEY')
        ->once()
        ->andReturn(true);

    $this->artisan('license:deactivate', ['key' => 'TEST-LICENSE-KEY'])
        ->expectsQuestion('Are you sure you want to deactivate this license?', true)
        ->expectsOutput('Deactivating license...')
        ->expectsOutput('License deactivated successfully!')
        ->assertSuccessful();
});

it('can refresh a license via command', function () {
    $client = Mockery::mock(LaravelLicensingClient::class);
    $this->app->instance(LaravelLicensingClient::class, $client);

    $client->shouldReceive('refresh')
        ->with('TEST-LICENSE-KEY')
        ->once()
        ->andReturn(true);

    $this->artisan('license:refresh', ['key' => 'TEST-LICENSE-KEY'])
        ->expectsOutput('Refreshing license token...')
        ->expectsOutput('License token refreshed successfully!')
        ->assertSuccessful();
});

it('can validate a license via command', function () {
    $client = Mockery::mock(LaravelLicensingClient::class);
    $this->app->instance(LaravelLicensingClient::class, $client);

    $client->shouldReceive('isValid')
        ->with('TEST-LICENSE-KEY')
        ->once()
        ->andReturn(true);

    $client->shouldReceive('isExpiringSoon')
        ->with(7, 'TEST-LICENSE-KEY')
        ->once()
        ->andReturn(false);

    $this->artisan('license:validate', ['key' => 'TEST-LICENSE-KEY'])
        ->expectsOutput('Validating license...')
        ->expectsOutput('✓ License is valid')
        ->assertSuccessful();
});

it('warns when license is expiring soon', function () {
    $client = Mockery::mock(LaravelLicensingClient::class);
    $this->app->instance(LaravelLicensingClient::class, $client);

    $client->shouldReceive('isValid')
        ->with('TEST-LICENSE-KEY')
        ->once()
        ->andReturn(true);

    $client->shouldReceive('isExpiringSoon')
        ->with(7, 'TEST-LICENSE-KEY')
        ->once()
        ->andReturn(true);

    $this->artisan('license:validate', ['key' => 'TEST-LICENSE-KEY'])
        ->expectsOutput('Validating license...')
        ->expectsOutput('✓ License is valid')
        ->expectsOutput('⚠ License is expiring soon!')
        ->assertSuccessful();
});

it('can display license information via command', function () {
    $client = Mockery::mock(LaravelLicensingClient::class);
    $this->app->instance(LaravelLicensingClient::class, $client);

    $client->shouldReceive('getLicenseInfo')
        ->with('TEST-LICENSE-KEY')
        ->once()
        ->andReturn([
            'status' => 'active',
            'issued_at' => '2024-01-01',
            'expires_at' => '2025-12-31',
            'license_expires_at' => '2025-12-31',
            'max_usages' => 5,
            'force_online_after' => '2025-01-14',
            'grace_until' => null,
        ]);

    $client->shouldReceive('isExpiringSoon')
        ->with(7, 'TEST-LICENSE-KEY')
        ->once()
        ->andReturn(false);

    $client->shouldReceive('requiresOnlineRefresh')
        ->with('TEST-LICENSE-KEY')
        ->once()
        ->andReturn(false);

    $this->artisan('license:info', ['key' => 'TEST-LICENSE-KEY'])
        ->expectsOutput('Fetching license information...')
        ->expectsOutput('License Information:')
        ->expectsTable(
            ['Property', 'Value'],
            [
                ['License Key', 'TEST-LIC...'],
                ['Status', 'active'],
                ['Issued At', '2024-01-01'],
                ['Token Expires At', '2025-12-31'],
                ['License Expires At', '2025-12-31'],
                ['Max Usages', 5],
                ['Force Online After', '2025-01-14'],
                ['Grace Until', 'N/A'],
            ]
        )
        ->assertSuccessful();
});

it('handles activation failure via command', function () {
    $client = Mockery::mock(LaravelLicensingClient::class);
    $this->app->instance(LaravelLicensingClient::class, $client);

    $client->shouldReceive('activate')
        ->with('INVALID-KEY')
        ->once()
        ->andThrow(new Exception('Invalid license key'));

    $this->artisan('license:activate', ['key' => 'INVALID-KEY'])
        ->expectsOutput('Activating license...')
        ->expectsOutput('Activation failed: Invalid license key')
        ->assertFailed();
});

it('prompts for license key when not provided', function () {
    config(['licensing-client.license_key' => null]);

    $client = Mockery::mock(LaravelLicensingClient::class);
    $this->app->instance(LaravelLicensingClient::class, $client);

    $client->shouldReceive('activate')
        ->with('PROMPTED-KEY')
        ->once()
        ->andReturn(true);

    $client->shouldReceive('getLicenseInfo')
        ->with('PROMPTED-KEY')
        ->once()
        ->andReturn([]);

    $this->artisan('license:activate')
        ->expectsQuestion('Please enter your license key', 'PROMPTED-KEY')
        ->expectsOutput('Activating license...')
        ->expectsOutput('License activated successfully!')
        ->assertSuccessful();
});

it('shows error when deactivation fails', function () {
    $client = Mockery::mock(LaravelLicensingClient::class);
    $this->app->instance(LaravelLicensingClient::class, $client);

    $client->shouldReceive('deactivate')
        ->once()
        ->andReturn(false);

    $this->artisan('license:deactivate', ['key' => 'TEST-KEY'])
        ->expectsQuestion('Are you sure you want to deactivate this license?', true)
        ->expectsOutput('Deactivating license...')
        ->expectsOutput('Failed to deactivate license')
        ->assertFailed();
});

it('cancels deactivation when user declines', function () {
    $client = Mockery::mock(LaravelLicensingClient::class);
    $this->app->instance(LaravelLicensingClient::class, $client);

    $client->shouldNotReceive('deactivate');

    $this->artisan('license:deactivate', ['key' => 'TEST-KEY'])
        ->expectsQuestion('Are you sure you want to deactivate this license?', false)
        ->expectsOutput('Deactivation cancelled')
        ->assertSuccessful();
});

it('shows error when refresh fails', function () {
    $client = Mockery::mock(LaravelLicensingClient::class);
    $this->app->instance(LaravelLicensingClient::class, $client);

    $client->shouldReceive('refresh')
        ->once()
        ->andReturn(false);

    $this->artisan('license:refresh', ['key' => 'TEST-KEY'])
        ->expectsOutput('Refreshing license token...')
        ->expectsOutput('Failed to refresh license token')
        ->assertFailed();
});

it('shows error when validation fails', function () {
    $client = Mockery::mock(LaravelLicensingClient::class);
    $this->app->instance(LaravelLicensingClient::class, $client);

    $client->shouldReceive('isValid')
        ->once()
        ->andReturn(false);

    $this->artisan('license:validate', ['key' => 'TEST-KEY'])
        ->expectsOutput('Validating license...')
        ->expectsOutput('✗ License is invalid')
        ->assertFailed();
});

it('shows error when no license key for validation', function () {
    config(['licensing-client.license_key' => null]);

    $this->artisan('license:validate')
        ->expectsOutput('License key is required')
        ->assertFailed();
});

it('shows error when no license info available', function () {
    $client = Mockery::mock(LaravelLicensingClient::class);
    $this->app->instance(LaravelLicensingClient::class, $client);

    $client->shouldReceive('getLicenseInfo')
        ->once()
        ->andReturn([]);

    $this->artisan('license:info', ['key' => 'TEST-KEY'])
        ->expectsOutput('Fetching license information...')
        ->expectsOutput('No license information available. Please activate the license first.')
        ->assertFailed();
});

it('shows online refresh warning in license info', function () {
    $client = Mockery::mock(LaravelLicensingClient::class);
    $this->app->instance(LaravelLicensingClient::class, $client);

    $client->shouldReceive('getLicenseInfo')
        ->once()
        ->andReturn([
            'status' => 'active',
            'issued_at' => '2024-01-01',
            'expires_at' => '2025-12-31',
            'license_expires_at' => null,
            'max_usages' => null,
            'force_online_after' => '2024-01-14',
            'grace_until' => null,
        ]);

    $client->shouldReceive('isExpiringSoon')
        ->once()
        ->andReturn(false);

    $client->shouldReceive('requiresOnlineRefresh')
        ->once()
        ->andReturn(true);

    $this->artisan('license:info', ['key' => 'TEST-KEY'])
        ->expectsOutput('Warning: Online refresh is required!')
        ->assertSuccessful();
});

it('uses configured license key when not provided as argument', function () {
    config(['licensing-client.license_key' => 'CONFIG-KEY']);

    $client = Mockery::mock(LaravelLicensingClient::class);
    $this->app->instance(LaravelLicensingClient::class, $client);

    $client->shouldReceive('isValid')
        ->with('CONFIG-KEY')
        ->once()
        ->andReturn(true);

    $client->shouldReceive('isExpiringSoon')
        ->with(7, 'CONFIG-KEY')
        ->once()
        ->andReturn(false);

    $this->artisan('license:validate')
        ->expectsOutput('Validating license...')
        ->expectsOutput('✓ License is valid')
        ->assertSuccessful();
});
