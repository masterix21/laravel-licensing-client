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
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'expires_at' => '2025-12-31',
            'max_usages' => 5,
        ]);

    $this->artisan('license:activate', ['key' => 'TEST-LICENSE-KEY'])
        ->expectsOutput('Activating license...')
        ->expectsOutput('License activated successfully!')
        ->expectsTable(
            ['Property', 'Value'],
            [
                ['Customer', 'John Doe'],
                ['Email', 'john@example.com'],
                ['Expires', '2025-12-31'],
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
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'issued_at' => '2024-01-01',
            'expires_at' => '2025-12-31',
            'max_usages' => 5,
            'current_usages' => 2,
            'features' => ['feature1', 'feature2'],
        ]);

    $client->shouldReceive('isExpiringSoon')
        ->with(7, 'TEST-LICENSE-KEY')
        ->once()
        ->andReturn(false);

    $this->artisan('license:info', ['key' => 'TEST-LICENSE-KEY'])
        ->expectsOutput('Fetching license information...')
        ->expectsOutput('🌟 License Information:')
        ->expectsTable(
            ['Property', 'Value'],
            [
                ['License Key', 'TEST-LIC...'],
                ['Customer Name', 'John Doe'],
                ['Customer Email', 'john@example.com'],
                ['Issued At', '2024-01-01'],
                ['Expires At', '2025-12-31'],
                ['Max Usages', 5],
                ['Current Usages', 2],
            ]
        )
        ->expectsOutput('Features:')
        ->expectsOutput('  ✓ feature1')
        ->expectsOutput('  ✓ feature2')
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
