<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_summary_articles', function (Blueprint $table) {
            $table->foreignId('article_id')->primary()->constrained()->restrictOnDelete();
            $table->foreignId('daily_summary_id')->constrained()->restrictOnDelete();
        });

        DB::table('daily_summaries')->orderBy('id')->select(['id', 'draft_blocks', 'published_blocks'])
            ->chunk(100, function ($summaries): void {
                foreach ($summaries as $summary) {
                    foreach (['draft_blocks', 'published_blocks'] as $field) {
                        $blocks = json_decode($summary->{$field} ?? '[]', true) ?: [];

                        foreach ($blocks as $block) {
                            foreach ($block['article_ids'] ?? [] as $articleId) {
                                DB::table('daily_summary_articles')->insertOrIgnore([
                                    'article_id' => $articleId,
                                    'daily_summary_id' => $summary->id,
                                ]);
                            }
                        }
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_summary_articles');
    }
};
