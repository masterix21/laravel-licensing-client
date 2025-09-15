# Laravel Licensing Client

A comprehensive Laravel package for integrating license validation in your applications. This client package works with the [masterix21/laravel-licensing](https://github.com/masterix21/laravel-licensing) server to provide secure, offline-capable license management using PASETO v4 tokens.

## Features

- 🔐 **Secure offline validation** using PASETO v4 tokens
- 🖥️ **Device fingerprinting** to prevent license sharing
- ⏰ **Grace period support** for handling server downtime
- 💾 **Token caching** for improved performance
- 🛡️ **Middleware protection** for routes
- 🎨 **Artisan commands** for license management
- 📊 **Usage limit tracking**
- 🔄 **Automatic token refresh**
- ❤️ **Heartbeat mechanism** for license status updates

## Requirements

- PHP 8.1 or higher
- Laravel 10.0 or higher

## Installation

Install the package via Composer:

```bash
composer require lucalongo/laravel-licensing-client
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="licensing-client-config"
```

Run the migrations:

```bash
php artisan migrate
```

## Configuration

Configure the package in `config/licensing-client.php`:

```php
return [
    // Licensing server configuration
    'server_url' => env('LICENSING_SERVER_URL', 'https://licensing.example.com'),
    'api_version' => env('LICENSING_API_VERSION', 'v1'),

    // PASETO v4 public key for offline token validation
    'public_key' => env('LICENSING_PUBLIC_KEY', ''),

    // Default license key (can be overridden at runtime)
    'license_key' => env('LICENSE_KEY', ''),

    // Storage configuration
    'storage_path' => storage_path('licensing'),

    // Cache configuration
    'cache' => [
        'enabled' => env('LICENSING_CACHE_ENABLED', true),
        'store' => env('LICENSING_CACHE_STORE', 'file'),
        'ttl' => env('LICENSING_CACHE_TTL', 3600), // 1 hour
    ],

    // Heartbeat configuration
    'heartbeat' => [
        'enabled' => env('LICENSING_HEARTBEAT_ENABLED', true),
        'interval' => env('LICENSING_HEARTBEAT_INTERVAL', 3600), // 1 hour
    ],

    // Grace period for server unavailability (in hours)
    'grace_period' => env('LICENSING_GRACE_PERIOD', 72), // 3 days

    // Routes to exclude from license checking
    'excluded_routes' => [
        'login',
        'register',
        'password/*',
        'health',
    ],
];
```

### Environment Variables

Add these to your `.env` file:

```env
LICENSING_SERVER_URL=https://your-licensing-server.com
LICENSING_PUBLIC_KEY=your-paseto-v4-public-key
LICENSE_KEY=YOUR-LICENSE-KEY-HERE
```

## Basic Usage

### Using the Facade

```php
use LucaLongo\LaravelLicensingClient\Facades\LaravelLicensingClient;

// Activate a license
$activated = LaravelLicensingClient::activate('LICENSE-KEY-123');

// Check if license is valid
if (LaravelLicensingClient::isValid()) {
    // License is valid, proceed with application logic
}

// Get license information
$licenseInfo = LaravelLicensingClient::getLicenseInfo();
// Returns: [
//     'license_key' => 'LICENSE-KEY-123',
//     'customer_email' => 'customer@example.com',
//     'customer_name' => 'John Doe',
//     'expires_at' => '2025-01-01T00:00:00Z',
//     'max_usages' => 5,
//     'current_usages' => 2,
//     'features' => ['feature1', 'feature2'],
//     'metadata' => ['custom' => 'data']
// ]

// Check if expiring soon (within 7 days)
if (LaravelLicensingClient::isExpiringSoon(7)) {
    // Notify user to renew license
}

// Refresh the license token
LaravelLicensingClient::refresh();

// Deactivate a license
LaravelLicensingClient::deactivate('LICENSE-KEY-123');
```

### Dependency Injection

```php
use LucaLongo\LaravelLicensingClient\LaravelLicensingClient;

class LicenseController extends Controller
{
    public function __construct(
        private LaravelLicensingClient $licensing
    ) {}

    public function status()
    {
        if ($this->licensing->isValid()) {
            return response()->json([
                'valid' => true,
                'info' => $this->licensing->getLicenseInfo()
            ]);
        }

        return response()->json(['valid' => false], 403);
    }
}
```

## Middleware Protection

Protect your routes using the `license` middleware:

```php
// In routes/web.php or routes/api.php

// Protect individual routes
Route::get('/dashboard', DashboardController::class)
    ->middleware('license');

// Protect route groups
Route::middleware(['license'])->group(function () {
    Route::get('/reports', ReportsController::class);
    Route::get('/analytics', AnalyticsController::class);
});

// API routes with license protection
Route::prefix('api')->middleware(['api', 'license'])->group(function () {
    Route::apiResource('products', ProductController::class);
});
```

### Excluding Routes

Configure routes that should be accessible without a license in `config/licensing-client.php`:

```php
'excluded_routes' => [
    'login',
    'register',
    'password/*',     // Wildcard support
    'api/health',
    'public/*',
],
```

### Handling License Expiration in Middleware

The middleware automatically:
- Validates the license on each request
- Attempts to refresh expired tokens
- Starts grace period if server is unreachable
- Sends heartbeats to track usage
- Adds expiration warnings to request attributes

Access expiration warnings in your controllers:

```php
public function dashboard(Request $request)
{
    if ($request->attributes->get('license_expiring_soon')) {
        $expiresAt = $request->attributes->get('license_expires_at');
        // Show warning banner to user
    }

    return view('dashboard');
}
```

## Artisan Commands

### Activate a License

```bash
# With license key as argument
php artisan license:activate YOUR-LICENSE-KEY

# Interactive mode (will prompt for key)
php artisan license:activate
```

### Validate License Status

```bash
# Check current license
php artisan license:validate

# Check specific license
php artisan license:validate --key=ANOTHER-LICENSE-KEY
```

### Display License Information

```bash
php artisan license:info
```

Output:
```
License Information:
====================
Status: ✓ Active
License Key: YOUR-LICENSE-KEY
Customer: John Doe (john@example.com)
Expires: 2025-01-01 00:00:00 (in 180 days)
Usage: 2 / 5 activations
Features: feature1, feature2, premium_support
```

### Refresh License Token

```bash
php artisan license:refresh
```

### Deactivate License

```bash
php artisan license:deactivate

# With confirmation bypass
php artisan license:deactivate --force
```

## Task Scheduling

### Automatic License Maintenance

The package includes automatic license maintenance through Laravel's task scheduler. When heartbeat is enabled, it automatically sends heartbeat signals to keep your license status synchronized with the server.

To enable automatic scheduling, add the following to your `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    // The package automatically schedules heartbeat when enabled in config
    // No manual configuration needed if heartbeat.enabled is true
}
```

The heartbeat runs based on your configuration:
- **Default interval**: Every 60 minutes (3600 seconds)
- **Configurable via**: `LICENSING_HEARTBEAT_INTERVAL` environment variable (in seconds)

### Manual License Checks

If you prefer manual control or need additional license checks, you can schedule commands:

```php
use Illuminate\Console\Scheduling\Schedule;

protected function schedule(Schedule $schedule): void
{
    // Validate license daily
    $schedule->command('license:validate')
        ->daily()
        ->onFailure(function () {
            // Handle invalid license
            Log::error('License validation failed');
        });

    // Refresh license token weekly
    $schedule->command('license:refresh')
        ->weekly()
        ->onSuccess(function () {
            Log::info('License token refreshed successfully');
        });

    // Check and notify about expiring licenses
    $schedule->call(function () {
        if (LaravelLicensingClient::isExpiringSoon(7)) {
            // Send notification about expiring license
            Mail::to(config('mail.admin'))->send(new LicenseExpiringSoon());
        }
    })->daily();
}
```

### Configuration

The scheduling behavior is controlled by these settings in `config/licensing-client.php`:

```php
'heartbeat' => [
    'enabled' => env('LICENSING_HEARTBEAT_ENABLED', true),
    'interval' => env('LICENSING_HEARTBEAT_INTERVAL', 3600), // seconds
],
```

To disable automatic heartbeat:

```env
LICENSING_HEARTBEAT_ENABLED=false
```

To change heartbeat frequency (e.g., every 30 minutes):

```env
LICENSING_HEARTBEAT_INTERVAL=1800
```

## Grace Period Management

When the licensing server is unreachable, the package automatically enters a grace period:

```php
// Check if in grace period
if (LaravelLicensingClient::isInGracePeriod()) {
    // Show warning that license server is unreachable
    // Application continues to work for configured grace period
}

// Manually start grace period (useful for testing)
LaravelLicensingClient::startGracePeriod();

// Check server health
if (!LaravelLicensingClient::isServerHealthy()) {
    // Server is down, grace period may be active
}
```

## Advanced Usage

### Custom License Validation

```php
use LucaLongo\LaravelLicensingClient\Services\TokenValidator;

class CustomLicenseValidator
{
    public function __construct(
        private TokenValidator $validator
    ) {}

    public function validateBusinessRules(string $token): bool
    {
        try {
            $claims = $this->validator->validate($token);

            // Custom validation logic
            if ($claims['plan'] !== 'enterprise') {
                return false;
            }

            // Check custom features
            $requiredFeatures = ['api_access', 'white_label'];
            $hasFeatures = !empty(array_intersect(
                $requiredFeatures,
                $claims['features'] ?? []
            ));

            return $hasFeatures;
        } catch (\Exception $e) {
            return false;
        }
    }
}
```

### Heartbeat Customization

Send custom data with heartbeats:

```php
// In a service provider or scheduled job
use LucaLongo\LaravelLicensingClient\Facades\LaravelLicensingClient;

LaravelLicensingClient::heartbeat(
    licenseKey: 'LICENSE-KEY',
    data: [
        'active_users' => User::where('last_login', '>', now()->subDay())->count(),
        'storage_used' => DiskUsage::calculate(),
        'api_calls_today' => ApiLog::today()->count(),
    ]
);
```

### Token Storage Access

Direct access to token storage for advanced scenarios:

```php
use LucaLongo\LaravelLicensingClient\Services\TokenStorage;

class LicenseDebugService
{
    public function __construct(
        private TokenStorage $storage
    ) {}

    public function debugInfo(string $licenseKey): array
    {
        return [
            'has_token' => $this->storage->exists($licenseKey),
            'last_heartbeat' => $this->storage->getLastHeartbeat($licenseKey),
            'grace_period' => $this->storage->getGracePeriod(),
            'cached' => Cache::has("license_token:{$licenseKey}"),
        ];
    }
}
```

### Custom Fingerprint Generation

Extend the fingerprint generator for custom device identification:

```php
use LucaLongo\LaravelLicensingClient\Services\FingerprintGenerator;

class CustomFingerprintGenerator extends FingerprintGenerator
{
    public function generate(): string
    {
        $metadata = $this->getMetadata();

        // Add custom identifiers
        $metadata['docker_container'] = env('HOSTNAME');
        $metadata['deployment_id'] = config('app.deployment_id');

        return hash('sha256', json_encode($metadata));
    }
}

// Register in a service provider
$this->app->bind(FingerprintGenerator::class, CustomFingerprintGenerator::class);
```

## Error Handling

The package throws specific exceptions for different scenarios:

```php
use LucaLongo\LaravelLicensingClient\Exceptions\LicensingException;
use LucaLongo\LaravelLicensingClient\Facades\LaravelLicensingClient;

try {
    LaravelLicensingClient::validate();
} catch (LicensingException $e) {
    switch ($e->getMessage()) {
        case 'The license has expired.':
            // Handle expiration
            break;
        case 'License usage limit has been exceeded.':
            // Handle usage limit
            break;
        case 'Device fingerprint does not match the licensed device.':
            // Handle device mismatch
            break;
        case 'The license has not been activated.':
            // Prompt for activation
            break;
        default:
            // Generic error handling
            Log::error('License validation failed', [
                'error' => $e->getMessage()
            ]);
    }
}
```

## Testing

The package includes comprehensive test coverage. When using in your tests:

```php
use LucaLongo\LaravelLicensingClient\Facades\LaravelLicensingClient;
use Illuminate\Support\Facades\Http;

class FeatureTest extends TestCase
{
    public function test_protected_route_with_valid_license()
    {
        // Mock the licensing server responses
        Http::fake([
            '*/api/licensing/v1/activate' => Http::response([
                'token' => 'valid-paseto-token',
                'expires_at' => now()->addYear()->toIso8601String(),
            ], 200),
        ]);

        // Activate a test license
        LaravelLicensingClient::activate('TEST-LICENSE');

        // Test protected route
        $response = $this->get('/protected-route');
        $response->assertStatus(200);
    }

    public function test_grace_period_activation()
    {
        // Mock server as unreachable
        Http::fake([
            '*/api/licensing/v1/*' => Http::response(null, 500),
        ]);

        // Start grace period
        LaravelLicensingClient::startGracePeriod();

        // Should still access protected routes
        $response = $this->get('/protected-route');
        $response->assertStatus(200);
    }
}
```

### Mocking in Unit Tests

```php
use LucaLongo\LaravelLicensingClient\LaravelLicensingClient;
use Mockery;

class ServiceTest extends TestCase
{
    public function test_service_with_license_check()
    {
        $licensingMock = Mockery::mock(LaravelLicensingClient::class);
        $licensingMock->shouldReceive('isValid')
            ->once()
            ->andReturn(true);
        $licensingMock->shouldReceive('getLicenseInfo')
            ->once()
            ->andReturn([
                'customer_email' => 'test@example.com',
                'features' => ['premium'],
            ]);

        $this->app->instance(LaravelLicensingClient::class, $licensingMock);

        // Test your service
        $service = app(YourService::class);
        $result = $service->premiumFeature();

        $this->assertTrue($result);
    }
}
```

## Troubleshooting

### Common Issues

#### License validation fails with "Invalid public key format"
- Ensure the PASETO v4 public key is correctly formatted
- The key should be base64-encoded in the correct PASETO format
- Verify the key matches the one used by the licensing server

#### Grace period not activating
- Check that the grace period is configured in hours (default: 72)
- Verify the server health endpoint is correctly configured
- Check storage permissions for the grace period file

#### Heartbeat not sending
- Ensure heartbeat is enabled in configuration
- Check the heartbeat interval setting
- Verify network connectivity to the licensing server

#### Token not caching
- Verify cache is enabled in configuration
- Check the configured cache store exists
- Ensure cache permissions are set correctly

### Debug Mode

Enable debug logging for troubleshooting:

```php
// In config/logging.php
'channels' => [
    'licensing' => [
        'driver' => 'single',
        'path' => storage_path('logs/licensing.log'),
        'level' => env('LICENSING_LOG_LEVEL', 'debug'),
    ],
],
```

Then in your code:

```php
use Illuminate\Support\Facades\Log;

Log::channel('licensing')->info('License check', [
    'valid' => LaravelLicensingClient::isValid(),
    'info' => LaravelLicensingClient::getLicenseInfo(),
]);
```

## Security Considerations

1. **Store the public key securely**: Use environment variables, never commit to version control
2. **Validate fingerprints**: Ensure device fingerprinting is properly configured to prevent license sharing
3. **Monitor heartbeats**: Track unusual patterns in heartbeat data for potential abuse
4. **Implement rate limiting**: Add rate limiting to license validation endpoints
5. **Audit license usage**: Log all activation, deactivation, and validation events
6. **Secure token storage**: Tokens are automatically encrypted when stored

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).

## Support

For issues and questions, please use the [GitHub issue tracker](https://github.com/lucalongo/laravel-licensing-client/issues).

## Credits

- [Luca Longo](https://github.com/lucalongo)
- Built to work with [masterix21/laravel-licensing](https://github.com/masterix21/laravel-licensing)

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.