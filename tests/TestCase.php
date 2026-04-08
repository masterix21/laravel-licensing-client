<?php

namespace LucaLongo\LaravelLicensingClient\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\File;
use LucaLongo\LaravelLicensingClient\LaravelLicensingClientServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use ParagonIE\Paseto\Builder;
use ParagonIE\Paseto\Keys\AsymmetricPublicKey;
use ParagonIE\Paseto\Keys\AsymmetricSecretKey;
use ParagonIE\Paseto\Protocol\Version4;

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
        $builder = Builder::getPublic($this->privateKey, new Version4);

        $defaultClaims = [
            'sub' => '1',
            'iss' => 'laravel-licensing',
            'license_id' => 1,
            'license_key_hash' => hash('sha256', 'TEST-LICENSE-KEY'),
            'usage_fingerprint' => app(\LucaLongo\LaravelLicensingClient\Services\FingerprintGenerator::class)->generate(),
            'status' => 'active',
            'max_usages' => 5,
            'exp' => now()->addYear()->toIso8601String(),
            'nbf' => now()->toIso8601String(),
            'iat' => now()->toIso8601String(),
            'force_online_after' => now()->addDays(14)->toIso8601String(),
        ];

        $finalClaims = array_merge($defaultClaims, $claims);

        return $builder
            ->withClaims($finalClaims)
            ->toString();
    }

    /**
     * Generate a test token with a JSON footer (for certificate chain tests)
     *
     * @param  array<string, mixed>  $claims
     * @param  array<string, mixed>  $footer
     */
    protected function generateTestTokenWithFooter(array $claims = [], array $footer = []): string
    {
        $this->initializeTestProperties();
        $builder = Builder::getPublic($this->privateKey, new Version4);

        $defaultClaims = [
            'sub' => '1',
            'iss' => 'laravel-licensing',
            'license_id' => 1,
            'license_key_hash' => hash('sha256', 'TEST-LICENSE-KEY'),
            'usage_fingerprint' => app(\LucaLongo\LaravelLicensingClient\Services\FingerprintGenerator::class)->generate(),
            'status' => 'active',
            'max_usages' => 5,
            'exp' => now()->addYear()->toIso8601String(),
            'nbf' => now()->toIso8601String(),
            'iat' => now()->toIso8601String(),
            'force_online_after' => now()->addDays(14)->toIso8601String(),
        ];

        $finalClaims = array_merge($defaultClaims, $claims);

        return $builder
            ->withClaims($finalClaims)
            ->setFooterArray($footer)
            ->toString();
    }

    /**
     * Generate a test root+signing keypair and signed certificate for chain verification tests
     *
     * @return array{root_public_key: string, signing_public_key: string, chain: array<string, mixed>}
     */
    protected function generateTestCertificateChain(): array
    {
        $rootKeypair = sodium_crypto_sign_keypair();
        $rootPublicKey = sodium_crypto_sign_publickey($rootKeypair);
        $rootSecretKey = sodium_crypto_sign_secretkey($rootKeypair);

        $signingKeypair = sodium_crypto_sign_keypair();
        $signingPublicKey = sodium_crypto_sign_publickey($signingKeypair);

        $certificate = [
            'kid' => 'test-signing-key',
            'public_key' => base64_encode($signingPublicKey),
            'valid_from' => now()->subDay()->toIso8601String(),
            'valid_until' => now()->addYear()->toIso8601String(),
            'issued_at' => now()->toIso8601String(),
            'issuer_kid' => 'test-root-key',
        ];

        $signature = sodium_crypto_sign_detached(json_encode($certificate), $rootSecretKey);

        $signedCertificate = json_encode([
            'certificate' => $certificate,
            'signature' => base64_encode($signature),
        ]);

        return [
            'root_public_key' => base64_encode($rootPublicKey),
            'signing_public_key' => base64_encode($signingPublicKey),
            'chain' => [
                'signing' => [
                    'kid' => 'test-signing-key',
                    'public_key' => base64_encode($signingPublicKey),
                    'certificate' => $signedCertificate,
                    'valid_from' => now()->subDay()->toIso8601String(),
                    'valid_until' => now()->addYear()->toIso8601String(),
                ],
                'root' => [
                    'kid' => 'test-root-key',
                    'public_key' => base64_encode($rootPublicKey),
                    'valid_from' => now()->subYear()->toIso8601String(),
                    'valid_until' => now()->addYears(5)->toIso8601String(),
                ],
            ],
        ];
    }
}
