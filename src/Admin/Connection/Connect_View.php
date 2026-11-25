<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

use WPMCP\Admin\Connection_Page;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The "Connect an app" tab: four steps on one rail (pick the app, create the
 * connection, add it to the app, check it). Each step is a list item with
 * data-step and data-state, a numbered marker and a section labelled by its
 * heading. Picking an app is a link, creating is a form post and checking is
 * a form post, so the flow works without JavaScript; the script upgrades
 * create and check to AJAX and swaps in the HTML this class draws.
 */
final class Connect_View
{
    /** What wp_kses() keeps of a button link. */
    private const BUTTON_TAGS = [
        'a'    => [ 'class' => true, 'href' => true ],
        'span' => [ 'class' => true, 'aria-hidden' => true ],
    ];

    public function __construct(
        private readonly App_Labels $labels,
        private readonly App_Icons $icons,
        private readonly Instructions_View $instructions,
        private readonly Check_View $checks,
    ) {
    }

    public function render(Connect_Screen $screen): void
    {
        echo '<ol class="wpmcp-steps" id="wpmcp-steps">';
        $this->pick($screen);
        $this->create($screen);
        $this->add($screen);
        $this->check($screen);
        echo '</ol>';
    }

    private function pick(Connect_Screen $screen): void
    {
        $app = $screen->app;
        if (null !== $app) {
            $change = sprintf(
                '<a class="wpmcp-change" href="%1$s">%2$s<span class="screen-reader-text">%3$s</span></a>',
                esc_url(admin_url('admin.php?page=' . Connection_Page::SLUG) . '#wpmcp-step-1'),
                esc_html__('Change app', 'safe-mcp-for-woocommerce'),
                esc_html(sprintf(
                    /* translators: %s: app name */
                    __(', now %s', 'safe-mcp-for-woocommerce'),
                    $this->labels->label($app)
                ))
            );
            $this->open(1, 'pick', $screen->model->pick, __('Pick your AI app', 'safe-mcp-for-woocommerce'), $change);
            echo '<div class="wpmcp-summary">' . wp_kses($this->icons->tile($app), $this->icons->allowed_html()) . '<span>' . esc_html($this->labels->label($app)) . '</span></div>';
            $this->close('pick');
            return;
        }

        $this->open(1, 'pick', $screen->model->pick, __('Pick your AI app', 'safe-mcp-for-woocommerce'));
        printf('<p class="wpmcp-lead">%s</p><ul class="wpmcp-apps">', esc_html__('Which app should work on this site? You can connect more apps later.', 'safe-mcp-for-woocommerce'));
        foreach (Client_App::cases() as $choice) {
            $url = add_query_arg([ 'page' => Connection_Page::SLUG, 'client' => $choice->value ], admin_url('admin.php')) . '#wpmcp-step-2';
            printf(
                '<li><a class="wpmcp-app" href="%1$s" data-client="%2$s" aria-describedby="wpmcp-hint-%2$s">%3$s<span class="wpmcp-app__text"><span class="wpmcp-app__name">%4$s</span><span class="wpmcp-app__hint" id="wpmcp-hint-%2$s">%5$s</span>',
                esc_url($url),
                esc_attr($choice->value),
                wp_kses($this->icons->tile($choice), $this->icons->allowed_html()),
                esc_html($this->labels->label($choice)),
                esc_html($this->labels->hint($choice))
            );
            if (Sign_In::Oauth === $choice->sign_in(false) && ! $screen->public_address) {
                printf('<span class="wpmcp-app__flag wpmcp-public-address-flag"><span class="dashicons dashicons-warning" aria-hidden="true"></span>%s</span>', esc_html__('Needs a public HTTPS site', 'safe-mcp-for-woocommerce'));
            }
            echo '</span></a></li>';
        }
        echo '</ul>';
        $this->close('pick');
    }

    private function create(Connect_Screen $screen): void
    {
        $app   = $screen->app;
        $state = $screen->model->create;
        $oauth = Sign_In::Oauth === $screen->model->sign_in;
        $title = null !== $app && $oauth
            ? sprintf(
                /* translators: %s: app name */
                __('Allow %s to sign in', 'safe-mcp-for-woocommerce'),
                $this->labels->in_text($app, $screen->connector)
            )
            : __('Create a connection', 'safe-mcp-for-woocommerce');

        $this->open(2, 'create', $state, $title);
        if (null === $app || Step_State::Locked === $state) {
            $this->sub(__('Available after you pick an app.', 'safe-mcp-for-woocommerce'));
        } elseif (Step_State::Blocked === $state) {
            $this->blocked($screen, $app);
        } elseif ($oauth) {
            $this->oauth_step($screen, $app, $state);
        } elseif (null !== $screen->provisioned) {
            $this->key($screen->provisioned, $app);
        } elseif ('' !== $screen->created_name) {
            printf(
                '<div class="wpmcp-summary"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><span>%s</span></div>',
                esc_html(sprintf(
                    /* translators: %s: connection name */
                    __('Connection created: %s. Its password was shown once, when it was made.', 'safe-mcp-for-woocommerce'),
                    $screen->created_name
                ))
            );
        } else {
            $this->create_form($screen, $app);
        }
        $this->close('create');
    }

    private function create_form(Connect_Screen $screen, Client_App $app): void
    {
        printf('<p class="wpmcp-lead">%s</p>', wp_kses(sprintf(
            /* translators: 1: app name, 2: the current user's login */
            __('WPMCP creates a WordPress application password for %1$s. The app signs in with it as %2$s. You can revoke it any time on the Connections tab.', 'safe-mcp-for-woocommerce'),
            '<strong>' . esc_html($this->labels->label($app)) . '</strong>',
            '<strong>' . esc_html(wp_get_current_user()->user_login) . '</strong>'
        ), [ 'strong' => [] ]));
        ?>
        <form method="post" action="#wpmcp-step-2" class="wpmcp-create" data-wpmcp-create>
            <input type="hidden" name="wpmcp_connection_action" value="provision">
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($screen->nonce); ?>">
            <input type="hidden" name="client" value="<?php echo esc_attr($app->value); ?>">
            <input type="hidden" name="request_id" value="<?php echo esc_attr($screen->request_id); ?>">
            <div class="wpmcp-actions">
                <button type="submit" class="button button-primary" data-wpmcp-create-button><?php echo esc_html__('Create connection', 'safe-mcp-for-woocommerce'); ?></button>
                <span class="wpmcp-note">
                    <?php
                    echo esc_html(sprintf(
                        /* translators: %s: the connection's default name */
                        __('Named "%s"', 'safe-mcp-for-woocommerce'),
                        $screen->default_name
                    ));
                    ?>
                </span>
            </div>
            <details class="wpmcp-details">
                <summary><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span><?php echo esc_html__('Change the user or name', 'safe-mcp-for-woocommerce'); ?></summary>
                <div class="wpmcp-fields">
                    <div class="wpmcp-field">
                        <label for="wpmcp-connection-user"><?php echo esc_html__('Connect as', 'safe-mcp-for-woocommerce'); ?></label>
                        <?php
                        wp_dropdown_users([
                            'name'     => 'user_id',
                            'id'       => 'wpmcp-connection-user',
                            'selected' => get_current_user_id(),
                        ]);
                        ?>
                        <p class="wpmcp-help"><?php echo esc_html__('Least privilege: pick a user with only the role this app needs. The app can never do more than that user can, and the Abilities screen narrows it further.', 'safe-mcp-for-woocommerce'); ?></p>
                    </div>
                    <div class="wpmcp-field">
                        <label for="wpmcp-connection-name"><?php echo esc_html__('Name', 'safe-mcp-for-woocommerce'); ?></label>
                        <input type="text" class="regular-text" id="wpmcp-connection-name" name="name" value="<?php echo esc_attr($screen->default_name); ?>">
                        <p class="wpmcp-help"><?php echo esc_html__('Shown on the Connections tab and on the user\'s profile.', 'safe-mcp-for-woocommerce'); ?></p>
                    </div>
                </div>
            </details>
        </form>
        <?php
    }

    /** The one-time reveal: the password, Copy, and what happens if it is lost. */
    private function key(Provisioned $made, Client_App $app): void
    {
        $where = match ($app) {
            Client_App::ClaudeDesktop => __('You will type it into Claude Desktop in step 3.', 'safe-mcp-for-woocommerce'),
            Client_App::ClaudeCode => __('It is already inside the command in step 3.', 'safe-mcp-for-woocommerce'),
            Client_App::Other => __('It is already inside the header and the JSON in step 3.', 'safe-mcp-for-woocommerce'),
            default => __('It is already inside the JSON in step 3.', 'safe-mcp-for-woocommerce'),
        };
        $shown = trim(chunk_split($made->password, 4, ' '));
        ?>
        <div class="wpmcp-key" id="wpmcp-key">
            <div class="wpmcp-key__top">
                <h3 class="wpmcp-key__title" id="wpmcp-key-title" tabindex="-1"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php echo esc_html__('Connection created', 'safe-mcp-for-woocommerce'); ?></h3>
                <span class="wpmcp-key__meta">
                    <?php
                    echo wp_kses(sprintf(
                        /* translators: 1: connection name, 2: user login */
                        __('%1$s signs in as %2$s', 'safe-mcp-for-woocommerce'),
                        esc_html($made->name),
                        '<strong>' . esc_html($made->user_login) . '</strong>'
                    ), [ 'strong' => [] ]);
                    ?>
                </span>
            </div>
            <div class="wpmcp-key__label" id="wpmcp-password-label"><?php echo esc_html__('Application password', 'safe-mcp-for-woocommerce'); ?> <span class="wpmcp-once"><span class="dashicons dashicons-lock" aria-hidden="true"></span><?php echo esc_html__('Shown once', 'safe-mcp-for-woocommerce'); ?></span></div>
            <div class="wpmcp-key__row">
                <output class="wpmcp-key__value" id="wpmcp-password" aria-labelledby="wpmcp-password-label"><?php echo esc_html($shown); ?></output>
                <button type="button" class="button wpmcp-copy" data-copy-target="wpmcp-password" data-wpmcp-copy-text="<?php echo esc_attr($shown); ?>" data-wpmcp-copied="<?php echo esc_attr__('Copied the application password.', 'safe-mcp-for-woocommerce'); ?>" hidden><span class="dashicons dashicons-clipboard" aria-hidden="true"></span><span class="wpmcp-copy__label"><?php echo esc_html__('Copy password', 'safe-mcp-for-woocommerce'); ?></span></button>
            </div>
            <p class="wpmcp-key__note">
                <?php
                echo esc_html(__('WPMCP doesn\'t store this password and can\'t show it again.', 'safe-mcp-for-woocommerce') . ' ' . $where . ' ' . __('Lost it? Revoke this connection on the Connections tab and create a new one.', 'safe-mcp-for-woocommerce'));
                ?>
            </p>
        </div>
        <?php
    }

    private function oauth_step(Connect_Screen $screen, Client_App $app, Step_State $state): void
    {
        $name = $this->labels->in_text($app, $screen->connector);
        if (Step_State::Done === $state) {
            printf(
                '<div class="wpmcp-summary"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><span>%s</span></div>',
                esc_html(sprintf(
                    /* translators: %s: app name */
                    __('OAuth sign-in is on. %s signs in through your browser, no password needed.', 'safe-mcp-for-woocommerce'),
                    $name
                ))
            );
            return;
        }

        printf('<p class="wpmcp-lead">%s</p>', esc_html(sprintf(
            /* translators: %s: app name */
            __('%s doesn\'t use a password. It signs in through your browser: you log in to WordPress and approve it. That needs OAuth sign-in, which is off on this site.', 'safe-mcp-for-woocommerce'),
            $name
        )));
        ?>
        <form method="post" action="<?php echo esc_url(add_query_arg([ 'page' => Connection_Page::SLUG, 'client' => $app->value, 'route' => $screen->connector ? 'connector' : false ], admin_url('admin.php')) . '#wpmcp-step-3'); ?>">
            <input type="hidden" name="wpmcp_connection_action" value="oauth_enabled">
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($screen->nonce); ?>">
            <input type="hidden" name="enabled" value="1">
            <div class="wpmcp-actions"><button type="submit" class="button button-primary"><?php echo esc_html__('Turn on OAuth sign-in', 'safe-mcp-for-woocommerce'); ?></button></div>
        </form>
        <p class="wpmcp-fine"><?php echo esc_html__('You can turn it off on the Settings tab. Turning it off stops new sign-ins and refuses connections made this way on their next request.', 'safe-mcp-for-woocommerce'); ?></p>
        <?php
    }

    private function blocked(Connect_Screen $screen, Client_App $app): void
    {
        switch ($screen->model->blocker) {
            case Blocker::HttpsRequired:
                $this->alert('error', __('Application passwords are off because this site doesn\'t use HTTPS', 'safe-mcp-for-woocommerce'), [
                    sprintf(
                        /* translators: %s: app name */
                        __('%s signs in with a WordPress application password, and WordPress only allows those on HTTPS sites (or on local development sites).', 'safe-mcp-for-woocommerce'),
                        $this->labels->label($app)
                    ),
                    $screen->passwords_message,
                ]);
                $this->again($app);
                echo '</div></div>';
                return;
            case Blocker::PasswordsDisabled:
                $this->alert('error', __('Application passwords are turned off on this site', 'safe-mcp-for-woocommerce'), [ $screen->passwords_message ]);
                $base = admin_url('admin.php?page=' . Connection_Page::SLUG);
                echo '<div class="wpmcp-actions">' . wp_kses($this->again_button($app), self::BUTTON_TAGS);
                printf(
                    '<a class="button" href="%1$s">%2$s</a><a class="button" href="%3$s">%4$s</a></div>',
                    esc_url($base . '&client=claude-desktop&route=connector#wpmcp-step-2'),
                    esc_html__('Use the Claude connector', 'safe-mcp-for-woocommerce'),
                    esc_url($base . '&client=chatgpt#wpmcp-step-2'),
                    esc_html__('Use ChatGPT', 'safe-mcp-for-woocommerce')
                );
                echo '</div></div>';
                return;
            case Blocker::NotPublic:
                $name = $this->labels->in_text($app, $screen->connector);
                $this->alert('warn', sprintf(
                    /* translators: %s: app name */
                    __('%s can\'t reach this site', 'safe-mcp-for-woocommerce'),
                    $name
                ), []);
                printf('<p class="wpmcp-public-address-warning">%s</p>', esc_html($screen->public_warning));
                printf(
                    '<ul><li>%1$s</li><li>%2$s</li></ul>',
                    esc_html__('Connect from your live site instead: open WPMCP, Connection there.', 'safe-mcp-for-woocommerce'),
                    esc_html__('Or pick an app that runs on your computer: it can reach this site as it is.', 'safe-mcp-for-woocommerce')
                );
                $base = admin_url('admin.php?page=' . Connection_Page::SLUG);
                printf(
                    '<div class="wpmcp-actions"><a class="button" href="%1$s">%2$s</a><a class="button" href="%3$s">%4$s</a></div>',
                    esc_url($base . '&client=claude-code#wpmcp-step-2'),
                    esc_html__('Use Claude Code', 'safe-mcp-for-woocommerce'),
                    esc_url($base . '&client=claude-desktop#wpmcp-step-2'),
                    esc_html__('Use Claude Desktop', 'safe-mcp-for-woocommerce')
                );
                echo '</div></div>';
                return;
            default:
                $this->alert('warn', __('OAuth sign-in is fixed in code', 'safe-mcp-for-woocommerce'), [
                    $screen->oauth_locked_message,
                    __('Or use an app that signs in with an application password, such as Claude Desktop with the bundle.', 'safe-mcp-for-woocommerce'),
                ]);
                echo '</div></div>';
        }
    }

    private function add(Connect_Screen $screen): void
    {
        $app   = $screen->app;
        $title = null === $app
            ? __('Add it to your app', 'safe-mcp-for-woocommerce')
            : sprintf(
                /* translators: %s: app name, or "your app" */
                __('Add it to %s', 'safe-mcp-for-woocommerce'),
                $this->labels->in_text($app, $screen->connector)
            );
        $this->open(3, 'add', $screen->model->add, $title);
        if (Step_State::Locked === $screen->model->add) {
            $this->sub(__('Available after step 2.', 'safe-mcp-for-woocommerce'));
        } else {
            $this->instructions->render($screen);
        }
        $this->close('add');
    }

    private function check(Connect_Screen $screen): void
    {
        $app   = $screen->app;
        $state = $screen->model->check;
        $this->open(4, 'check', $state, __('Check the connection', 'safe-mcp-for-woocommerce'));
        if (null === $app || Step_State::Locked === $state) {
            $this->sub(__('Available after step 3.', 'safe-mcp-for-woocommerce'));
            $this->close('check');
            return;
        }

        $name = $this->labels->in_text($app, $screen->connector);
        $ask  = Client_App::Chatgpt === $app
            ? __('In a ChatGPT chat with WPMCP turned on, ask: "List the WPMCP tools."', 'safe-mcp-for-woocommerce')
            : sprintf(
                /* translators: %s: app name, or "your app" */
                __('Ask %s something simple, like "List the WPMCP tools."', 'safe-mcp-for-woocommerce'),
                $name
            );
        printf('<p class="wpmcp-lead">%s %s</p>', esc_html($ask), esc_html__('This page then looks for the sign-in on your site.', 'safe-mcp-for-woocommerce'));
        $done = null !== $screen->check && $screen->check->is_done();
        ?>
        <form method="post" action="#wpmcp-step-4" class="wpmcp-check" data-wpmcp-check
            data-client="<?php echo esc_attr($app->value); ?>"
            data-uuid="<?php echo esc_attr($screen->check_uuid); ?>"
            data-connector="<?php echo esc_attr($screen->connector ? '1' : '0'); ?>"
            data-name="<?php echo esc_attr($name); ?>"
            data-watch="<?php echo esc_attr($done ? '0' : '1'); ?>">
            <input type="hidden" name="wpmcp_connection_action" value="check">
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($screen->nonce); ?>">
            <input type="hidden" name="client" value="<?php echo esc_attr($app->value); ?>">
            <input type="hidden" name="uuid" value="<?php echo esc_attr($screen->check_uuid); ?>">
            <input type="hidden" name="route" value="<?php echo esc_attr($screen->connector ? 'connector' : ''); ?>">
            <input type="hidden" name="selftest" value="1">
            <div class="wpmcp-actions">
                <button type="submit" class="button<?php echo esc_attr($done ? '' : ' button-primary'); ?>" data-wpmcp-check-button><?php echo esc_html(null === $screen->check ? __('Check connection', 'safe-mcp-for-woocommerce') : __('Check again', 'safe-mcp-for-woocommerce')); ?></button>
                <span class="wpmcp-watch" data-wpmcp-watch hidden></span>
            </div>
            <div class="wpmcp-check__result" data-wpmcp-result role="status" aria-live="polite">
                <?php
                if (null !== $screen->check) {
                    $this->checks->render($screen->check, $app, $screen->connector, $screen->self_test);
                }
                ?>
            </div>
        </form>
        <?php
        $this->close('check');
    }

    private function open(int $number, string $step, Step_State $state, string $title, string $extra = ''): void
    {
        $words = match ($state) {
            Step_State::Locked => __('not available yet', 'safe-mcp-for-woocommerce'),
            Step_State::Current => __('current step', 'safe-mcp-for-woocommerce'),
            Step_State::Open => __('ready', 'safe-mcp-for-woocommerce'),
            Step_State::Done => __('done', 'safe-mcp-for-woocommerce'),
            Step_State::Blocked => __('needs attention', 'safe-mcp-for-woocommerce'),
        };
        printf('<li class="wpmcp-step is-%1$s" id="wpmcp-step-%2$d" data-step="%3$s" data-state="%1$s"', esc_attr($state->value), absint($number), esc_attr($step));
        if (Step_State::Current === $state || Step_State::Blocked === $state) {
            echo ' aria-current="step"';
        }
        echo '><div class="wpmcp-marker" aria-hidden="true">';
        if (Step_State::Done === $state) {
            echo '<span class="dashicons dashicons-yes"></span>';
        } elseif (Step_State::Blocked === $state) {
            echo '<span class="dashicons dashicons-no-alt"></span>';
        } else {
            echo absint($number);
        }
        echo '</div>';
        printf(
            '<section class="wpmcp-panel" aria-labelledby="wpmcp-step-%1$d-title"><div class="wpmcp-step__head"><h2 id="wpmcp-step-%1$d-title" tabindex="-1">%2$s<span class="screen-reader-text">%3$s</span></h2>%4$s</div>',
            absint($number),
            esc_html($title),
            esc_html(sprintf(
                /* translators: 1: step number, 2: the step's state, such as "current step" */
                __(', step %1$d of 4, %2$s', 'safe-mcp-for-woocommerce'),
                $number,
                $words
            )),
            wp_kses($extra, [ 'a' => [ 'class' => true, 'href' => true ], 'span' => [ 'class' => true ] ])
        );
    }

    private function close(string $step): void
    {
        printf('</section><!-- /wpmcp-step-%s --></li>', esc_html($step));
    }

    private function sub(string $text): void
    {
        printf('<p class="wpmcp-sub-note">%s</p>', esc_html($text));
    }

    /**
     * Opens an alert box with its title and paragraphs; the caller adds more
     * and closes both divs.
     *
     * @param string[] $paragraphs
     */
    private function alert(string $kind, string $title, array $paragraphs): void
    {
        printf(
            '<div class="wpmcp-alert is-%1$s"><span class="dashicons dashicons-%2$s" aria-hidden="true"></span><div><h3>%3$s</h3>',
            esc_attr($kind),
            esc_attr('error' === $kind ? 'dismiss' : 'warning'),
            esc_html($title)
        );
        foreach ($paragraphs as $paragraph) {
            if ('' !== $paragraph) {
                printf('<p>%s</p>', esc_html($paragraph));
            }
        }
    }

    private function again(Client_App $app): void
    {
        echo '<div class="wpmcp-actions">' . wp_kses($this->again_button($app), self::BUTTON_TAGS) . '</div>';
    }

    /** Reloads step 2 for the same app, after the reader fixed the cause. */
    private function again_button(Client_App $app): string
    {
        return sprintf(
            '<a class="button" href="%1$s"><span class="dashicons dashicons-update" aria-hidden="true"></span>%2$s</a>',
            esc_url(add_query_arg([ 'page' => Connection_Page::SLUG, 'client' => $app->value ], admin_url('admin.php')) . '#wpmcp-step-2'),
            esc_html__('Check again', 'safe-mcp-for-woocommerce')
        );
    }
}
