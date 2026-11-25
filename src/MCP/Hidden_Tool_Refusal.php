<?php

namespace WPMCP\MCP;

use WPMCP\Plugin;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A direct tools/call for a tool that is declared but not registered for
 * this connection (issue #876, widened by #628): a pair held back because
 * no plugin it covers is active, a tool governance switched off, or one
 * outside this edition. The adapter answers "Tool not found"; that answer is
 * replaced with the tool's refusal (Registrar::hidden_for_tool), as the tool
 * error every refusal is (Refusal_Result). Http_Tool_Call answers a single
 * 2025-11-25 call itself, so this is left with the calls it does not take,
 * such as a batch. It runs after the request was authenticated and
 * dispatched, only rewrites that one not-found answer, and grants nothing:
 * the tool stays unregistered.
 */
class Hidden_Tool_Refusal
{
    public function register(): void
    {
        add_filter('rest_post_dispatch', [ $this, 'filter_response' ], 20, 3);
    }

    /**
     * The tool error result for a refusal (Refusal_Result::for_error()).
     *
     * @return array<string, mixed>
     */
    public static function result(\WP_Error $error): array
    {
        return Refusal_Result::for_error($error);
    }

    /**
     * @param mixed $response
     * @param mixed $server
     * @param mixed $request
     * @return mixed
     */
    public function filter_response($response, $server, $request)
    {
        if (! $response instanceof \WP_REST_Response || ! $request instanceof \WP_REST_Request) {
            return $response;
        }
        if (! Transport_Guard::is_mcp_route((string) $request->get_route()) || 'POST' !== $request->get_method()) {
            return $response;
        }
        $body = $request->get_json_params();
        $data = $response->get_data();
        if (! is_array($body) || ! is_array($data)) {
            return $response;
        }

        if (array_is_list($body) && array_is_list($data)) {
            // The adapter drops notification answers, so match by JSON-RPC id, not position.
            $by_id = [];
            foreach ($body as $message) {
                if (is_array($message) && isset($message['id']) && (is_int($message['id']) || is_string($message['id']))) {
                    $by_id[ $message['id'] ] = $message;
                }
            }
            foreach ($data as $i => $item) {
                $id = is_array($item) ? ($item['id'] ?? null) : null;
                if ((is_int($id) || is_string($id)) && isset($by_id[ $id ])) {
                    $data[ $i ] = self::rewrite($by_id[ $id ], $item);
                }
            }
        } elseif (! array_is_list($body) && ! array_is_list($data)) {
            $data = self::rewrite($body, $data);
        }
        $response->set_data($data);

        return $response;
    }

    /**
     * @param array<string, mixed> $message  One JSON-RPC request.
     * @param array<string, mixed> $answer   Its JSON-RPC answer.
     * @return array<string, mixed>
     */
    private static function rewrite(array $message, array $answer): array
    {
        $not_in_build = Not_In_Build::for_message($message);
        if (null !== $not_in_build) {
            return [ 'jsonrpc' => '2.0', 'id' => $answer['id'] ?? null, 'result' => self::result($not_in_build) ];
        }
        if ('tools/call' !== ($message['method'] ?? null) || -32003 !== ($answer['error']['code'] ?? null)) {
            return $answer;
        }
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];
        $name   = is_string($params['name'] ?? null) ? trim($params['name']) : '';
        if ('' === $name) {
            return $answer;
        }
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
        $error     = Plugin::instance()->registrar()->hidden_for_tool($name, $arguments);
        if (null === $error) {
            return $answer;
        }

        return [ 'jsonrpc' => '2.0', 'id' => $answer['id'] ?? null, 'result' => self::result($error) ];
    }
}
