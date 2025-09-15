<?php

namespace LucaLongo\LaravelLicensingClient\Commands;

use Illuminate\Console\Command;
use LucaLongo\LaravelLicensingClient\LaravelLicensingClient;

class DeactivateLicenseCommand extends Command
{
    protected $signature = 'license:deactivate {key? : The license key to deactivate}';

    protected $description = 'Deactivate a license key';

    public function handle(LaravelLicensingClient $client): int
    {
        $licenseKey = $this->argument('key') ?? config('licensing-client.license_key');

        if (! $licenseKey) {
            $this->error('License key is required');

            return self::FAILURE;
        }

        if (! $this->confirm('Are you sure you want to deactivate this license?')) {
            $this->info('Deactivation cancelled');

            return self::SUCCESS;
        }

        $this->info('Deactivating license...');

        try {
            if ($client->deactivate($licenseKey)) {
                $this->info('License deactivated successfully!');

                return self::SUCCESS;
            }

            $this->error('Failed to deactivate license');

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error('Deactivation failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
