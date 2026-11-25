<?php

declare(strict_types=1);

namespace WPMCP\Kernel;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A build flavor: which modules and abilities it ships and which abilities
 * it re-tiers. Module membership lives on the modules (flavors and
 * exclude_flavors); the flavor only states the rule.
 */
final class Flavor
{
    /**
     * @param array<string, string> $tier_overrides ability name => tier in this flavor
     */
    public function __construct(
        public readonly string $id,
        public readonly string $main_file,
        public readonly string $text_domain,
        public readonly Ships $ships,
        public readonly bool $lift_with_pro = false,
        public readonly array $tier_overrides = [],
    ) {
        if (1 !== preg_match('/^[a-z][a-z0-9-]*$/', $id)) {
            throw new \InvalidArgumentException(sprintf('Bad flavor id "%s".', $id)); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- an exception message for developers, never echoed.
        }
    }

    public static function full(): self
    {
        return new self(id: 'full', main_file: 'wpmcp.php', text_domain: 'wpmcp', ships: Ships::All);
    }

    /**
     * Whether a compiled module descriptor ships in this flavor.
     *
     * @param array<string, mixed> $module needs tier, flavors and exclude_flavors
     */
    public function includes_module(array $module): bool
    {
        if (in_array($this->id, (array) ($module['exclude_flavors'] ?? []), true)) {
            return false;
        }

        return match ($this->ships) {
            Ships::All => true,
            Ships::Free => 'free' === ($module['tier'] ?? 'free'),
            Ships::Selected => in_array($this->id, (array) ($module['flavors'] ?? []), true),
        };
    }

    /** @param array<string, mixed> $ability needs exclude_flavors */
    public function includes_ability(array $ability): bool
    {
        return ! in_array($this->id, (array) ($ability['exclude_flavors'] ?? []), true);
    }

    public function tier_for(string $ability, string $tier): string
    {
        return $this->tier_overrides[ $ability ] ?? $tier;
    }

    /** @return array{id: string, main_file: string, text_domain: string, ships: string, lift_with_pro: bool, tier_overrides: array<string, string>} */
    public function to_array(): array
    {
        return [
            'id' => $this->id,
            'main_file' => $this->main_file,
            'text_domain' => $this->text_domain,
            'ships' => $this->ships->value,
            'lift_with_pro' => $this->lift_with_pro,
            'tier_overrides' => $this->tier_overrides,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function from_array(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            main_file: (string) ($data['main_file'] ?? ''),
            text_domain: (string) ($data['text_domain'] ?? ''),
            ships: Ships::from((string) ($data['ships'] ?? Ships::All->value)),
            lift_with_pro: (bool) ($data['lift_with_pro'] ?? false),
            tier_overrides: array_map('strval', (array) ($data['tier_overrides'] ?? [])),
        );
    }
}
