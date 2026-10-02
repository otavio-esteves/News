<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\DailySummary;
use App\News\Editorial\ReviewDailySummary;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDailySummaryController extends Controller
{
    public function index(): View
    {
        return view('admin.daily-summaries.index', [
            'summaries' => DailySummary::whereNotNull('draft_blocks')
                ->orWhereNotNull('published_blocks')->orderByDesc('date')->paginate(20),
        ]);
    }

    public function show(DailySummary $daily): View
    {
        $blocks = $daily->draft_blocks ?? $daily->published_blocks ?? [];
        $articles = Article::with('source')->whereIn('id', collect($blocks)
            ->flatMap(fn (array $block): array => $block['article_ids'])->unique())->get()->keyBy('id');

        return view('admin.daily-summaries.show', compact('daily', 'blocks', 'articles'));
    }

    public function approve(DailySummary $daily, ReviewDailySummary $review, Request $request): RedirectResponse
    {
        try {
            $review->approve($daily, $request->user());
        } catch (DomainException $exception) {
            return back()->withErrors(['review' => $exception->getMessage()]);
        }

        return redirect()->route('admin.daily-summaries.index')->with('status', 'Resumo diário publicado.');
    }

    public function reject(DailySummary $daily, ReviewDailySummary $review): RedirectResponse
    {
        $review->reject($daily);

        return redirect()->route('admin.daily-summaries.index')->with('status', 'Atualização do resumo diário descartada.');
    }
}
