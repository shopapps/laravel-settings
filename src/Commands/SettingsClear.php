<?php

namespace Shopapps\LaravelSettings\Commands;

use Illuminate\Console\Command;
use Shopapps\LaravelSettings\Services\SettingService;

class SettingsClear extends Command
{
    protected $signature = 'settings:clear';

    protected $description = 'Clear the pre-cached settings from cache';

    public function handle(): int
    {
        $service = SettingService::make();

        $service->clearPreCache();

        $this->info('Settings cache cleared successfully.');

        return self::SUCCESS;
    }
}
