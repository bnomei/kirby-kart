<?php

/**
 * Copyright (c) 2025 Bruno Meilick
 * All rights reserved.
 *
 * This file is part of Kirby Kart and is proprietary software.
 * Unauthorized copying, modification, or distribution is prohibited.
 */

namespace Bnomei\Kart;

use Kirby\Auth\Challenge\EmailChallenge;
use Kirby\Auth\Pending;
use Kirby\Cms\User;
use Kirby\Exception\PermissionException;
use Kirby\Toolkit\I18n;
use Kirby\Toolkit\Str;

class MagicLinkChallenge extends EmailChallenge
{
    public const TYPE = 'kart-magic-link';

    private const SESSION = 'kart.magic-link';

    private ?array $linkOptions = null;

    public static function start(User $user, array $linkOptions): void
    {
        if (Router::hasMagicLink() !== null) {
            throw new PermissionException(message: 'Enable kart-magic-link in auth.methods or auth.challenges');
        }

        $kirby = $user->kirby();
        $email = $user->email();
        $limits = $kirby->auth()->limits();
        $limits->ensure($email);
        $limits->track($email, triggerHook: false);

        // A standalone link must not become a pending Panel login or second factor.
        $session = $kirby->session();
        $session->remove(self::SESSION);
        $challenge = new self($user, self::TYPE, 'login', 10 * 60);
        $challenge->linkOptions = $linkOptions;
        $pending = $challenge->create();
        $session->set(self::SESSION, [
            'email' => $email,
            'expires' => time() + $challenge->timeout(),
            'data' => $pending->toArray(),
        ]);
    }

    public static function consume(mixed $email, mixed $code): bool
    {
        $session = kirby()->session();
        $state = $session->get(self::SESSION);
        if (! is_string($email) || ! is_string($code) || ! is_array($state) ||
            ! is_string($state['email'] ?? null) || ! is_int($state['expires'] ?? null) ||
            ! is_array($state['data'] ?? null) || $state['expires'] < time() ||
            strcasecmp($state['email'], $email) !== 0) {
            $session->remove(self::SESSION);

            return false;
        }

        $limits = kirby()->auth()->limits();
        $limits->ensure($email);
        $challenge = kirby()->auth()->challenges()->get(self::TYPE, new User(['email' => $email]), 'login');
        if (! $challenge->verify($code, Pending::from($state['data']))) {
            $limits->track($email);

            return false;
        }

        $session->remove(self::SESSION);

        return true;
    }

    protected function send(string $code): void
    {
        // Kirby's Panel uses the inherited code form, including for 2FA.
        if ($this->linkOptions === null) {
            parent::send($code);

            return;
        }

        $formatted = $code;
        $code = str_replace(' ', '', $code);
        $options = $this->linkOptions;
        $user = $this->user;
        $mode = 'login-magic';
        $kirby = $user->kirby();
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
            'timeout' => round($this->timeout / 60),
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

    }
}
