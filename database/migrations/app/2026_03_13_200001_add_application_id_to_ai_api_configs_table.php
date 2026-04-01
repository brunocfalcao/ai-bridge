<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_api_configs', function (Blueprint $table) {
            $table->foreignId('application_id')->nullable()->after('team_id')->constrained('applications')->nullOnDelete();
        });

        // Replace unique constraint to include application_id
        Schema::table('ai_api_configs', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropUnique(['team_id', 'provider', 'purpose']);
            $table->unique(['team_id', 'application_id', 'provider', 'purpose']);
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_api_configs', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropUnique(['team_id', 'application_id', 'provider', 'purpose']);
            $table->dropForeign(['application_id']);
            $table->dropColumn('application_id');
            $table->unique(['team_id', 'provider', 'purpose']);
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
        });
    }
};
