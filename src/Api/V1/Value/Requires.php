<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Value;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * What a module, operation contributor or ability needs from the site.
 * Every named kind must hold; within $plugin any one main file satisfies.
 * The refusal facts ($name, $slug, $url, $several) feed Plugin_Refusal.
 */
final class Requires
{
    /**
     * @param list<string> $plugin main plugin files, any one satisfies (a cheap active_plugins check)
     */
    public function __construct(
        public readonly array $plugin = [],
        public readonly ?string $class = null,
        public readonly ?string $function = null,
        public readonly ?string $constant = null,
        public readonly ?string $theme = null,
        public readonly bool $multisite = false,
        public readonly ?string $name = null,
        public readonly ?string $slug = null,
        public readonly ?string $url = null,
        public readonly bool $several = false,
    ) {
    }

    /**
     * The compiled form: only the fields that are set, in declaration order.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array
    {
        $out = [];
        foreach (get_object_vars($this) as $key => $value) {
            if (null === $value || [] === $value || false === $value) {
                continue;
            }
            $out[ $key ] = $value;
        }
        return $out;
    }

    /** @param array<string, mixed> $data */
    public static function from_array(array $data): self
    {
        return new self(
            plugin: array_values(array_map('strval', (array) ($data['plugin'] ?? []))),
            class: isset($data['class']) ? (string) $data['class'] : null,
            function: isset($data['function']) ? (string) $data['function'] : null,
            constant: isset($data['constant']) ? (string) $data['constant'] : null,
            theme: isset($data['theme']) ? (string) $data['theme'] : null,
            multisite: (bool) ($data['multisite'] ?? false),
            name: isset($data['name']) ? (string) $data['name'] : null,
            slug: isset($data['slug']) ? (string) $data['slug'] : null,
            url: isset($data['url']) ? (string) $data['url'] : null,
            several: (bool) ($data['several'] ?? false),
        );
    }

    public function with_name(string $name): self
    {
        return self::from_array(['name' => $name] + $this->to_array());
    }

    public function is_empty(): bool
    {
        return [] === $this->plugin && null === $this->class && null === $this->function
            && null === $this->constant && null === $this->theme && ! $this->multisite;
    }
}
