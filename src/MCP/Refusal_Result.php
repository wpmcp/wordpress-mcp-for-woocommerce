<?php

declare(strict_types=1);

namespace WPMCP\MCP;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The tool result every refusal becomes, on every transport (issue #628).
 *
 * The MCP spec makes a tool that exists but cannot run a tool error, not a
 * JSON-RPC error, so a refusal is a CallToolResult with isError true. It
 * keeps its code and data, which a bare message used to lose, as one
 * envelope sent three ways:
 *
 *  - a text block a model reads. With a reason it opens with
 *    "Unavailable (<reason>): ", then the message (which says what to do),
 *    then the reason's docs anchor; without one it is the message as written;
 *  - the envelope as a JSON text block, for agents that parse text;
 *  - the envelope under _meta["com.wpmcp-pro/unavailable"], for programmatic
 *    clients and the in-admin chat.
 *
 * The envelope is the refusal's code and message, the issue #627 contract
 * (reason, action, docs_url, retryable, retry_after, hidden) when it carries
 * one, and the rest of its data under data. No structuredContent: clients
 * validate it against the tool's outputSchema, which a refusal does not
 * follow. The stdio transport builds every tools/call result, and the HTTP
 * route answers tools/call through it (Modern_Http_Bridge, Http_Tool_Call),
 * so this is the only place the shape is written.
 *
 * Stateless: every answer depends only on its argument.
 */
final class Refusal_Result
{
    public const META_KEY = 'com.wpmcp-pro/unavailable';

    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

    /**
     * The tool result for an outcome that is a refusal (a WP_Error or a
     * dispatcher error envelope), or null for one that is a result.
     *
     * @param mixed $outcome what the ability returned (open: any tool result)
     * @return array<string, mixed>|null
     */
    public static function for_outcome(mixed $outcome): ?array
    {
        $refusal = Refusal::of($outcome);

        return null === $refusal ? null : self::of($refusal);
    }

    /** @return array<string, mixed> */
    public static function for_error(\WP_Error $error): array
    {
        return self::of(Refusal::from_error($error));
    }

    /** @return array<string, mixed> */
    public static function of(Refusal $refusal): array
    {
        $envelope = self::envelope($refusal);
        $json     = json_encode($envelope, self::JSON_FLAGS);
        if (false === $json) {
            // Data no JSON encoder can write (a float that is not finite, a
            // structure too deep): the refusal goes out without it.
            $envelope['data'] = new \stdClass();
            $json             = (string) json_encode($envelope, self::JSON_FLAGS);
        }

        return [
            'isError' => true,
            'content' => [
                [ 'type' => 'text', 'text' => self::text($refusal) ],
                [ 'type' => 'text', 'text' => $json ],
            ],
            '_meta'   => [ self::META_KEY => $envelope ],
        ];
    }

    /**
     * code, reason, message, the rest of the contract, data.
     *
     * @return array<string, mixed>
     */
    public static function envelope(Refusal $refusal): array
    {
        $contract = $refusal->unavailable ?? [];
        $head     = [ 'code' => $refusal->code ];
        if (is_string($contract['reason'] ?? null)) {
            $head['reason'] = $contract['reason'];
        }
        unset($contract['reason'], $contract['code'], $contract['message'], $contract['data']);

        $envelope = $head + [ 'message' => $refusal->message ];
        foreach ($contract as $key => $value) {
            $envelope[ (string) $key ] = $value;
        }
        $envelope['data'] = self::object($refusal->data);

        return $envelope;
    }

    /** The line a model reads. */
    public static function text(Refusal $refusal): string
    {
        $reason = $refusal->unavailable['reason'] ?? null;
        if (! is_string($reason)) {
            return $refusal->message;
        }
        $text = rtrim(sprintf('Unavailable (%s): %s', $reason, $refusal->message));
        $docs = $refusal->unavailable['docs_url'] ?? null;

        return is_string($docs) && '' !== $docs ? $text . ' Docs: ' . $docs : $text;
    }

    /**
     * Data as a JSON object: {} when there is none, a list under value.
     *
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>|\stdClass
     */
    private static function object(array $data): array|\stdClass
    {
        if ([] === $data) {
            return new \stdClass();
        }

        return array_is_list($data) ? [ 'value' => $data ] : $data;
    }
}
