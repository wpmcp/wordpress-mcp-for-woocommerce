<?php

declare(strict_types=1);

namespace WPMCP\MCP;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * One refusal, as the transports send it (issue #628): its code, its
 * message, the data it carried, and the unavailability contract of issue
 * #627 (Unavailable::to_array()) when it carries one.
 *
 * A tool refuses in one of two ways. Most return a WP_Error. The dispatcher
 * pairs (Integration_Dispatcher) and the WooCommerce catalog answer with an
 * array instead, {integration, error: {code, message, data}}; the ability
 * contract keeps that shape, and on the wire it is the same refusal. of()
 * reads both and leaves every other outcome alone. Refusal_Result turns one
 * into the tool result.
 */
final class Refusal
{
    /**
     * @param array<array-key, mixed>      $data        what the refusal carried, without the HTTP status hint and the contract
     * @param array<array-key, mixed>|null $unavailable the issue #627 contract, when the refusal carries one
     */
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly array $data = [],
        public readonly ?array $unavailable = null,
    ) {
    }

    /**
     * The refusal a tool outcome is, or null when the outcome is a result.
     *
     * @param mixed $outcome what the ability returned (open: any tool result)
     */
    public static function of(mixed $outcome): ?self
    {
        if ($outcome instanceof \WP_Error) {
            return self::from_error($outcome);
        }
        if (! is_array($outcome) || 2 !== count($outcome) || ! is_string($outcome['integration'] ?? null) || ! is_array($outcome['error'] ?? null)) {
            return null;
        }

        $integration = $outcome['integration'];
        $code        = $outcome['error']['code'] ?? null;
        $message     = $outcome['error']['message'] ?? null;
        $data        = $outcome['error']['data'] ?? [];
        if (! is_string($code) || ! is_string($message)) {
            return null;
        }

        return self::with_data($code, $message, (is_array($data) ? $data : []) + [ 'integration' => $integration ]);
    }

    public static function from_error(\WP_Error $error): self
    {
        $data = $error->get_error_data();
        if (! is_array($data)) {
            $data = null === $data ? [] : [ 'value' => $data ];
        }

        return self::with_data((string) $error->get_error_code(), $error->get_error_message(), $data);
    }

    /** @param array<array-key, mixed> $data */
    private static function with_data(string $code, string $message, array $data): self
    {
        $contract = $data['unavailable'] ?? null;
        unset($data['status'], $data['unavailable']);

        return new self($code, $message, $data, is_array($contract) && is_string($contract['reason'] ?? null) ? $contract : null);
    }
}
