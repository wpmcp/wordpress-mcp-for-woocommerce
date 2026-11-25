<?php

declare(strict_types=1);

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps -- WP-style snake_case class name is intentional (matches the rest of WPMCP\Safety).
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps -- WP-style snake_case method names are intentional (matches the rest of WPMCP\Safety).

namespace WPMCP\Safety;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The post-purchase offers of a FunnelKit funnel, for its snapshots (issue #1021).
 *
 * Funnel Builder Pro keeps a post-purchase offer step's offers outside the funnel's
 * steps JSON: each offer is a wfocu_offer post (linked back to its step by
 * _funnel_id) and the step lists them, in order, in its _funnel_steps post
 * meta, one entry per offer with its id, type, name and state. So that a
 * funnel snapshot can take back an offer write, FunnelKit_Funnel_Snapshot
 * counts these offer posts among the funnel's step posts (offer_ids()), and
 * after a restore puts the restored lists in line with what stays
 * (reconcile()): an offer added since is kept and stays listed, one deleted
 * since is not listed.
 */
final class FunnelKit_Offer_Lists
{
    /** The post type of a post-purchase offer step. */
    public const UPSELL_POST_TYPE = 'wfocu_funnel';

    /** The meta key a post-purchase offer step lists its offers in. */
    public const LIST_KEY = '_funnel_steps';

    /**
     * The offer post ids the post-purchase offer steps of a funnel row list, in order.
     *
     * @param array<string,mixed> $row
     * @return int[]
     */
    public static function offer_ids(array $row): array
    {
        $ids = [];
        foreach (self::lists($row) as $list) {
            foreach ($list as $entry) {
                $ids[] = self::entry_id($entry);
            }
        }
        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /**
     * Each post-purchase offer step of a funnel row with its stored offer list.
     *
     * @param array<string,mixed> $row
     * @return array<int,array<int|string,mixed>>
     */
    public static function lists(array $row): array
    {
        $steps = json_decode((string) ($row['steps'] ?? ''), true);
        $out   = [];
        foreach (is_array($steps) ? $steps : [] as $step) {
            $step_id = is_array($step) ? (int) ($step['id'] ?? 0) : 0;
            if ($step_id > 0 && self::UPSELL_POST_TYPE === get_post_type($step_id)) {
                $out[ $step_id ] = self::stored($step_id);
            }
        }
        return $out;
    }

    /**
     * After a restore, bring the restored row's offer lists in line with
     * what stays: the offers deleted since are taken out, and the offers
     * added since go back at the end of their step's list, with the entry
     * the step held for them just before the restore.
     *
     * @param array<string,mixed>                $row    the funnel row as restored.
     * @param array<int,array<int|string,mixed>> $before the lists as they were just before the restore (lists()).
     * @param int[]                              $later  offer posts added since, which stay.
     * @param int[]                              $gone   captured offer posts deleted since, not brought back.
     */
    public static function reconcile(array $row, array $before, array $later, array $gone): void
    {
        $later = array_map('intval', $later);
        $gone  = array_map('intval', $gone);
        foreach (self::lists($row) as $step_id => $stored) {
            $list   = array_values(array_filter($stored, static fn ($entry): bool => ! in_array(self::entry_id($entry), $gone, true)));
            $listed = array_map([ self::class, 'entry_id' ], $list);
            foreach ((array) ($before[ $step_id ] ?? []) as $entry) {
                $id = self::entry_id($entry);
                if (in_array($id, $later, true) && ! in_array($id, $listed, true)) {
                    $list[]   = $entry;
                    $listed[] = $id;
                }
            }
            if ($list !== array_values($stored)) {
                update_post_meta($step_id, self::LIST_KEY, $list);
            }
            self::refresh($step_id);
        }
    }

    /** @return array<int|string,mixed> */
    private static function stored(int $step_id): array
    {
        $list = get_post_meta($step_id, self::LIST_KEY, true);
        return is_array($list) ? $list : [];
    }

    /** @param mixed $entry */
    private static function entry_id($entry): int
    {
        if (is_object($entry)) {
            $entry = get_object_vars($entry);
        }
        return is_array($entry) ? (int) ($entry['id'] ?? 0) : 0;
    }

    /**
     * Drop the offer list the post-purchase offer module keeps for the rest of the
     * request (WFOCU_Funnels::get_funnel_steps() caches it per step), so its
     * next write starts from the restored list.
     */
    public static function refresh(int $step_id): void
    {
        if (! function_exists('WFOCU_Core')) {
            return;
        }
        try {
            $core    = WFOCU_Core();
            $funnels = is_object($core) ? ($core->funnels ?? null) : null;
            if (is_object($funnels) && method_exists($funnels, 'get_funnel_steps')) {
                $funnels->get_funnel_steps($step_id, false);
            }
        } catch (\Throwable $e) {
            unset($e);
        }
    }
}
