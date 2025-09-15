<?php

namespace LucaLongo\LaravelLicensingClient\Commands;

use Illuminate\Console\Command;
use LucaLongo\LaravelLicensingClient\LaravelLicensingClient;

class ValidateLicenseCommand extends Command
{
    protected $signature = 'license:validate {key? : The license key to validate}';

    protected $description = 'Validate a license';

    public function handle(LaravelLicensingClient $client): int
    {
        $licenseKey = $this->argument('key') ?? config('licensing-client.license_key');

        if (!$licenseKey) {
            $this->error('License key is required');
            return self::FAILURE;
        }

        $this->info('Validating license...');

        try {
            if ($client->isValid($licenseKey)) {
                $this->info('✓ License is valid');

                if ($client->isExpiringSoon(7, $licenseKey)) {
                    $this->warn('⚠ License is expiring soon!');
                }

                return self::SUCCESS;
            }

            $this->error('✗ License is invalid');
            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error('Validation failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}