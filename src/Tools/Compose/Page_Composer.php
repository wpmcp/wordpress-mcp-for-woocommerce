<?php

declare(strict_types=1);

namespace WPMCP\Tools\Compose;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Turns a validated build-page section tree into one dialect's output.
 * Block_Composer covers the free gutenberg dialect; other dialects arrive
 * through the wpmcp_page_composers filter (Page_Composers).
 */
interface Page_Composer
{
    /**
     * @param list<array<mixed>> $sections the spec's validated content
     * @return array<string, mixed> the composed result (shape set by the dialect)
     */
    public function compose(array $sections): array;
}
