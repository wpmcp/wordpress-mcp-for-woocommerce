<?php

declare(strict_types=1);

namespace WPMCP\Safety;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The direct replies of a comment, kept with its undo point (issue #1200).
 *
 * wp_delete_comment() hands a deleted comment's direct replies to its own
 * parent. An undo that brings the comment back under a new id points those
 * replies at it again, but only the ones still where the delete left them,
 * so a reply moved since is not overwritten.
 */
final class Comment_Replies
{
    private function __construct()
    {
    }

    /** @return int[] ids of the comment's direct replies, any status */
    public static function capture(int $comment_id): array
    {
        if ($comment_id <= 0) {
            return [];
        }
        $ids = get_comments([
            'parent'  => $comment_id,
            'status'  => 'all',
            'type'    => 'all',
            'fields'  => 'ids',
            'orderby' => 'comment_ID',
            'order'   => 'ASC',
        ]);
        return array_values(array_map('intval', is_array($ids) ? $ids : []));
    }

    /**
     * Point $reply_ids back at $restored_id where each still has
     * $left_parent, the parent the delete gave it. Returns how many moved.
     *
     * The column is written directly, not through wp_update_comment(), so
     * the save filters never touch the replies' content.
     *
     * @param int[] $reply_ids
     */
    public static function relink(array $reply_ids, int $left_parent, int $restored_id): int
    {
        global $wpdb;

        if ($restored_id <= 0) {
            return 0;
        }
        $moved = 0;
        foreach ($reply_ids as $reply_id) {
            $reply_id = (int) $reply_id;
            $reply    = get_comment($reply_id);
            if (! $reply instanceof \WP_Comment || (int) $reply->comment_parent !== $left_parent) {
                continue;
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one column of a core row, cache cleared below; wp_update_comment() would run the save filters over the content.
            if (false !== $wpdb->update($wpdb->comments, [ 'comment_parent' => $restored_id ], [ 'comment_ID' => $reply_id ], [ '%d' ], [ '%d' ])) {
                clean_comment_cache($reply_id);
                $moved++;
            }
        }
        return $moved;
    }
}
