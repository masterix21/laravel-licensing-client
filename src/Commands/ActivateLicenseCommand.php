<?php

namespace LucaLongo\LaravelLicensingClient\Commands;

use Illuminate\Console\Command;
use LucaLongo\LaravelLicensingClient\LaravelLicensingClient;

class ActivateLicenseCommand extends Command
{
    protected $signature = 'license:activate {key? : The license key to activate}';

    protected $description = 'Activate a license key';

    public function handle(LaravelLicensingClient $client): int
    {
        $licenseKey = $this->argument('key') ?? config('licensing-client.license_key');

        if (! $licenseKey) {
            $licenseKey = $this->ask('Please enter your license key');
        }

        if (! $licenseKey) {
            $this->error('License key is required');

            return self::FAILURE;
        }

        $this->info('Activating license...');

        try {
            if ($client->activate($licenseKey)) {
                $this->info('License activated successfully!');

                $licenseInfo = $client->getLicenseInfo($licenseKey);
                if ($licenseInfo) {
                    $this->table(
                        ['Property', 'Value'],
                        [
                            ['Customer', $licenseInfo['customer_name'] ?? 'N/A'],
                            ['Email', $licenseInfo['customer_email'] ?? 'N/A'],
                            ['Expires', $licenseInfo['expires_at'] ?? 'Never'],
                            ['Max Usages', $licenseInfo['max_usages'] ?? 'Unlimited'],
                        ]
                    );
                }

                return self::SUCCESS;
            }

            $this->error('Failed to activate license');

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error('Activation failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
