<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Marketing\MarketingPageRepository;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

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
    public function show(string $slug): Response
    {
        $page = $this->pages->find($slug);

        abort_if($page === null, 404);

        return Inertia::render('Marketing/Show', [
            'page' => [
                'slug' => $page->slug,
                'title' => $page->title,
                'description' => $page->description,
                'headline' => $page->headline,
                'ctaLabel' => $page->ctaLabel,
                'ctaUrl' => $page->ctaUrl,
                'html' => $page->html,
                'noindex' => $page->noindex,
            ],
            'canonicalUrl' => route('marketing.pages.show', ['slug' => $page->slug]),
            'authRoutes' => [
                'registerUrl' => '/#register',
                'loginUrl' => '/#login',
            ],
        ]);
    }

    /**
     * Display a small XML sitemap for public marketing pages.
     */
    public function sitemap(): HttpResponse
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
