<?php

declare(strict_types=1);

namespace Tpetry\MultiRegion;

use Illuminate\Support\ServiceProvider;
use Tpetry\MultiRegion\Helpers\CommitId;

class MultiRegionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/multiregion.php', 'multiregion');

        $this->app->singleton(CommitId::class);
        $this->app->singleton(MultiRegionManager::class);
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/multiregion.php' => config_path('multiregion.php'),
        ], ['multiregion-config']);
    }
}
