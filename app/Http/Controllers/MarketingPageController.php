<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Marketing\MarketingPageRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

/**
 * Render repo-managed public marketing pages.
 */
final class MarketingPageController extends Controller
{
    public function __construct(
        private readonly MarketingPageRepository $pages,
    ) {
    }

    /**
     * Display one markdown-powered marketing page.
     */
    public function show(string $slug): View
    {
        $page = $this->pages->find($slug);

        abort_if($page === null, 404);

        return view('marketing.show', [
            'page' => $page,
            'canonicalUrl' => route('marketing.pages.show', ['slug' => $page->slug]),
        ]);
    }

    /**
     * Display a small XML sitemap for public marketing pages.
     */
    public function sitemap(): Response
    {
        $urls = collect($this->pages->all())
            ->map(fn ($page): string => route('marketing.pages.show', ['slug' => $page->slug]))
            ->prepend(url('/'))
            ->values();

        return response()
            ->view('marketing.sitemap', ['urls' => $urls], 200)
            ->header('Content-Type', 'application/xml');
    }
}
