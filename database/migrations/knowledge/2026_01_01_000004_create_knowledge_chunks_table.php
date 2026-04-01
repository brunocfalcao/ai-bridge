<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * This migration is meant to be run on per-project PostgreSQL databases,
     * NOT on the main MySQL database.
     */
    public function up(): void
    {
        // Enable pgvector extension
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->string('source_type', 50);
            $table->string('source_url')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        // Add vector column (pgvector)
        $dimensions = config('ai-bridge.knowledge.embedding_dimensions', 1536);
        DB::statement("ALTER TABLE knowledge_chunks ADD COLUMN embedding vector({$dimensions})");
        DB::statement('CREATE INDEX knowledge_chunks_embedding_idx ON knowledge_chunks USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
    }
};
