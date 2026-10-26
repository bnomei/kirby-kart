<?php

use Bnomei\Kart\MagicLinkChallenge;
use Bnomei\Kart\Router;
use Kirby\Cms\User;
use Kirby\Email\Email;
use Kirby\Filesystem\F;
use Kirby\Http\Request;

beforeEach(function (): void {
    kirby()->impersonate(null);
    kirby()->auth()->logout();
    $this->loginLogFile = kirby()->auth()->limits()->file();
    $this->loginLog = is_file($this->loginLogFile) ? file_get_contents($this->loginLogFile) : null;
    F::remove($this->loginLogFile);
    $this->request = kirby()->request();
    $this->emailDebug = Email::$debug;
    $this->emails = Email::$emails;
    Email::$debug = true;
    Email::$emails = [];
    $this->kartOptions = [];
    foreach (['middlewares.enabled' => [], 'captcha.enabled' => false, 'turnstile.enabled' => false, 'router.mode' => 'json'] as $key => $value) {
        $this->kartOptions[$key] = kart()->option($key);
        kart()->setOption($key, $value);
    }
    $this->email = 'magic+'.bin2hex(random_bytes(5)).'@example.test';
    $this->callRoute = function (string $pattern, string $method, array $data) {
        $url = url($pattern).($method === 'GET' ? '?'.http_build_query($data) : '');
        (new ReflectionProperty(kirby(), 'request'))->setValue(kirby(), new Request([
            'url' => $url,
            'method' => $method,
            'query' => $method === 'GET' ? $data : [],
            'body' => $method === 'POST' ? $data : [],
        ]));
        $routes = (require dirname(__DIR__).'/routes.php')(kirby());
        $route = array_values(array_filter($routes, fn ($route) => $route['pattern'] === $pattern && $route['method'] === $method))[0];

        return $route['action']();
    };
});

afterEach(function (): void {
    kirby()->auth()->logout();
    if ($this->loginLog !== null) {
        file_put_contents($this->loginLogFile, $this->loginLog);
    } else {
        F::remove($this->loginLogFile);
    }
    if ($user = kirby()->user($this->email)) {
        kirby()->impersonate('kirby', fn () => $user->delete());
    }
    (new ReflectionProperty(kirby(), 'request'))->setValue(kirby(), $this->request);
    Email::$debug = $this->emailDebug;
    Email::$emails = $this->emails;
    foreach ($this->kartOptions as $key => $value) {
        kart()->setOption($key, $value);
    }
});

it('consumes magic links once for login, signup and the user method', function (string $flow): void {
    if ($flow !== 'signup') {
        $user = kirby()->impersonate('kirby', fn () => kirby()->users()->create([
            'email' => $this->email,
            'role' => 'customer',
        ]));
    }
    if ($flow === 'method') {
        $user->sendMagicLink('/account');
    } else {
        ($this->callRoute)($flow === 'signup' ? Router::SIGNUP_MAGIC : Router::MAGIC_LINK, 'POST', [
            'email' => $this->email,
            'name' => 'Magic Customer',
            'success_url' => '/account',
        ]);
    }

    expect(Email::$emails)->toHaveCount(1);
    preg_match('~https?://[^\s]+/kart/magic-link\?[^\s]+~', Email::$emails[0]->body()->text(), $match);
    parse_str(parse_url($match[0], PHP_URL_QUERY), $query);
    expect($query['email'])->toBe($this->email)
        ->and(kirby()->user())->toBeNull();
    $query['prg'] = '1';

    ($this->callRoute)(Router::MAGIC_LINK, 'GET', $query);
    expect(kirby()->user()?->email())->toBe($this->email)
        ->and(kirby()->session()->get('kirby.challenge.data'))->toBeNull();

    kirby()->auth()->logout();
    ($this->callRoute)(Router::MAGIC_LINK, 'GET', $query);
    expect(kirby()->user())->toBeNull();
})->with(['login', 'signup', 'method']);

it('rejects invalid signup links before creating an account', function (string $failure): void {
    ($this->callRoute)(Router::SIGNUP_MAGIC, 'POST', ['email' => $this->email, 'name' => 'Magic Customer']);
    preg_match('~https?://[^\s]+/kart/magic-link\?[^\s]+~', Email::$emails[0]->body()->text(), $match);
    parse_str(parse_url($match[0], PHP_URL_QUERY), $query);
    $query['prg'] = '1';
    if ($failure === 'expired') {
        kirby()->session()->set('kirby.challenge.timeout', time() - 1);
    } elseif ($failure === 'email') {
        kirby()->session()->set('kirby.challenge.email', 'different@example.test');
    } elseif ($failure === 'code') {
        kirby()->session()->set('kirby.challenge.data', ['secret' => password_hash('not-the-code', PASSWORD_DEFAULT)]);
    } else {
        $query['signup'] = 'tampered';
    }

    ($this->callRoute)(Router::MAGIC_LINK, 'GET', $query);
    expect(kirby()->user())->toBeNull()
        ->and(kirby()->user($this->email))->toBeNull();
})->with(['expired', 'email', 'code', 'signature']);

it('provides a registered Kirby challenge with a Panel code form', function (): void {
    $user = new User(['email' => $this->email]);
    $challenge = kirby()->auth()->challenges()->get('kart-magic-link', $user, 'login');
    $pending = $challenge->create();
    preg_match('/\b\d{3} \d{3}\b/', Email::$emails[0]->body()->text(), $match);

    expect($challenge)->toBeInstanceOf(MagicLinkChallenge::class)
        ->and($challenge->type())->toBe('kart-magic-link')
        ->and($challenge->form($pending)->render()['component'])->toBe('k-login-email-challenge-form')
        ->and($challenge->verify($match[0], $pending))->toBeTrue()
        ->and($challenge->verify('wrong', $pending))->toBeFalse();
});
