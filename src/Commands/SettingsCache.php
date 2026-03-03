<?php

namespace Shopapps\LaravelSettings\Commands;

use Illuminate\Console\Command;
use Shopapps\LaravelSettings\Services\SettingService;

class SettingsCache extends Command
{
    protected $signature = 'settings:cache';

    protected $description = 'Pre-cache all database settings into a single cache entry';

    public function handle(): int
    {
        $service = SettingService::make();

        $count = $service->buildPreCache();

        $this->info("Settings cached successfully. ({$count} settings)");

        return self::SUCCESS;
    }
}
