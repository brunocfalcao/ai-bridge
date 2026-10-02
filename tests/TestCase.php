<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Tests;

use BrunoCFalcao\AiBridge\AiBridgeServiceProvider;
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            AiServiceProvider::class,
            AiBridgeServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai-bridge.resolver', [
            'connections' => [
                'cheap' => 'gemini:gemini-3-flash-preview',
                'bridge' => 'claude-cli:opus',
            ],
            'fallbacks' => [
                'claude-cli' => 'openrouter:anthropic/claude-sonnet-4',
                'openrouter' => 'gemini:gemini-3-flash-preview',
            ],
            'default' => 'claude-cli:opus',
        ]);

        $app['config']->set('ai-bridge.claude_cli', [
            'url' => 'http://localhost:3456',
            'timeout' => 120,
            'agent_name' => 'TestAgent',
        ]);

        $app['config']->set('ai-bridge.openclaw', [
            'url' => 'http://localhost:18789',
            'token' => 'test-token',
            'timeout' => 120,
        ]);
    }
}
