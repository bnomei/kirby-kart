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
use Kirby\Cms\User;
use Kirby\Exception\PermissionException;
use Kirby\Toolkit\I18n;
use Kirby\Toolkit\Str;

class MagicLinkChallenge extends EmailChallenge
{
    public function __construct(User $user, string $mode, int $timeout, private ?array $options = null)
    {
        parent::__construct($user, $mode, $timeout);
    }

    public function type(): string
    {
        return 'kart-magic-link';
    }

    public function start(): void
    {
        if (Router::hasMagicLink() !== null) {
            throw new PermissionException(message: 'Enable kart-magic-link in auth.methods or auth.challenges');
        }

        $this->kirby->auth()->limits()->ensure($this->user->email());
        $this->kirby->auth()->limits()->track($this->user->email(), triggerHook: false);
        $session = $this->kirby->session();
        $this->kirby->auth()->challenges()->clear($session);
        $pending = $this->create();
        $session->set('kirby.challenge.type', $this->type());
        $session->set('kirby.challenge.mode', $this->mode());
        $session->set('kirby.challenge.email', $this->user->email());
        $session->set('kirby.challenge.timeout', time() + $this->timeout());
        $session->set('kirby.challenge.data', $pending->toArray());
    }

    protected function send(string $code): void
    {
        // Kirby's Panel uses the inherited code form, including for 2FA.
        if ($this->options === null) {
            parent::send($code);

            return;
        }

        $formatted = $code;
        $code = str_replace(' ', '', $code);
        $options = $this->options;
        $user = $this->user;

        // use the login templates for 2FA
        $mode = $this->mode;
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
