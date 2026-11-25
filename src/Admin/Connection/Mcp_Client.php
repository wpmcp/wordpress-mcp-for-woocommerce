<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

use WPMCP\MCP\Protocol_Revision;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The MCP client a request names, as it names itself: clientInfo in the
 * params of an initialize (2025-11-25 and earlier), or in params._meta on
 * every request of 2026-07-28, which has no initialize. The one place WPMCP
 * reads a client's name, whichever revision it speaks (issue #1258).
 *
 * The text is untrusted network input: only a string or a number is kept,
 * as plain text without tags or control characters, at most TEXT_MAX
 * characters. Whatever prints it escapes it again.
 */
final class Mcp_Client
{
    /** Longest client name or version kept from what a client reports. */
    public const TEXT_MAX = 64;

    private function __construct(
        public readonly string $name,
        public readonly string $version,
    ) {
    }

    /**
     * A client from a name and a version as received or as stored.
     *
     * @param mixed $name    Whatever the client sent or a store kept: only a string or a number becomes text.
     * @param mixed $version The same.
     */
    public static function of(mixed $name, mixed $version): self
    {
        return new self(self::text($name), self::text($version));
    }

    /** A request that names no client, such as a 2025 request after initialize. */
    public static function unnamed(): self
    {
        return new self('', '');
    }

    /**
     * The client a decoded JSON-RPC request names: an initialize's clientInfo,
     * otherwise the clientInfo in its _meta.
     *
     * @param array<mixed> $message
     */
    public static function from_message(array $message): self
    {
        $params = $message['params'] ?? null;
        if (! is_array($params)) {
            return self::unnamed();
        }

        $info = 'initialize' === ($message['method'] ?? null) ? ($params['clientInfo'] ?? null) : null;
        if (! is_array($info)) {
            $meta = $params['_meta'] ?? null;
            $info = is_array($meta) ? ($meta[ Protocol_Revision::META_CLIENT_INFO ] ?? null) : null;
        }

        return self::from_info($info);
    }

    /**
     * The client an initialize's params name, as sent or as the MCP adapter
     * stored them with its session.
     *
     * @param mixed $params Stored by the MCP adapter, so of any shape.
     */
    public static function from_initialize_params(mixed $params): self
    {
        return self::from_info(is_array($params) ? ($params['clientInfo'] ?? null) : null);
    }

    public function is_named(): bool
    {
        return '' !== $this->name;
    }

    /** The name and the version, such as "claude-code 2.1.330"; empty for a client that gave no name. */
    public function label(): string
    {
        return $this->is_named() ? trim($this->name . ' ' . $this->version) : '';
    }

    public function equals(self $other): bool
    {
        return $this->name === $other->name && $this->version === $other->version;
    }

    /** @param mixed $info A clientInfo object as decoded, of any shape. */
    private static function from_info(mixed $info): self
    {
        return is_array($info) ? self::of($info['name'] ?? null, $info['version'] ?? null) : self::unnamed();
    }

    /** @param mixed $value Untrusted: only a string or a number becomes text. */
    private static function text(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return '';
        }

        $text = (string) $value;
        if (! mb_check_encoding($text, 'UTF-8')) {
            return '';
        }

        // Tags out (a regular expression, not wp_strip_all_tags(): this class
        // runs without WordPress in the fast tier), then every run of
        // whitespace, control and format characters (bidi overrides
        // included) becomes one space.
        $text = (string) preg_replace([ '/<[^<>]*>/u', '/[\s\p{Cc}\p{Cf}]+/u' ], [ '', ' ' ], $text);

        return trim(mb_substr(trim($text), 0, self::TEXT_MAX));
    }
}
