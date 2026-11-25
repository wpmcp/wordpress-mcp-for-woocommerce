<?php

declare(strict_types=1);

namespace WPMCP\Kernel;

use WPMCP\Api\V1\Contract\Plugin_Presence;
use WPMCP\Api\V1\Value\Requires;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * What is active on this site, read once per request. active_plugins is
 * autoloaded, so the plugin check costs no query and works at our load time
 * even when the other plugin loads after us. Class, function and constant
 * requirements are checked when asked, which callers do after
 * plugins_loaded. The sources are injectable: Presence::with_plugins() is
 * the test seam that replaces Registrar::set_plugin_loaded_for_tests().
 */
final class Presence implements Plugin_Presence
{
    /** @var array<string, true>|null */
    private ?array $active = null;

    /** @var list<string>|null */
    private ?array $themes = null;

    private readonly \Closure $plugins_source;
    private readonly \Closure $themes_source;
    private readonly \Closure $multisite_source;

    public function __construct(?callable $plugins = null, ?callable $themes = null, ?callable $multisite = null)
    {
        $this->plugins_source = \Closure::fromCallable($plugins ?? [self::class, 'site_plugins']);
        $this->themes_source = \Closure::fromCallable($themes ?? [self::class, 'site_themes']);
        $this->multisite_source = \Closure::fromCallable($multisite ?? static fn (): bool => function_exists('is_multisite') && is_multisite());
    }

    /** @param list<string> $plugins */
    public static function with_plugins(array $plugins): self
    {
        return new self(static fn (): array => $plugins, static fn (): array => [], static fn (): bool => false);
    }

    public function plugin(string $file): bool
    {
        if (null === $this->active) {
            $this->active = [];
            foreach ((array) ($this->plugins_source)() as $active) {
                $this->active[ (string) $active ] = true;
            }
        }

        return isset($this->active[ $file ]);
    }

    /** @param Requires|array<string, mixed>|null $requires */
    public function satisfies(Requires|array|null $requires): bool
    {
        if (null === $requires) {
            return true;
        }
        if (is_array($requires)) {
            $requires = Requires::from_array($requires);
        }
        if ([] !== $requires->plugin && ! $this->any_plugin($requires->plugin)) {
            return false;
        }
        if (null !== $requires->class && ! class_exists($requires->class)) {
            return false;
        }
        if (null !== $requires->function && ! function_exists($requires->function)) {
            return false;
        }
        if (null !== $requires->constant && ! defined($requires->constant)) {
            return false;
        }
        if (null !== $requires->theme && ! in_array($requires->theme, $this->themes(), true)) {
            return false;
        }

        return ! $requires->multisite || (bool) ($this->multisite_source)();
    }

    /** @param list<string> $files */
    public function any_plugin(array $files): bool
    {
        foreach ($files as $file) {
            if ($this->plugin($file)) {
                return true;
            }
        }
        return false;
    }

    /** @return list<string> */
    private function themes(): array
    {
        return $this->themes ??= array_values(array_map('strval', (array) ($this->themes_source)()));
    }

    /** @return list<string> */
    private static function site_plugins(): array
    {
        if (! function_exists('get_option')) {
            return [];
        }
        $plugins = array_values((array) get_option('active_plugins', []));
        if (function_exists('is_multisite') && is_multisite() && function_exists('get_site_option')) {
            $plugins = array_merge($plugins, array_keys((array) get_site_option('active_sitewide_plugins', [])));
        }
        return array_map('strval', $plugins);
    }

    /** @return list<string> */
    private static function site_themes(): array
    {
        if (! function_exists('get_option')) {
            return [];
        }
        return array_values(array_unique(array_filter([(string) get_option('template', ''), (string) get_option('stylesheet', '')])));
    }
}
