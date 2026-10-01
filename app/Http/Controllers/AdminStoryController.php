<?php

namespace App\Http\Controllers;

use App\Models\StoryDraft;
use App\News\Editorial\ReviewStoryDraft;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminStoryController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status') === 'rejected' ? 'rejected' : 'pending';

        return view('admin.stories.index', [
            'status' => $status,
            'drafts' => StoryDraft::query()->where('review_status', $status)
                ->with('story.articles.source')->latest('generated_at')->paginate(20),
        ]);
    }

    public function show(StoryDraft $draft): View
    {
        $draft->load(['story.articles.source', 'reviewer']);

        return view('admin.stories.show', ['draft' => $draft]);
    }

    public function approve(StoryDraft $draft, ReviewStoryDraft $review, Request $request): RedirectResponse
    {
        try {
            $story = $review->approve($draft, $request->user());
        } catch (DomainException $exception) {
            return back()->withErrors(['review' => $exception->getMessage()]);
        }

        return redirect()->route('admin.stories.index')
            ->with('status', "Resumo publicado: {$story->title}");
    }

    public function reject(StoryDraft $draft, ReviewStoryDraft $review, Request $request): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        try {
            $review->reject($draft, $request->user(), $validated['reason']);
        } catch (DomainException $exception) {
            return back()->withErrors(['review' => $exception->getMessage()]);
        }

        return redirect()->route('admin.stories.index')
            ->with('status', 'Rascunho rejeitado e registrado para revisão futura.');
    }
}
