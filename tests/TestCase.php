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
                'bridge' => 'anthropic:claude-opus-4-6',
            ],
            'fallbacks' => [
                'anthropic' => 'openrouter:anthropic/claude-sonnet-4',
                'openrouter' => 'gemini:gemini-3-flash-preview',
            ],
            'default' => 'anthropic:claude-opus-4-6',
        ]);
    }
}
