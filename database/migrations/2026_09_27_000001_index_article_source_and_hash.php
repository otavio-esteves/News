<?php

use App\News\Deduplication\ContentFingerprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $fingerprint = new ContentFingerprint;

        DB::table('articles')->whereNotNull('content')->orderBy('id')->chunkById(100, function ($articles) use ($fingerprint): void {
            foreach ($articles as $article) {
                DB::table('articles')->where('id', $article->id)->update([
                    'content_hash' => $fingerprint->hash($article->content),
                ]);
            }
        });

        Schema::table('articles', function (Blueprint $table): void {
            $table->index(['source_id', 'content_hash']);
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropIndex(['source_id', 'content_hash']);
        });
    }
};
