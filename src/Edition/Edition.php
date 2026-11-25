<?php

declare(strict_types=1);

namespace WPMCP\Edition;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The edition seam: the free tree names no Pro class. Pro code (pro/, loaded
 * by Pro_Loader) hooks in through the filters named here, and the free code
 * asks this class at each seam.
 *
 *  - SLOTS_FILTER: list<Edition_Slot>, the contributors Plugin.php's slot
 *    calls run, in registration order.
 *  - REGISTRY_ROOTS_FILTER: list<string>, extra compiled v2 registry roots
 *    (build/registry directories), read after the free root.
 *
 * Pro skills use the existing wpmcp_skill_sources filter (Skill_Library).
 */
final class Edition
{
    public const SLOTS_FILTER = 'wpmcp_edition_slots';
    public const REGISTRY_ROOTS_FILTER = 'wpmcp_registry_roots';

    /** @param list<Edition_Slot> $slots */
    public function __construct(private readonly array $slots)
    {
    }

    public static function from_hooks(): self
    {
        $slots = apply_filters('wpmcp_edition_slots', []);

        return new self(self::only_slots($slots));
    }

    /**
     * Run $slot on every contributor. A slot no contributor knows is a no-op.
     */
    public function run(string $slot, Edition_Context $context): void
    {
        foreach ($this->slots as $contributor) {
            $contributor->run($slot, $context);
        }
    }

    public function has_contributors(): bool
    {
        return [] !== $this->slots;
    }

    /**
     * Extra compiled registry roots, as absolute paths that exist.
     *
     * @return list<string>
     */
    public static function registry_roots(): array
    {
        return self::existing_dirs(apply_filters('wpmcp_registry_roots', []));
    }

    /** @return list<Edition_Slot> */
    private static function only_slots(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $slot): bool => $slot instanceof Edition_Slot));
    }

    /** @return list<string> */
    private static function existing_dirs(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $dirs = [];
        foreach ($value as $dir) {
            if (is_string($dir) && is_dir($dir)) {
                $dirs[] = rtrim($dir, '/');
            }
        }

        return array_values(array_unique($dirs));
    }
}
