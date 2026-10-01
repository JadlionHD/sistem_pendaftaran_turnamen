<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->string('ai_status')->nullable()->after('admin_notes'); // passed, flagged, rejected
            $table->unsignedSmallInteger('ai_score')->nullable()->after('ai_status'); // 0-100
            $table->text('ai_summary')->nullable()->after('ai_score');
            $table->json('ai_checklist')->nullable()->after('ai_summary');
            $table->text('ai_recommendation')->nullable()->after('ai_checklist');
            $table->string('ai_cost')->nullable()->after('ai_recommendation'); // X-Atmorouter-Cost
            $table->unsignedInteger('ai_tokens_used')->nullable()->after('ai_cost');
            $table->timestamp('ai_checked_at')->nullable()->after('ai_tokens_used');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'ai_status',
                'ai_score',
                'ai_summary',
                'ai_checklist',
                'ai_recommendation',
                'ai_cost',
                'ai_tokens_used',
                'ai_checked_at',
            ]);
        });
    }
};
