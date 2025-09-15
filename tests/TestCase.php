<?php

namespace LucaLongo\LaravelLicensingClient\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\File;
use LucaLongo\LaravelLicensingClient\LaravelLicensingClientServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use ParagonIE\Paseto\Keys\AsymmetricPublicKey;
use ParagonIE\Paseto\Keys\AsymmetricSecretKey;

class TestCase extends Orchestra
{
    protected ?AsymmetricSecretKey $privateKey = null;

    protected ?AsymmetricPublicKey $publicKey = null;

    protected ?string $testStoragePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'LucaLongo\\LaravelLicensingClient\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function tearDown(): void
    {
        // Clean up test storage
        if (File::isDirectory($this->testStoragePath)) {
            File::deleteDirectory($this->testStoragePath);
        }

        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelLicensingClientServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        // Initialize test properties early
        $this->initializeTestProperties();

        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set app key for encryption
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        config()->set('licensing-client.server_url', 'https://licensing.test');
        config()->set('licensing-client.api_version', 'v1');
        config()->set('licensing-client.public_key', $this->publicKey ? $this->publicKey->encode() : '');
        config()->set('licensing-client.storage_path', $this->testStoragePath);
        config()->set('licensing-client.cache.enabled', true);
        config()->set('licensing-client.cache.store', 'array');
        config()->set('licensing-client.heartbeat.enabled', false);

        // Run migrations
        $migration = include __DIR__.'/../database/migrations/create_licensing_client_table.php.stub';
        $migration->up();
    }

    protected function initializeTestProperties(): void
    {
        if (! $this->privateKey) {
            // Generate test keys for PASETO v4
            $this->privateKey = AsymmetricSecretKey::generate(new \ParagonIE\Paseto\Protocol\Version4);
            $this->publicKey = $this->privateKey->getPublicKey();

            // Set test storage path
            $this->testStoragePath = sys_get_temp_dir().'/licensing-test-'.uniqid();
            File::makeDirectory($this->testStoragePath, 0755, true);
        }
    }

    protected function generateTestToken(array $claims = []): string
    {
        $this->initializeTestProperties();
        $builder = \ParagonIE\Paseto\Builder::getPublic($this->privateKey, new \ParagonIE\Paseto\Protocol\Version4);

        $defaultClaims = [
            'license_key' => 'TEST-LICENSE-KEY',
            'fingerprint' => app(\LucaLongo\LaravelLicensingClient\Services\FingerprintGenerator::class)->generate(),
            'customer_email' => 'test@example.com',
            'customer_name' => 'Test User',
            'exp' => now()->addYear()->toIso8601String(),
            'iat' => now()->toIso8601String(),
            'max_usages' => 1,
            'current_usages' => 0,
        ];

        $finalClaims = array_merge($defaultClaims, $claims);

        return $builder
            ->withClaims($finalClaims)
            ->toString();
    }
}
