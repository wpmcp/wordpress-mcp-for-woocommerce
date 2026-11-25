<?php

declare(strict_types=1);

namespace WPMCP\Safety;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The session a write records its undo point under (issue #1255).
 *
 * rollback-session undoes every undo point recorded under one session id, so
 * a write takes the caller's session_id from its input: one chat's writes then
 * roll back together and leave another chat's alone. A write given none
 * records under the shared 'default' session, as before.
 *
 * Pure on purpose: it reads only the input it is given.
 */
final class Session_Id
{
    /** The catch-all session of writes that name none. */
    public const SHARED = 'default';

    /**
     * The caller's session_id, or null when none is given.
     *
     * @param array<mixed> $args The ability input.
     */
    public static function given(array $args): ?string
    {
        $session = $args['session_id'] ?? null;
        return is_string($session) && '' !== $session ? $session : null;
    }

    /**
     * The session to record under: the caller's, else the shared one.
     *
     * @param array<mixed> $args The ability input.
     */
    public static function of(array $args): string
    {
        return self::given($args) ?? self::SHARED;
    }
}
