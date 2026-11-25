<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

use WPMCP\Admin\Ability_Grid_Page;
use WPMCP\Auth\Client_Access;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The result of "Check connection": the verdict in words, the evidence it
 * rests on, what the check cannot see, and what to do next. Every line
 * names a fact WordPress recorded; nothing here claims more.
 */
final class Check_View
{
    public function __construct(
        private readonly App_Labels $labels,
    ) {
    }

    /**
     * @param list<array{id: string, label: string, ok: bool, detail: string}> $self_test The server self-test checks, when one ran.
     */
    public function render(Check_Result $result, Client_App $app, bool $connector, array $self_test): void
    {
        $name = $this->labels->in_text($app, $connector);

        if (! $result->mcp_on) {
            printf(
                '<div class="notice notice-warning inline"><p>%s</p></div>',
                esc_html__('MCP is off on this site, so all WPMCP tools refuse until you turn it back on on the Settings tab.', 'safe-mcp-for-woocommerce')
            );
        }

        switch ($result->verdict) {
            case Verdict::Connected:
                $this->connected($result);
                return;
            case Verdict::PasswordOnly:
                $this->password_only($result, $name);
                return;
            case Verdict::OauthSignedIn:
                $this->signed_in($result, $name);
                return;
            case Verdict::OauthWaiting:
                $this->box('warn', __('Waiting for approval', 'safe-mcp-for-woocommerce'), sprintf(
                    /* translators: %s: app name */
                    __('%s signed in and waits for an administrator to approve it on the Connections tab.', 'safe-mcp-for-woocommerce'),
                    $name
                ));
                echo '</div>';
                return;
            default:
                $this->not_seen($app, $connector, $name, $self_test);
        }
    }

    /**
     * A password app the MCP route answered: the client as it names itself,
     * on either protocol revision (issue #1258), and when.
     */
    private function connected(Check_Result $result): void
    {
        $activity = $result->activity;
        $client   = null === $activity ? '' : $activity->client->label();
        $this->box('ok', __('Connected', 'safe-mcp-for-woocommerce'), sprintf(
            /* translators: 1: the client's name and version as it reports them, 2: how long ago, such as "2 minutes" */
            __('%1$s signed in %2$s ago.', 'safe-mcp-for-woocommerce'),
            '' === $client ? __('An MCP client', 'safe-mcp-for-woocommerce') : $client,
            null === $activity ? '' : human_time_diff($activity->first_at, time())
        ));
        echo '<ul class="wpmcp-evidence">';
        $this->password_line($result);
        if (null !== $activity) {
            $this->line(true, __('MCP requests answered', 'safe-mcp-for-woocommerce'), $activity->client->is_named()
                ? sprintf(
                    /* translators: 1: the client's name as it reports it, 2: a date and time */
                    __('By %1$s (the name the app reports). Last active %2$s.', 'safe-mcp-for-woocommerce'),
                    $activity->client->name,
                    $this->when($activity->last_at)
                )
                : sprintf(
                    /* translators: %s: a date and time */
                    __('By an MCP client that sent no name. Last active %s.', 'safe-mcp-for-woocommerce'),
                    $this->when($activity->last_at)
                ));
        }
        $this->tool_line($result);
        echo '</ul>';
        printf('<p class="wpmcp-fine">%s</p>', esc_html__('This check reads what WordPress recorded: the password\'s first use, the MCP requests the app made with it and its tool calls. It can\'t see inside your app.', 'safe-mcp-for-woocommerce'));
        $this->next_steps();
        echo '</div>';
    }

    private function password_only(Check_Result $result, string $name): void
    {
        $this->box('warn', __('Password accepted, no MCP request yet', 'safe-mcp-for-woocommerce'), sprintf(
            /* translators: 1: a date and time, 2: app name */
            __('WordPress accepted the password %1$s, but hasn\'t answered an MCP request from %2$s yet.', 'safe-mcp-for-woocommerce'),
            $this->when((int) $result->password_last_used),
            $name
        ));
        printf(
            '<ol class="wpmcp-tips"><li>%s</li><li>%s</li></ol>',
            wp_kses(sprintf(
                /* translators: %s: the endpoint path */
                __('Check that the address in its settings ends in %s.', 'safe-mcp-for-woocommerce'),
                '<code>/wp-json/mcp/wpmcp-server</code>'
            ), [ 'code' => [] ]),
            esc_html__('Some apps connect only when you start a new chat or conversation.', 'safe-mcp-for-woocommerce')
        );
        echo '</div>';
    }

    private function signed_in(Check_Result $result, string $name): void
    {
        $grant = $result->grant;
        if (null === $grant) {
            return;
        }
        $this->box('ok', sprintf(
            /* translators: %s: app name */
            __('%s is signed in', 'safe-mcp-for-woocommerce'),
            $name
        ), sprintf(
            /* translators: 1: user login, 2: the client's registered name, 3: a date and time */
            __('%1$s approved %2$s %3$s.', 'safe-mcp-for-woocommerce'),
            '' === $grant->approved_by ? __('A user', 'safe-mcp-for-woocommerce') : $grant->approved_by,
            '' === $grant->client_name ? $name : $grant->client_name,
            $this->when($grant->approved_at > 0 ? $grant->approved_at : $grant->token_issued_at)
        ));
        echo '<ul class="wpmcp-evidence">';
        if ($grant->registered_at > 0) {
            $this->line(true, __('App registered', 'safe-mcp-for-woocommerce'), sprintf(
                /* translators: 1: the client's registered name, 2: a date and time */
                __('OAuth client "%1$s" registered %2$s.', 'safe-mcp-for-woocommerce'),
                $grant->client_name,
                $this->when($grant->registered_at)
            ));
        }
        $this->line(true, __('Sign-in approved', 'safe-mcp-for-woocommerce'), sprintf(
            /* translators: %s: access level, such as Full access */
            __('Access: %s. Change it on the Connections tab.', 'safe-mcp-for-woocommerce'),
            $this->access_label($grant->access)
        ));
        $this->line(true, __('Token issued', 'safe-mcp-for-woocommerce'), ucfirst($this->when($grant->token_issued_at)) . '.');
        if (null !== $result->session) {
            $this->line(true, __('MCP session started', 'safe-mcp-for-woocommerce'), sprintf(
                /* translators: %s: a date and time */
                __('Last active %s.', 'safe-mcp-for-woocommerce'),
                $this->when($result->session->last_activity)
            ));
        }
        echo '</ul>';
        printf('<p class="wpmcp-fine">%s</p>', esc_html(sprintf(
            /* translators: %s: app name */
            __('This check reads what WordPress recorded when %s registered, signed in and opened a session. It can\'t see inside the app.', 'safe-mcp-for-woocommerce'),
            $name
        )));
        $this->next_steps();
        echo '</div>';
    }

    /** @param list<array{id: string, label: string, ok: bool, detail: string}> $self_test */
    private function not_seen(Client_App $app, bool $connector, string $name, array $self_test): void
    {
        $oauth = Sign_In::Oauth === $app->sign_in($connector);
        $this->box('warn', __('Not seen yet', 'safe-mcp-for-woocommerce'), $oauth
            ? sprintf(
                /* translators: %s: app name */
                __('WordPress hasn\'t seen %s sign in yet.', 'safe-mcp-for-woocommerce'),
                $name
            )
            : __('WordPress hasn\'t seen this password used, and no MCP request has arrived.', 'safe-mcp-for-woocommerce'));
        printf('<p class="wpmcp-subhead">%s</p><ol class="wpmcp-tips">', esc_html__('Try this', 'safe-mcp-for-woocommerce'));
        foreach ($this->tips($app, $connector) as $tip) {
            echo '<li>' . wp_kses($tip, [ 'strong' => [], 'code' => [] ]) . '</li>';
        }
        printf('<li>%s</li></ol>', esc_html(sprintf(
            /* translators: %s: the site's address */
            __('Open %s in a browser on the same computer, to make sure it can reach this site.', 'safe-mcp-for-woocommerce'),
            home_url('/')
        )));
        if ([] !== $self_test) {
            printf('<p class="wpmcp-subhead">%s</p><ul class="wpmcp-evidence">', esc_html__('Server self-test, from this site to itself', 'safe-mcp-for-woocommerce'));
            foreach ($self_test as $check) {
                $this->line($check['ok'], $check['label'], $check['detail']);
            }
            echo '</ul>';
        }
        printf('<p class="wpmcp-fine">%s</p>', esc_html(sprintf(
            /* translators: %s: app name */
            __('What this can\'t test: whether your computer can reach the site, and whether %s loaded its new settings.', 'safe-mcp-for-woocommerce'),
            $name
        )));
        echo '</div>';
    }

    /** @return list<string> Tip text, which may carry strong and code tags. */
    private function tips(Client_App $app, bool $connector): array
    {
        if (Sign_In::Oauth === $app->sign_in($connector)) {
            return [ __('Finish the steps above, then sign in to WordPress and approve the access in the window that opens.', 'safe-mcp-for-woocommerce') ];
        }

        return match ($app) {
            Client_App::ClaudeCode => [
                __('Run the command in the same folder you start Claude Code in.', 'safe-mcp-for-woocommerce'),
                sprintf(
                    /* translators: %s: the /mcp command */
                    __('Quit Claude Code, start it again and type %s.', 'safe-mcp-for-woocommerce'),
                    '<strong>/mcp</strong>'
                ),
            ],
            Client_App::ClaudeDesktop => [
                __('Quit Claude Desktop completely and open it again: it loads extensions when it starts.', 'safe-mcp-for-woocommerce'),
                __('Check that the username and the password are the ones from step 2.', 'safe-mcp-for-woocommerce'),
            ],
            Client_App::Cursor => [
                sprintf(
                    /* translators: %s: the file name .cursor/mcp.json */
                    __('Check that the file is valid JSON and saved as %s.', 'safe-mcp-for-woocommerce'),
                    '<code>.cursor/mcp.json</code>'
                ),
                __('Make sure wpmcp is turned on in Cursor\'s MCP settings.', 'safe-mcp-for-woocommerce'),
            ],
            Client_App::Antigravity => [
                __('Quit and reopen Antigravity: it keeps its old settings until it restarts.', 'safe-mcp-for-woocommerce'),
                __('Start a new conversation so the tools load.', 'safe-mcp-for-woocommerce'),
            ],
            default => [
                __('Restart the app after you change its settings.', 'safe-mcp-for-woocommerce'),
                sprintf(
                    /* translators: %s: the endpoint path */
                    __('Check that the address ends in %s and that the app sends the Authorization header.', 'safe-mcp-for-woocommerce'),
                    '<code>/wp-json/mcp/wpmcp-server</code>'
                ),
            ],
        };
    }

    private function password_line(Check_Result $result): void
    {
        if (null === $result->password_last_used) {
            return;
        }
        $this->line(true, __('WordPress accepted the password', 'safe-mcp-for-woocommerce'), null === $result->password_last_ip
            ? sprintf(
                /* translators: %s: a date and time */
                __('First used %s.', 'safe-mcp-for-woocommerce'),
                $this->when($result->password_last_used)
            )
            : sprintf(
                /* translators: 1: a date and time, 2: an IP address */
                __('First used %1$s, from %2$s.', 'safe-mcp-for-woocommerce'),
                $this->when($result->password_last_used),
                $result->password_last_ip
            ));
    }

    private function tool_line(Check_Result $result): void
    {
        $call = $result->tool_call;
        if (null === $call) {
            return;
        }
        $this->line($call->ok, __('First tool call', 'safe-mcp-for-woocommerce'), sprintf(
            $call->ok
                /* translators: 1: tool name, 2: a date and time */
                ? __('%1$s %2$s, finished without an error.', 'safe-mcp-for-woocommerce')
                /* translators: 1: tool name, 2: a date and time */
                : __('%1$s %2$s, ended with an error.', 'safe-mcp-for-woocommerce'),
            $call->tool,
            $this->when($call->at)
        ));
    }

    private function next_steps(): void
    {
        ?>
        <ul class="wpmcp-next" aria-label="<?php echo esc_attr__('What to try next', 'safe-mcp-for-woocommerce'); ?>">
            <li><span class="dashicons dashicons-format-chat" aria-hidden="true"></span><span><?php echo esc_html__('Try asking: "Show me my last five posts."', 'safe-mcp-for-woocommerce'); ?></span></li>
            <li><span class="dashicons dashicons-backup" aria-hidden="true"></span><span>
                <?php
                echo wp_kses(sprintf(
                    /* translators: %s: link to the History screen */
                    __('Before each supported write, WPMCP saves a snapshot, and undo is free. Your changes are on the %s screen.', 'safe-mcp-for-woocommerce'),
                    '<a href="' . esc_url(admin_url('admin.php?page=wpmcp')) . '">' . esc_html__('History', 'safe-mcp-for-woocommerce') . '</a>'
                ), [ 'a' => [ 'href' => true ] ]);
                ?>
            </span></li>
            <li><span class="dashicons dashicons-shield" aria-hidden="true"></span><span>
                <?php
                echo wp_kses(sprintf(
                    /* translators: %s: link to the Abilities screen */
                    __('Choose what agents may do on the %s screen.', 'safe-mcp-for-woocommerce'),
                    '<a href="' . esc_url(admin_url('admin.php?page=' . Ability_Grid_Page::SLUG)) . '">' . esc_html__('Abilities', 'safe-mcp-for-woocommerce') . '</a>'
                ), [ 'a' => [ 'href' => true ] ]);
                ?>
            </span></li>
        </ul>
        <?php
    }

    /** Opens the result box with its title and first line; the caller closes it. */
    private function box(string $kind, string $title, string $body): void
    {
        printf(
            '<div class="wpmcp-result is-%1$s"><h3 class="wpmcp-result__title"><span class="dashicons dashicons-%2$s" aria-hidden="true"></span>%3$s</h3><p class="wpmcp-result__body">%4$s</p>',
            esc_attr($kind),
            esc_attr('ok' === $kind ? 'yes-alt' : 'warning'),
            esc_html($title),
            esc_html($body)
        );
    }

    private function line(bool $ok, string $label, string $detail): void
    {
        printf(
            '<li><span class="dashicons dashicons-%1$s %2$s" aria-hidden="true"></span><div><b>%3$s</b><span>%4$s</span></div></li>',
            esc_attr($ok ? 'yes' : 'no-alt'),
            esc_attr($ok ? 'is-ok' : 'is-bad'),
            esc_html(($ok ? '' : __('Problem:', 'safe-mcp-for-woocommerce') . ' ') . $label),
            esc_html($detail)
        );
    }

    /** The access level a user chose for an OAuth app, in words. */
    private function access_label(string $access): string
    {
        return match ($access) {
            Client_Access::READ => __('Read only', 'safe-mcp-for-woocommerce'),
            Client_Access::IDENTITY => __('As a scoped identity', 'safe-mcp-for-woocommerce'),
            default => __('Full access', 'safe-mcp-for-woocommerce'),
        };
    }

    /** A time in the site's time zone: "today at 5:17 pm", or the date and time. */
    private function when(int $timestamp): string
    {
        $time_format = get_option('time_format');
        $date_format = get_option('date_format');
        $time        = (string) wp_date(is_string($time_format) ? $time_format : 'g:i a', $timestamp);
        if (wp_date('Y-m-d', $timestamp) === wp_date('Y-m-d')) {
            return sprintf(
                /* translators: %s: a time of day */
                __('today at %s', 'safe-mcp-for-woocommerce'),
                $time
            );
        }

        return sprintf(
            /* translators: 1: a date, 2: a time of day */
            __('on %1$s at %2$s', 'safe-mcp-for-woocommerce'),
            (string) wp_date(is_string($date_format) ? $date_format : 'F j, Y', $timestamp),
            $time
        );
    }
}
