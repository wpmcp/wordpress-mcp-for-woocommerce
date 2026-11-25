<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Activity_Store in user meta: one map per user and site, keyed by the
 * application password's uuid. The key is wpmcp_connection_activity, with
 * the blog id appended on multisite, where user meta is network-wide but
 * each site has its own connections (the MCP adapter keys its sessions the
 * same way).
 *
 * The rows are WPMCP's own; one of any other shape reads as nothing. At
 * most MAX_ROWS passwords are kept per user and site, the least recently
 * active dropped first. Deleting the user deletes it, and uninstall removes
 * it with the rest of the wpmcp_ user meta (Data_Registry).
 */
final class Wp_Activity_Store implements Activity_Store
{
    public const META = 'wpmcp_connection_activity';

    /** Most passwords kept per user and site. */
    public const MAX_ROWS = 32;

    public function find(int $user_id, string $uuid): ?Mcp_Activity
    {
        $row = $this->rows($user_id)[ $uuid ] ?? null;
        if (null === $row) {
            return null;
        }

        return new Mcp_Activity(Mcp_Client::of($row['name'], $row['version']), $row['first_at'], $row['last_at']);
    }

    public function save(int $user_id, string $uuid, Mcp_Activity $activity): void
    {
        if ($user_id <= 0 || '' === $uuid) {
            return;
        }

        $rows          = $this->rows($user_id);
        $rows[ $uuid ] = [
            'first_at' => $activity->first_at,
            'last_at'  => $activity->last_at,
            'name'     => $activity->client->name,
            'version'  => $activity->client->version,
        ];
        if (count($rows) > self::MAX_ROWS) {
            uasort($rows, static fn (array $a, array $b): int => $b['last_at'] <=> $a['last_at']);
            $rows = array_slice($rows, 0, self::MAX_ROWS, true);
        }

        $key = self::META . $this->site_suffix();
        update_user_meta($user_id, $key, $rows);
    }

    public function forget(int $user_id, string $uuid): void
    {
        $rows = $this->rows($user_id);
        if (! isset($rows[ $uuid ])) {
            return;
        }
        unset($rows[ $uuid ]);

        $key = self::META . $this->site_suffix();
        if ([] === $rows) {
            delete_user_meta($user_id, $key);
            return;
        }
        update_user_meta($user_id, $key, $rows);
    }

    /**
     * The well-formed rows of a user on this site, by uuid.
     *
     * @return array<string, array{first_at: int, last_at: int, name: string, version: string}>
     */
    private function rows(int $user_id): array
    {
        if ($user_id <= 0) {
            return [];
        }

        $stored = get_user_meta($user_id, self::META . $this->site_suffix(), true);
        $rows   = [];
        foreach (is_array($stored) ? $stored : [] as $uuid => $row) {
            if (! is_string($uuid) || ! is_array($row) || ! is_int($row['first_at'] ?? null) || ! is_int($row['last_at'] ?? null)) {
                continue;
            }
            $rows[ $uuid ] = [
                'first_at' => $row['first_at'],
                'last_at'  => $row['last_at'],
                'name'     => is_string($row['name'] ?? null) ? $row['name'] : '',
                'version'  => is_string($row['version'] ?? null) ? $row['version'] : '',
            ];
        }

        return $rows;
    }

    /** "_{blog_id}" on multisite, nothing on a single site. */
    private function site_suffix(): string
    {
        $blog_id = get_current_blog_id();

        return is_multisite() && $blog_id > 0 ? '_' . $blog_id : '';
    }
}
