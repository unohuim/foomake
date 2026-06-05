<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use Illuminate\Support\Facades\File;
use League\CommonMark\CommonMarkConverter;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads repo-managed marketing markdown files and renders safe HTML.
 */
final class MarketingPageRepository
{
    private const SLUG_PATTERN = '/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/';

    private readonly CommonMarkConverter $markdown;

    public function __construct()
    {
        $this->markdown = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Find a published marketing page by safe slug.
     */
    public function find(string $slug): ?MarketingPage
    {
        if (! $this->isSafeSlug($slug)) {
            return null;
        }

        $path = $this->pathForSlug($slug);

        if (! File::exists($path)) {
            return null;
        }

        $parsed = $this->parse(File::get($path));
        $frontMatter = $parsed['frontMatter'];

        if (($frontMatter['slug'] ?? null) !== $slug) {
            return null;
        }

        $html = $this->markdown->convert($parsed['body'])->getContent();

        return new MarketingPage(
            slug: $slug,
            title: (string) ($frontMatter['title'] ?? ''),
            description: (string) ($frontMatter['description'] ?? ''),
            headline: (string) ($frontMatter['headline'] ?? $frontMatter['title'] ?? ''),
            ctaLabel: (string) ($frontMatter['cta_label'] ?? 'Start beta access'),
            ctaUrl: (string) ($frontMatter['cta_url'] ?? '/register'),
            html: $html,
            noindex: (bool) ($frontMatter['noindex'] ?? false),
            frontMatter: $frontMatter,
        );
    }

    /**
     * Return all safely loadable marketing pages in slug order.
     *
     * @return array<int, MarketingPage>
     */
    public function all(): array
    {
        $directory = resource_path('content/marketing');

        if (! File::isDirectory($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->filter(fn ($file): bool => $file->getExtension() === 'md')
            ->map(fn ($file): string => $file->getFilenameWithoutExtension())
            ->filter(fn (string $slug): bool => $this->isSafeSlug($slug))
            ->sort()
            ->map(fn (string $slug): ?MarketingPage => $this->find($slug))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Determine whether a slug can safely map to one markdown file.
     */
    public function isSafeSlug(string $slug): bool
    {
        return preg_match(self::SLUG_PATTERN, $slug) === 1;
    }

    /**
     * Parse YAML front matter and markdown body.
     *
     * @return array{frontMatter: array<string, mixed>, body: string}
     */
    private function parse(string $content): array
    {
        if (preg_match('/\A---\R(?<frontMatter>.*?)\R---\R?(?<body>.*)\z/s', $content, $matches) !== 1) {
            return [
                'frontMatter' => [],
                'body' => $content,
            ];
        }

        $frontMatter = Yaml::parse($matches['frontMatter']);

        return [
            'frontMatter' => is_array($frontMatter) ? $frontMatter : [],
            'body' => $matches['body'],
        ];
    }

    /**
     * Build the only allowed content path for a slug.
     */
    private function pathForSlug(string $slug): string
    {
        return resource_path("content/marketing/{$slug}.md");
    }
}
