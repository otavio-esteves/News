<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->json('draft_blocks')->nullable();
            $table->timestamp('draft_slot_at')->nullable();
            $table->json('published_blocks')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('daily_summary_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_summary_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->json('blocks');
            $table->foreignId('published_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['daily_summary_id', 'version']);
        });

        Schema::table('ai_runs', function (Blueprint $table) {
            $table->foreignId('daily_summary_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_runs', fn (Blueprint $table) => $table->dropConstrainedForeignId('daily_summary_id'));
        Schema::dropIfExists('daily_summary_revisions');
        Schema::dropIfExists('daily_summaries');
    }
};
