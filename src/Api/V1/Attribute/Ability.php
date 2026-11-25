<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Attribute;

use WPMCP\Api\V1\Value\Exposure;
use WPMCP\Api\V1\Value\Op;
use WPMCP\Api\V1\Value\Requires;
use WPMCP\Api\V1\Value\Tier;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * One ability on its handler class (repeatable; $method defaults to handle).
 * $input, $output and $description are constant expressions, or come from
 * pure static $input_factory / $description_factory methods the generator
 * runs at build time. $objects and $op_capability name instance methods on
 * the handler. $condition is a static callable run at registration, the
 * way today's registration-time probes are. $position keeps today's order
 * inside the module (D5).
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class Ability
{
    /**
     * @param array<string, mixed> $input
     * @param list<string> $read_keys
     * @param array{0: class-string, 1: string}|null $condition
     * @param array<string, mixed>|null $output
     * @param list<string> $exclude_flavors
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description = '',
        public readonly array $input = ['type' => 'object', 'properties' => []],
        public readonly string $capability = 'edit_posts',
        public readonly string $domain = 'content',
        public readonly Op $operation = Op::Read,
        public readonly ?int $position = null,
        public readonly Tier|string|null $tier = null,
        public readonly string $method = 'handle',
        public readonly array $read_keys = [],
        public readonly ?bool $read_only = null,
        public readonly ?bool $destructive = null,
        public readonly ?bool $idempotent = null,
        public readonly ?string $objects = null,
        public readonly ?string $op_capability = null,
        public readonly ?Requires $requires = null,
        public readonly ?array $condition = null,
        public readonly ?string $input_factory = null,
        public readonly ?string $description_factory = null,
        public readonly ?array $output = null,
        public readonly ?int $default_disabled_since = null,
        public readonly Exposure $exposure = Exposure::Default,
        public readonly array $exclude_flavors = [],
    ) {
    }
}
