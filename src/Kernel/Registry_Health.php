<?php

declare(strict_types=1);

namespace WPMCP\Kernel;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Says so when a development checkout boots without build/registry: the
 * kernel then loads no module, so their integrations and opt-in switches are
 * gone without an error. One error_log line per request and an admin notice
 * for administrators. A release copy has no bin/build-registry.php and ships
 * its registry (the release builds assert it), so it is never reported.
 */
final class Registry_Health
{
    /** @var \Closure(string): void */
    private \Closure $log;

    /** @var \Closure(string, callable): void */
    private \Closure $add_action;

    private bool $reported = false;

    /**
     * @param (callable(string): void)|null           $log        defaults to error_log()
     * @param (callable(string, callable): void)|null $add_action defaults to add_action()
     */
    public function __construct(private readonly string $root, ?callable $log = null, ?callable $add_action = null)
    {
        $this->log = null === $log
            ? static function (string $line): void {
                error_log($line); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- a development checkout without its module registry must be visible in the log.
            }
            : \Closure::fromCallable($log);
        $this->add_action = null === $add_action
            ? static function (string $hook, callable $callback): void {
                add_action($hook, $callback);
            }
            : \Closure::fromCallable($add_action);
    }

    /** Why this checkout runs without its module registry, or null when it does not. */
    public function problem(): ?string
    {
        $root = rtrim($this->root, '/');
        if (! is_file($root . '/bin/build-registry.php') || is_file($root . '/build/registry/boot.php')) {
            return null;
        }
        return 'build/registry is missing in this development checkout, so no module loads (their integrations and opt-in switches are gone). Run php bin/build-registry.php (composer install runs it).';
    }

    /** Logs the problem and adds the admin notice, once. True when there is a problem. */
    public function report(): bool
    {
        $problem = $this->problem();
        if (null === $problem) {
            return false;
        }
        if (! $this->reported) {
            $this->reported = true;
            ($this->log)('WPMCP: ' . $problem);
            ($this->add_action)('admin_notices', static function () use ($problem): void {
                if (! current_user_can('manage_options')) {
                    return;
                }
                printf('<div class="notice notice-error"><p>%s</p></div>', esc_html('WPMCP: ' . $problem));
            });
        }
        return true;
    }
}
