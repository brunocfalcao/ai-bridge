<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Console;

use BrunoCFalcao\AiBridge\Chat\ChatManager;
use Illuminate\Console\Command;

class AiChatCommand extends Command
{
    protected $signature = 'ai:chat
        {prompt : The prompt to send to the AI}
        {--connection= : Named AI connection from config (uses default if not set)}
        {--system= : System instructions for the agent}';

    protected $description = 'Send a prompt to an AI provider and display the response';

    public function handle(ChatManager $chat): int
    {
        $prompt = $this->argument('prompt');
        $connection = $this->option('connection');
        $system = $this->option('system');

        $messages = [];

        if ($system) {
            $messages[] = ['role' => 'system', 'content' => $system];
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];

        try {
            if (! $chat->healthy($connection)) {
                $this->warn('Primary provider reports unhealthy — will attempt fallbacks if configured.');
            }

            foreach ($chat->stream($messages, $connection) as $event) {
                if ($event['type'] === 'delta' && $event['content']) {
                    $this->output->write($event['content']);
                }
            }

            $this->newLine();

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
