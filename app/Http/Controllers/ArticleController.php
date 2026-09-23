<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Voltiva\ArticleTopic;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * News & Advice: the article listing, filterable by topic, and the one
 * article template.
 */
class ArticleController extends Controller
{
    /**
     * The listing. An unknown ?topic= is ignored rather than a 404, since
     * it is a filter and the unfiltered list is a sensible answer.
     */
    public function index(Request $request): View
    {
        // A string check before tryFrom(): ?topic[]=x arrives as an array,
        // and casting that to a string is an error page, not a filter.
        $requested = $request->query('topic');
        $topic = is_string($requested) ? ArticleTopic::tryFrom($requested) : null;

        return view('news.index', [
            'articles' => Article::query()
                ->published()
                ->when($topic, fn ($query) => $query->where('topic', $topic))
                ->latestFirst()
                ->paginate(9)
                ->withQueryString(),
            'topic' => $topic,
        ]);
    }

    /**
     * One article. Drafts render only for people who may edit them.
     */
    public function show(Request $request, Article $article): View
    {
        abort_unless($article->isVisible() || $request->user()?->can('update', $article), 404);

        $article->load('vehicle');

        return view('news.show', [
            'article' => $article,
            'related' => Article::query()
                ->published()
                ->whereKeyNot($article->id)
                ->where('topic', $article->topic)
                ->latestFirst()
                ->limit(3)
                ->get(),
        ]);
    }
}
