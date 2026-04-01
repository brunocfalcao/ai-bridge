<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Chat;

use BrunoCFalcao\AiBridge\Chat\Models\Conversation;
use BrunoCFalcao\AiBridge\Chat\Models\ConversationMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConversationSummarizer
{
    public function summarizeIfNeeded(string $conversationId, int $maxMessages, array $providerArray): void
    {
        try {
            $totalMessages = ConversationMessage::query()
                ->where('conversation_id', $conversationId)
                ->count();

            if ($totalMessages <= $maxMessages) {
                return;
            }

            $oldMessages = ConversationMessage::query()
                ->where('conversation_id', $conversationId)
                ->orderBy('created_at')
                ->limit($totalMessages - $maxMessages)
                ->get();

            $transcript = $oldMessages->map(function ($msg) {
                $role = strtoupper($msg->role);
                $content = Str::limit($msg->content ?? '', 500);
                $toolLine = '';

                $toolCalls = is_array($msg->tool_calls) ? $msg->tool_calls : json_decode($msg->tool_calls ?? '', true);
                if (! empty($toolCalls)) {
                    $names = collect($toolCalls)->pluck('name')->filter()->join(', ');
                    $toolLine = "\n[TOOLS USED]: {$names}";
                }

                return "[{$role}]: {$content}{$toolLine}";
            })->join("\n\n");

            $existingSummary = Conversation::query()
                ->where('id', $conversationId)
                ->value('summary');

            $prompt = $existingSummary
                ? "Update this conversation summary with the new messages below.\n\nExisting summary:\n{$existingSummary}\n\nNew messages:\n{$transcript}"
                : "Summarize this conversation. Focus on decisions made, actions taken, and key information exchanged.\n\n{$transcript}";

            $response = agent(instructions: 'You are a conversation summarizer. Be concise. If tools like store_knowledge were used, explicitly state what was ALREADY COMPLETED — do NOT re-learn this topic.')
                ->prompt($prompt, provider: $providerArray);

            Conversation::query()
                ->where('id', $conversationId)
                ->update(['summary' => $response->text, 'updated_at' => now()]);

        } catch (\Throwable $e) {
            Log::warning('ConversationSummarizer failed', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function getSummary(string $conversationId): ?string
    {
        return Conversation::query()
            ->where('id', $conversationId)
            ->value('summary');
    }
}
