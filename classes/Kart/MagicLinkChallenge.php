<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart;

use Kirby\Cms\Auth\EmailChallenge;
use Kirby\Cms\User;
use Kirby\Toolkit\I18n;
use Kirby\Toolkit\Str;

class MagicLinkChallenge extends EmailChallenge
{
    private const SESSION = 'kart.magic-link';

    private const TIMEOUT = 600;

    public static function start(User $user, array $options): void
    {
        $kirby = $user->kirby();
        $session = $kirby->session();
        $session->remove(self::SESSION);
        $code = sprintf('%06d', random_int(0, 999999));

        // insert a space in the middle for easier readability
        $formatted = substr($code, 0, 3).' '.substr($code, 3, 3);

        $mode = 'login-magic';
        $link = url(Router::MAGIC_LINK).'?'.implode('&', [
            'email='.urlencode($user->email() ?? ''),
            'code='.$code,
        ]);
        if (isset($options['signup'])) {
            $link .= '&signup='.$options['signup'];
        }
        if (isset($options['success_url'])) {
            $link .= '&success_url='.urlencode($options['success_url']);
        }
        if (isset($options['error_url'])) {
            $link .= '&error_url='.urlencode($options['error_url']);
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
            'timeout' => self::TIMEOUT / 60,
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

        $session->set(self::SESSION, [
            'email' => $user->email(),
            'expires' => time() + self::TIMEOUT,
            'hash' => password_hash($code, PASSWORD_DEFAULT),
        ]);
    }

    public static function consume(mixed $email, mixed $code): bool
    {
        $link = kirby()->session()->pull(self::SESSION);

        return is_array($link) && is_string($email) && is_string($code)
            && is_int($link['expires'] ?? null) && $link['expires'] >= time()
            && is_string($link['email'] ?? null) && strcasecmp($link['email'], $email) === 0
            && is_string($link['hash'] ?? null) && password_verify($code, $link['hash']);
    }
}
