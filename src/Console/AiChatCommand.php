<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Console;

use BrunoCFalcao\AiBridge\Resolver\AiResolver;
use Illuminate\Console\Command;

class AiChatCommand extends Command
{
    protected $signature = 'ai:chat
        {prompt : The prompt to send to the AI}
        {--connection= : Named AI connection from config (uses default if not set)}
        {--system= : System instructions for the agent}';

    protected $description = 'Send a prompt to an AI provider and display the response';

    public function handle(AiResolver $resolver): int
    {
        $prompt = $this->argument('prompt');
        $connection = $this->option('connection');
        $system = $this->option('system');

        if ($connection) {
            $providers = $resolver->using($connection);
            [$primaryProvider, $primaryModel] = $resolver->primary($connection);
        } else {
            $providers = $resolver->using('__default__');
            $configKey = config('ai-bridge.ai_config_key', 'ai-bridge.resolver');
            $default = config("{$configKey}.default", 'gemini:gemini-2.5-flash');
            $pos = strpos($default, ':');
            $primaryProvider = $pos !== false ? substr($default, 0, $pos) : $default;
            $primaryModel = $pos !== false ? substr($default, $pos + 1) : '';
        }

        $this->line("Provider: <info>{$primaryProvider}</info> | Model: <info>{$primaryModel}</info>");

        if ($connection) {
            $fallbackCount = count($providers) - 1;
            if ($fallbackCount > 0) {
                $this->line("Fallbacks: <comment>{$fallbackCount}</comment>");
            }
        }

        $this->newLine();

        $instructions = $system ?? '';

        try {
            $agent = \Laravel\Ai\agent(instructions: $instructions);
            $response = $agent->prompt($prompt, provider: $providers);

            $this->line((string) $response);
            $this->newLine();

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
