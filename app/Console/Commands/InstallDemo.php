<?php

namespace App\Console\Commands;

use App\Domain\Dashboards\Actions\InstallShowcaseDemo;
use Illuminate\Console\Command;
use InvalidArgumentException;

class InstallDemo extends Command
{
    protected $signature = 'demo:install';

    protected $description = 'Create the public demo account (DEMO_USER_EMAIL / DEMO_USER_PASSWORD) with sample dashboards';

    public function handle(InstallShowcaseDemo $install): int
    {
        try {
            $result = $install->execute();
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $result['created']) {
            $this->components->info("The demo account {$result['email']} already exists. Nothing was changed.");

            return self::SUCCESS;
        }

        $this->components->info("Demo account {$result['email']} created with {$result['dashboards']} dashboards.");

        return self::SUCCESS;
    }
}
