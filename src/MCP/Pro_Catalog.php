<?php

declare(strict_types=1);

namespace WPMCP\MCP;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The Pro tools a copy without the Pro code knows by name (issue #628).
 *
 * The free zip carries none of the Pro code, so none of its abilities is
 * declared there and a call for one used to be "Tool not found". The build
 * writes their names, and nothing else, to build/registry/pro-tools.php
 * (the registry generator, from the ability manifest), so the Registrar can
 * answer such a call with the pro_required refusal. Where the Pro code runs,
 * its own abilities answer, and the catalog is empty. Only the call path
 * reads it: the tools stay out of tools/list.
 */
final class Pro_Catalog
{
    /** The compiled registry data file the names come from. */
    public const DATA_FILE = 'pro-tools';

    /** @var list<string> */
    private readonly array $names;

    /** @param array<array-key, mixed> $names ability names; anything that is not a string is left out */
    public function __construct(array $names = [])
    {
        $this->names = array_values(array_filter($names, 'is_string'));
    }

    /**
     * The catalog a copy answers with: the build's names, or none where the
     * Pro code is loaded.
     *
     * @param array<array-key, mixed> $names what the build wrote
     */
    public static function for_build(bool $pro_code_loaded, array $names): self
    {
        return new self($pro_code_loaded ? [] : $names);
    }

    public function has(string $ability): bool
    {
        return in_array($ability, $this->names, true);
    }

    /** @return list<string> */
    public function names(): array
    {
        return $this->names;
    }
}
