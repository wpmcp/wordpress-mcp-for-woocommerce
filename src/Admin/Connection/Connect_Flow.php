<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The state of the Connection screen's four steps: pick the app, create the
 * connection (an application password, or OAuth sign-in for an OAuth app),
 * add it to the app, and check it. Only one step is ever Current; a step the
 * reader can also use now is Open.
 */
final class Connect_Flow
{
    public function model(Flow_Input $in): Flow_Model
    {
        if (null === $in->app) {
            return new Flow_Model(Step_State::Current, Step_State::Locked, Step_State::Locked, Step_State::Locked, null, null);
        }

        $sign_in = $in->app->sign_in($in->connector);
        $blocker = $this->blocker($in, $sign_in);
        if (null !== $blocker) {
            return new Flow_Model(Step_State::Done, Step_State::Blocked, Step_State::Locked, Step_State::Locked, $blocker, $sign_in);
        }

        $ready = Sign_In::Oauth === $sign_in ? $in->oauth_on : $in->created;
        if (! $ready) {
            return new Flow_Model(Step_State::Done, Step_State::Current, Step_State::Locked, Step_State::Locked, null, $sign_in);
        }

        if ($in->verified) {
            return new Flow_Model(Step_State::Done, Step_State::Done, Step_State::Done, Step_State::Done, null, $sign_in);
        }

        return new Flow_Model(Step_State::Done, Step_State::Done, Step_State::Current, Step_State::Open, null, $sign_in);
    }

    private function blocker(Flow_Input $in, Sign_In $sign_in): ?Blocker
    {
        if (Sign_In::Password === $sign_in) {
            if (null === $in->passwords_unavailable) {
                return null;
            }

            return 'https_required' === $in->passwords_unavailable ? Blocker::HttpsRequired : Blocker::PasswordsDisabled;
        }

        if (! $in->public_address) {
            return Blocker::NotPublic;
        }

        return ! $in->oauth_on && $in->oauth_locked ? Blocker::OauthLocked : null;
    }
}
