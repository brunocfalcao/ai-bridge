<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConversationSummarizer
{
    public function summarizeIfNeeded(string $conversationId, int $maxMessages, array $providerArray): void
    {
        try {
            $totalMessages = DB::table('ai_conversation_messages')
                ->where('conversation_id', $conversationId)
                ->count();

            if ($totalMessages <= $maxMessages) {
                return;
            }

            $oldMessages = DB::table('ai_conversation_messages')
                ->where('conversation_id', $conversationId)
                ->orderBy('created_at')
                ->limit($totalMessages - $maxMessages)
                ->get();

            $transcript = $oldMessages->map(function ($msg) {
                $role = strtoupper($msg->role);
                $content = Str::limit($msg->content ?? '', 500);
                $toolLine = '';

                if ($msg->tool_calls) {
                    $tools = json_decode($msg->tool_calls, true);
                    if (! empty($tools)) {
                        $names = collect($tools)->pluck('name')->filter()->join(', ');
                        $toolLine = "\n[TOOLS USED]: {$names}";
                    }
                }

                return "[{$role}]: {$content}{$toolLine}";
            })->join("\n\n");

            $existingSummary = DB::table('ai_conversations')
                ->where('id', $conversationId)
                ->value('summary');

            $prompt = $existingSummary
                ? "Update this conversation summary with the new messages below.\n\nExisting summary:\n{$existingSummary}\n\nNew messages:\n{$transcript}"
                : "Summarize this conversation. Focus on decisions made, actions taken, and key information exchanged.\n\n{$transcript}";

            $response = agent(instructions: 'You are a conversation summarizer. Be concise. If tools like store_knowledge were used, explicitly state what was ALREADY COMPLETED — do NOT re-learn this topic.')
                ->prompt($prompt, provider: $providerArray);

            DB::table('ai_conversations')
                ->where('id', $conversationId)
                ->update(['summary' => $response->text, 'updated_at' => now()]);

        } catch (\Throwable) {
            // Non-critical — errors are logged but don't break flow
        }
    }

    public static function getSummary(string $conversationId): ?string
    {
        return DB::table('ai_conversations')
            ->where('id', $conversationId)
            ->value('summary');
    }
}
