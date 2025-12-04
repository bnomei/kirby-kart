<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart;

use Kirby\Cms\Auth\Challenge;
use Kirby\Cms\User;
use Kirby\Toolkit\I18n;
use Kirby\Toolkit\Str;

class MagicLinkChallenge extends Challenge
{
    public static function isAvailable(User $user, string $mode): bool
    {
        return true;
    }

    public static function create(User $user, array $options): ?string
    {
        $code = Str::random(6, 'num');
        if (! $code) {
            return null;
        }

        // insert a space in the middle for easier readability
        $formatted = substr($code, 0, 3).' '.substr($code, 3, 3);

        // use the login templates for 2FA
        $mode = $options['mode'];
        if ($mode === '2fa') {
            $mode = 'login';
        }
        if ($mode === 'login') {
            $mode = 'login-magic';
        }

        $kirby = $user->kirby();
        $link = url(Router::MAGIC_LINK).'?'.implode('&', [
            'email='.urlencode($user->email() ?? ''),
            'code='.$code,
        ]);
        if (isset($options['signup'])) {
            $link .= '&signup='.$options['signup'];
        }
        if (isset($options['success_url'])) {
            $link .= '&redirect='.urlencode($options['success_url']);
        }
        if (isset($options['name'])) {
            $link .= '&name='.urlencode($options['name']);
        }

        if (kart()->option('crypto.signature')) {
            $link .= '&signature='.Kart::signature($link);
        }

        $data = [
            'user' => $user,
            'site' => $kirby->site(),
            'code' => $link,
            'link' => $link,
            'preview' => t('login.code.label.login'),
            'formatted' => $formatted,
            'timeout' => round($options['timeout'] / 60),
        ];
        $from = 'noreply@'.($kirby->url('index', true)->domain() ?? 'localhost');
        $kirby->email([
            'from' => $kirby->option('auth.challenge.email.from', $from),
            'fromName' => $kirby->option('auth.challenge.email.fromName', $kirby->site()->title()->value()), // @phpstan-ignore-line
            'to' => $user,
            'subject' => Str::template(strval($kirby->option(
                'auth.challenge.email.subject', // use this option to set your own
                I18n::translate('login.email.'.$mode.'.subject', null, $user->language()) ?? ''
            )), $data),
            'body' => [
                'html' => snippet('kart/email-'.$mode.'.html', $data, true),
                'text' => snippet('kart/email-'.$mode, $data, true),
            ],
        ]);

        return $code;
    }
}
