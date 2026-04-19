<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge;

use BrunoCFalcao\AiBridge\Browser\BrowserSidecarClient;
use BrunoCFalcao\AiBridge\Browser\Mcp\BrowserServer;
use BrunoCFalcao\AiBridge\Chat\ChatManager;
use BrunoCFalcao\AiBridge\Knowledge\Mcp\KnowledgeServer;
use BrunoCFalcao\AiBridge\Knowledge\Middleware\AuthenticateApiKey;
use BrunoCFalcao\AiBridge\Knowledge\SystemContext;
use BrunoCFalcao\AiBridge\Providers\BearerAnthropic;
use BrunoCFalcao\AiBridge\Resolver\AiResolver;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use Prism\Prism\PrismManager;
use Prism\Prism\Providers\Anthropic\Anthropic;

class AiBridgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-bridge.php', 'ai-bridge');

        $this->app->singleton(SystemContext::class);
        $this->app->singleton(AiResolver::class);
        $this->app->singleton(ChatManager::class);

        $this->app->singleton(BrowserSidecarClient::class, function (Application $app) {
            $config = $app['config']->get('ai-bridge.browser', []);

            return new BrowserSidecarClient(
                baseUrl: $config['sidecar_url'] ?? 'http://127.0.0.1:3100',
                defaultSessionId: $config['default_session_id'] ?? 'ai-bridge',
                timeout: (int) ($config['timeout'] ?? 30),
            );
        });

        $this->configurePrismOAuth();
    }

    public function boot(): void
    {
        $this->registerCommands();
        $this->registerPublishing();
        $this->registerMigrations();
        $this->registerMcpRoutes();
        $this->registerBrowserMcpRoute();
    }

    protected function registerBrowserMcpRoute(): void
    {
        $path = config('ai-bridge.browser.mcp_path');

        if (! $path) {
            return;
        }

        Mcp::web($path, BrowserServer::class);
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\AiChatCommand::class,
            ]);
        }
    }

    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ai-bridge.php' => config_path('ai-bridge.php'),
            ], 'ai-bridge-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'ai-bridge-migrations');
        }
    }

    protected function registerMigrations(): void
    {
        // Only load main app migrations (not knowledge_chunks which is per-project PostgreSQL)
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations/app');
    }

    protected function registerMcpRoutes(): void
    {
        $applicationModel = config('ai-bridge.models.application');

        if (! $applicationModel) {
            return;
        }

        try {
            $applications = $applicationModel::where('status', 'active')
                ->whereNotNull('knowledge_connection')
                ->get();
        } catch (\Throwable) {
            // Table may not exist yet during migration
            return;
        }

        $mcpSystems = [];

        foreach ($applications as $app) {
            Mcp::web("/mcp/{$app->slug}", KnowledgeServer::class)
                ->middleware(AuthenticateApiKey::class);

            $mcpSystems[$app->slug] = [
                'name' => $app->name,
                'connection' => $app->knowledge_connection,
                'database' => $app->knowledge_database,
                'api_key' => $app->mcp_api_key,
            ];
        }

        // Register knowledge database connections (separate loop to avoid boot issues)
        foreach ($applications as $app) {
            if ($app->knowledge_connection && $app->knowledge_database) {
                $this->registerKnowledgeConnection(
                    $app->knowledge_connection,
                    $app->knowledge_database,
                    $app->knowledge_db_username ?? null,
                    $app->knowledge_db_password ?? null,
                );
            }
        }

        // Populate config for SystemContext compatibility
        config(['ai-bridge.mcp_systems' => $mcpSystems]);
    }

    protected function registerKnowledgeConnection(
        string $connectionName,
        string $database,
        ?string $username,
        ?string $password,
    ): void {
        if (! $password) {
            return;
        }

        $dbConfig = config('ai-bridge.knowledge.db');

        Config::set("database.connections.{$connectionName}", [
            'driver' => $dbConfig['driver'] ?? 'pgsql',
            'host' => $dbConfig['host'] ?? '127.0.0.1',
            'port' => $dbConfig['port'] ?? '5432',
            'database' => $database,
            'username' => $username ?? $connectionName,
            'password' => $password,
            'charset' => $dbConfig['charset'] ?? 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => $dbConfig['sslmode'] ?? 'prefer',
        ]);
    }

    /**
     * Configure Prism to use Bearer token authentication for Anthropic OAuth tokens.
     * Tokens starting with 'sk-ant-oat' are OAuth access tokens.
     */
    protected function configurePrismOAuth(): void
    {
        $this->app->afterResolving(PrismManager::class, function (PrismManager $manager): void {
            $manager->extend('anthropic', function (Application $app, array $config) {
                $apiKey = $config['api_key'] ?? '';

                $provider = str_starts_with($apiKey, 'sk-ant-oat')
                    ? BearerAnthropic::class
                    : Anthropic::class;

                return new $provider(
                    apiKey: $apiKey,
                    apiVersion: $config['version'],
                    url: $config['url'] ?? 'https://api.anthropic.com/v1',
                    betaFeatures: $config['anthropic_beta'] ?? null,
                );
            });
        });
    }
}
