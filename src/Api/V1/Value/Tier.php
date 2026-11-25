<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Value;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The tier an ability, module or operation belongs to. Add-on tiers are
 * strings of the form addon:<slug> and are accepted wherever a Tier is.
 */
enum Tier: string
{
    case Free = 'free';
    case Pro = 'pro';

    public const ADDON_PREFIX = 'addon:';

    /**
     * The tier as the string today's Ability and Registrar use.
     *
     * @throws \InvalidArgumentException for anything that is not free, pro or addon:<slug>.
     */
    public static function normalize(self|string $tier): string
    {
        if ($tier instanceof self) {
            return $tier->value;
        }
        if (null !== self::tryFrom($tier)) {
            return $tier;
        }
        if (str_starts_with($tier, self::ADDON_PREFIX) && 1 === preg_match('/^[a-z0-9][a-z0-9-]*$/', substr($tier, strlen(self::ADDON_PREFIX)))) {
            return $tier;
        }
        throw new \InvalidArgumentException(sprintf('Unknown tier "%s": use free, pro or addon:<slug>.', $tier)); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- an exception message for developers, never echoed.
    }
}
