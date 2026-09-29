<?php

use App\News\Categorization\ArticleCategoryClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('category')->nullable()->after('author');
            $table->index(['category', 'published_at']);
        });

        $classifier = app(ArticleCategoryClassifier::class);

        DB::table('articles')->join('sources', 'sources.id', '=', 'articles.source_id')
            ->select('articles.id', 'articles.canonical_url', 'sources.slug')
            ->orderBy('articles.id')
            ->chunk(500, function ($articles) use ($classifier) {
                foreach ($articles as $article) {
                    $category = $classifier->classify($article->slug, $article->canonical_url);

                    if ($category !== null) {
                        DB::table('articles')->where('id', $article->id)->update(['category' => $category->value]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['category', 'published_at']);
            $table->dropColumn('category');
        });
    }
};
