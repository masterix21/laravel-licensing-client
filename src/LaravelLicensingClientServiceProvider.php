<?php

namespace LucaLongo\LaravelLicensingClient;

use Illuminate\Console\Scheduling\Schedule;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use LucaLongo\LaravelLicensingClient\Commands\{
    ActivateLicenseCommand,
    DeactivateLicenseCommand,
    RefreshLicenseCommand,
    ValidateLicenseCommand,
    LicenseInfoCommand
};
use LucaLongo\LaravelLicensingClient\Http\Middleware\CheckLicense;
use LucaLongo\LaravelLicensingClient\Services\{
    FingerprintGenerator,
    LicensingApiClient,
    TokenStorage,
    TokenValidator
};

class LaravelLicensingClientServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-licensing-client')
            ->hasConfigFile('licensing-client')
            ->hasMigration('create_licensing_client_table')
            ->hasCommands([
                ActivateLicenseCommand::class,
                DeactivateLicenseCommand::class,
                RefreshLicenseCommand::class,
                ValidateLicenseCommand::class,
                LicenseInfoCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Register services as singletons
        $this->app->singleton(FingerprintGenerator::class);
        $this->app->singleton(LicensingApiClient::class);
        $this->app->singleton(TokenStorage::class);
        $this->app->singleton(TokenValidator::class);

        // Register main client as singleton
        $this->app->singleton(LaravelLicensingClient::class, function ($app) {
            return new LaravelLicensingClient(
                $app->make(FingerprintGenerator::class),
                $app->make(LicensingApiClient::class),
                $app->make(TokenStorage::class),
                $app->make(TokenValidator::class)
            );
        });

        // Register alias
        $this->app->alias(LaravelLicensingClient::class, 'laravel-licensing-client');
    }

    public function packageBooted(): void
    {
        // Register middleware
        $this->app['router']->aliasMiddleware('license', CheckLicense::class);

        // Schedule heartbeat if enabled
        if (config('licensing-client.heartbeat.enabled')) {
            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $interval = config('licensing-client.heartbeat.interval', 3600);

                $schedule->call(function () {
                    app(LaravelLicensingClient::class)->heartbeat();
                })->everyMinutes($interval / 60);
            });
        }
    }
}
