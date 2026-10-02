<?php

use App\Enums\ArticleStatus;
use App\Enums\StoryCategory;
use App\Http\Controllers\AdminDailySummaryController;
use App\Http\Controllers\AdminSessionController;
use App\Http\Controllers\AdminStoryController;
use App\Models\Article;
use App\Models\DailySummary;
use App\Models\Source;
use App\Models\Story;
use App\Support\StoryPresenter;
use Illuminate\Support\Facades\Route;

Route::get('/admin/login', [AdminSessionController::class, 'create'])->name('login');
Route::post('/admin/login', [AdminSessionController::class, 'store'])->middleware('throttle:5,1')->name('admin.login');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:review-stories'])->group(function (): void {
    Route::redirect('/', '/admin/stories');
    Route::post('/logout', [AdminSessionController::class, 'destroy'])->name('logout');
    Route::get('/stories', [AdminStoryController::class, 'index'])->name('stories.index');
    Route::get('/stories/{draft}', [AdminStoryController::class, 'show'])->name('stories.show');
    Route::post('/stories/{draft}/approve', [AdminStoryController::class, 'approve'])->name('stories.approve');
    Route::post('/stories/{draft}/reject', [AdminStoryController::class, 'reject'])->name('stories.reject');
    Route::get('/daily-summaries', [AdminDailySummaryController::class, 'index'])->name('daily-summaries.index');
    Route::get('/daily-summaries/{daily}', [AdminDailySummaryController::class, 'show'])->name('daily-summaries.show');
    Route::post('/daily-summaries/{daily}/approve', [AdminDailySummaryController::class, 'approve'])->name('daily-summaries.approve');
    Route::post('/daily-summaries/{daily}/reject', [AdminDailySummaryController::class, 'reject'])->name('daily-summaries.reject');
});

Route::get('/', function () {
    $sources = Source::whereIn('slug', array_keys(config('news.source_adapters', [])))
        ->where('enabled', true)->orderBy('name')->get();
    $articles = Article::with('source')
        ->whereIn('status', [ArticleStatus::Processed, ArticleStatus::Matched, ArticleStatus::Headline])
        ->whereIn('source_id', $sources->modelKeys())
        ->orderByDesc('published_at')->orderByDesc('id')
        ->simplePaginate(30, ['*'], 'articles_page');

    return view('stories.index', [
        'dailySummary' => DailySummary::whereNotNull('published_blocks')->whereHas('revisions')
            ->orderByDesc('date')->first(),
        'articles' => $articles,
        'activeCategory' => null,
    ]);
})->name('home');

Route::get('/n/{slug}', function (string $slug) {
    $story = Story::published()->fromRealSources()->with('articles.source')
        ->where('slug', $slug)->firstOrFail();

    return view('stories.show', [
        'story' => StoryPresenter::make($story),
    ]);
})->name('stories.show');

Route::get('/fontes/{source:slug}', function (Source $source) {
    abort_unless($source->enabled && array_key_exists($source->slug, config('news.source_adapters', [])), 404);

    return view('stories.index', [
        'dailySummary' => null,
        'articles' => Article::with('source')
            ->whereIn('status', [ArticleStatus::Processed, ArticleStatus::Matched, ArticleStatus::Headline])
            ->where('source_id', $source->id)->orderByDesc('published_at')->orderByDesc('id')
            ->simplePaginate(30, ['*'], 'articles_page'),
        'activeCategory' => null,
        'activeSource' => $source->slug,
        'sourceName' => $source->name,
    ]);
})->name('stories.source');

Route::get('/{category}', function (string $category) {
    $sources = Source::whereIn('slug', array_keys(config('news.source_adapters', [])))
        ->where('enabled', true)->get();

    return view('stories.index', [
        'dailySummary' => null,
        'articles' => Article::with('source')
            ->whereIn('status', [ArticleStatus::Processed, ArticleStatus::Matched, ArticleStatus::Headline])
            ->whereIn('source_id', $sources->modelKeys())
            ->where('category', $category)->orderByDesc('published_at')->orderByDesc('id')
            ->simplePaginate(30, ['*'], 'articles_page'),
        'activeCategory' => $category,
    ]);
})->whereIn('category', StoryCategory::values())->name('stories.category');
