<?php

declare(strict_types=1);

namespace WPMCP\Tools\Revisions;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * One way to report a revision's time (issue #1199): UTC as stored in the
 * *_gmt columns, and the same moment as ISO 8601 with its +00:00 offset,
 * so list-revisions and diff-revisions never disagree about a revision.
 */
final class Revision_Time
{
    private function __construct()
    {
    }

    /** A *_gmt column value as ISO 8601 UTC, or '' for an empty or zero date. */
    public static function iso_utc(string $gmt): string
    {
        if ('' === $gmt || '0000-00-00 00:00:00' === $gmt) {
            return '';
        }
        $ts = strtotime($gmt . ' UTC');
        return false === $ts ? '' : gmdate('c', $ts);
    }

    /**
     * @return array{date_gmt: string, date_iso: string}
     */
    public static function fields(\WP_Post $post): array
    {
        $gmt = (string) $post->post_date_gmt;
        return [
            'date_gmt' => $gmt,
            'date_iso' => self::iso_utc($gmt),
        ];
    }
}
