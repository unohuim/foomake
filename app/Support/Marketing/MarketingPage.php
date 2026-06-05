<?php

declare(strict_types=1);

namespace App\Support\Marketing;

/**
 * Immutable read model for a repo-managed marketing markdown page.
 */
final readonly class MarketingPage
{
    /**
     * @param array<string, mixed> $frontMatter
     */
    public function __construct(
        public string $slug,
        public string $title,
        public string $description,
        public string $headline,
        public string $ctaLabel,
        public string $ctaUrl,
        public string $html,
        public bool $noindex,
        public array $frontMatter,
    ) {
    }
}
