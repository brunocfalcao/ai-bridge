<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge;

use BrunoCFalcao\AiBridge\Resolver\AiResolver;
use Illuminate\Support\ServiceProvider;

class AiBridgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-bridge.php', 'ai-bridge');

        $this->app->singleton(AiResolver::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ai-bridge.php' => config_path('ai-bridge.php'),
            ], 'ai-bridge-config');
        }
    }
}
