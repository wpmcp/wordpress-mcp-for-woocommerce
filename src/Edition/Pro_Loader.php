<?php

declare(strict_types=1);

namespace WPMCP\Edition;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Loads the Pro code when this copy carries it, and does nothing otherwise.
 *
 * Pro lives in pro/ (a private submodule in a dev checkout, a plain
 * directory in the premium zip). A file is resolved at pro/<path> first and
 * at the plugin root second, so a build that copies the Pro tree over the
 * plugin paths works too. Only readable files are required: the free zip,
 * which has no pro/, boots the free plugin with every edition slot empty.
 *
 * Classes under pro/src keep their public namespaces (WPMCP\...). Composer
 * maps them in a dev checkout; the loader adds the same mapping as a
 * fallback for a copy whose autoloader was built without pro/src.
 */
final class Pro_Loader
{
    public const BOOTSTRAP = 'src/Pro/bootstrap.php';
    public const DIR = 'pro';

    public function __construct(private readonly string $plugin_dir)
    {
    }

    /** Absolute path of $relative under pro/, else at the plugin root, else null. */
    public function resolve(string $relative): ?string
    {
        $relative = ltrim($relative, '/');
        $root = rtrim($this->plugin_dir, '/');
        foreach ([$root . '/' . self::DIR . '/' . $relative, $root . '/' . $relative] as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function bootstrap_file(): ?string
    {
        return $this->resolve(self::BOOTSTRAP);
    }

    public function src_dir(): ?string
    {
        $dir = rtrim($this->plugin_dir, '/') . '/' . self::DIR . '/src';

        return is_dir($dir) ? $dir : null;
    }

    /**
     * Add the pro/src autoload fallback and require the Pro entry point,
     * pro/src/Pro/bootstrap.php (src/Pro/bootstrap.php in a tree the split
     * has not run on, or in a build that copies Pro over the plugin paths).
     *
     * @return bool true when this copy carries Pro and it was loaded
     */
    public function load(): bool
    {
        $bootstrap = $this->bootstrap_file();
        if (null === $bootstrap) {
            return false;
        }
        $src = $this->src_dir();
        if (null !== $src) {
            spl_autoload_register(static function (string $class) use ($src): void {
                if (! str_starts_with($class, 'WPMCP\\')) {
                    return;
                }
                $file = $src . '/' . str_replace('\\', '/', substr($class, 6)) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            });
        }
        require_once $bootstrap;

        return true;
    }
}
