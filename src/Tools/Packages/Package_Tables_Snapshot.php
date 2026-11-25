<?php

declare(strict_types=1);

namespace WPMCP\Tools\Packages;

use WPMCP\Tools\Backup\Db_Dumper;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The package-owned tables part of a safe package update (issue #843):
 * dump them before the update, replay the dump when the update is rolled
 * back.
 *
 * The replay goes through the SQL importer, which the WooCommerce wp.org build
 * does not ship (issue #1147). That build's strip turns available() false
 * and replay() into a warning, so its safe updates still archive and put
 * back the package's files and the site's options, and say plainly that the
 * package's own tables are not covered.
 */
final class Package_Tables_Snapshot
{
    private function __construct()
    {
    }

    /** Whether this build can save and put back a package's own tables. */
    public static function available(): bool
    {
        return false;
    }

    /** What a build without the SQL importer says about package tables. */
    public static function unavailable_reason(): string
    {
        return 'This build has no SQL importer, so the package\'s own tables are not saved or put back; its files and the site options are.';
    }

    /**
     * Dump $tables to $path.
     *
     * @param string[] $tables
     */
    public static function dump(string $path, array $tables): void
    {
        if (false === file_put_contents($path, '')) {
            throw new \RuntimeException('the dump file could not be created');
        }
        $buffer = '';
        $flush  = static function (string $chunk) use ($path): void {
            if ('' !== $chunk && false === file_put_contents($path, $chunk, FILE_APPEND)) {
                throw new \RuntimeException('writing the dump failed (out of disk space?)');
            }
        };
        (new Db_Dumper())->dump(
            static function (string $sql) use (&$buffer, $flush): void {
                $buffer .= $sql;
                if (strlen($buffer) < 1048576) {
                    return;
                }

                $flush($buffer);
                $buffer = '';
            },
            $tables
        );
        $flush($buffer);
    }

    /**
     * Put $tables back from the dump at $path.
     *
     * @param string[] $tables
     * @return string[] warnings
     */
    public static function replay(string $path, array $tables): array
    {
        unset($path, $tables);

        return [ self::unavailable_reason() ];
    }

}
