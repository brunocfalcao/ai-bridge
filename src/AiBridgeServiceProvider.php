<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge;

use BrunoCFalcao\AiBridge\Ai\BearerAnthropic;
use BrunoCFalcao\AiBridge\Mcp\Middleware\AuthenticateApiKey;
use BrunoCFalcao\AiBridge\Mcp\Servers\KnowledgeServer;
use BrunoCFalcao\AiBridge\Mcp\Services\SystemContext;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use Prism\Prism\Providers\Anthropic\Anthropic;
use Prism\Prism\PrismManager;

class AiBridgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-bridge.php', 'ai-bridge');

        $this->app->singleton(SystemContext::class);

        $this->configurePrismOAuth();
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerMigrations();
        $this->registerMcpRoutes();
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
        // Dynamically register MCP routes from active applications with knowledge connections
        try {
            $applications = \App\Models\Application::where('status', 'active')
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
                $this->registerKnowledgeConnection($app->knowledge_connection, $app->knowledge_database);
            }
        }

        // Populate config for SystemContext compatibility
        config(['ai-bridge.mcp_systems' => $mcpSystems]);
    }

    protected function registerKnowledgeConnection(string $connectionName, string $database): void
    {
        try {
            $app = \App\Models\Application::where('knowledge_connection', $connectionName)->first();
            $password = $app?->knowledge_db_password;
        } catch (\Throwable) {
            return;
        }

        if (! $password) {
            return;
        }

        // Derive username from connection name: codiant_knowledge_friday → codiant_friday
        $username = str_replace('codiant_knowledge_', 'codiant_', $connectionName);

        Config::set("database.connections.{$connectionName}", [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => '5432',
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
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
