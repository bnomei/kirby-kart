<?php

use Bnomei\Kart\MagicLinkChallenge;
use Bnomei\Kart\Router;
use Kirby\Auth\State;
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
    $this->kirbyOptions = (new ReflectionProperty(kirby(), 'options'))->getValue(kirby());
    $this->configureAuth = function (array $methods, array $challenges): void {
        $options = $this->kirbyOptions;
        $options['auth']['methods'] = $methods;
        $options['auth']['challenges'] = $challenges;
        (new ReflectionProperty(kirby(), 'options'))->setValue(kirby(), $options);
        (new ReflectionProperty(kirby(), 'auth'))->setValue(kirby(), null);
    };
    $this->emailDebug = Email::$debug;
    $this->emails = Email::$emails;
    Email::$debug = true;
    Email::$emails = [];
    $this->kartOptions = [];
    foreach ([
        'middlewares.enabled' => [],
        'middlewares.ratelimit.enabled' => false,
        'captcha.enabled' => false,
        'turnstile.enabled' => false,
        'router.mode' => 'json',
    ] as $key => $value) {
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
    kirby()->session()->remove('kart.magic-link');
    if ($this->loginLog !== null) {
        file_put_contents($this->loginLogFile, $this->loginLog);
    } else {
        F::remove($this->loginLogFile);
    }
    if ($user = kirby()->user($this->email)) {
        kirby()->impersonate('kirby', fn () => $user->delete());
    }
    (new ReflectionProperty(kirby(), 'request'))->setValue(kirby(), $this->request);
    (new ReflectionProperty(kirby(), 'options'))->setValue(kirby(), $this->kirbyOptions);
    (new ReflectionProperty(kirby(), 'auth'))->setValue(kirby(), null);
    Email::$debug = $this->emailDebug;
    Email::$emails = $this->emails;
    foreach ($this->kartOptions as $key => $value) {
        kart()->setOption($key, $value);
    }
});

it('consumes magic links once for login, signup and the user method', function (string $flow, array $methods, array $challenges): void {
    ($this->configureAuth)($methods, $challenges);
    expect(kirby()->auth()->methods()->has('password'))->toBeTrue()
        ->and(kirby()->auth()->methods()->has('kart-magic-link'))->toBeFalse();

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
        ->and(kirby()->auth()->status(kirby()->session())->state())->toBe(State::Inactive);
    $query['prg'] = '1';

    ($this->callRoute)(Router::MAGIC_LINK, 'GET', $query);
    expect(kirby()->user()?->email())->toBe($this->email)
        ->and(kirby()->session()->get('kart.magic-link'))->toBeNull();

    kirby()->auth()->logout();
    ($this->callRoute)(Router::MAGIC_LINK, 'GET', $query);
    expect(kirby()->user())->toBeNull();
})->with(['login', 'signup', 'method'])->with([
    'method list' => [['password', 'kart-magic-link'], ['email']],
    'method map' => [['password' => [], 'kart-magic-link' => []], ['email']],
    'challenge only' => [['password'], ['email', 'kart-magic-link']],
    'both' => [['password', 'kart-magic-link'], ['email', 'kart-magic-link']],
    'standalone with Panel 2FA' => [['password' => ['2fa' => true], 'kart-magic-link' => true], ['totp']],
]);

it('disables frontend endpoints when neither auth option enables the flag', function (array $methods, array $challenges): void {
    ($this->configureAuth)($methods, $challenges);
    foreach ([Router::SIGNUP_MAGIC, Router::MAGIC_LINK] as $pattern) {
        foreach (['GET', 'POST'] as $method) {
            $response = ($this->callRoute)($pattern, $method, ['email' => $this->email]);
            expect($response->code())->toBe(405);
        }
    }
    expect(Email::$emails)->toBe([])
        ->and(kirby()->user($this->email))->toBeNull()
        ->and(kirby()->auth()->methods()->has('password'))->toBeTrue();
})->with([
    'absent' => [['password'], ['email']],
    'explicitly disabled' => [['password', 'kart-magic-link' => false], ['email', 'kart-magic-link' => false]],
]);

it('rejects invalid signup links before creating an account', function (string $failure): void {
    ($this->callRoute)(Router::SIGNUP_MAGIC, 'POST', ['email' => $this->email, 'name' => 'Magic Customer']);
    preg_match('~https?://[^\s]+/kart/magic-link\?[^\s]+~', Email::$emails[0]->body()->text(), $match);
    parse_str(parse_url($match[0], PHP_URL_QUERY), $query);
    $query['prg'] = '1';
    $state = kirby()->session()->get('kart.magic-link');
    if ($failure === 'expired') {
        $state['expires'] = time() - 1;
    } elseif ($failure === 'email') {
        $state['email'] = 'different@example.test';
    } elseif ($failure === 'code') {
        $state['data']['secret'] = password_hash('not-the-code', PASSWORD_DEFAULT);
    } else {
        $query['signup'] = 'tampered';
    }
    kirby()->session()->set('kart.magic-link', $state);

    $response = ($this->callRoute)(Router::MAGIC_LINK, 'GET', $query);
    expect($response->code())->toBe($failure === 'signature' ? 401 : 200)
        ->and(kirby()->user())->toBeNull()
        ->and(kirby()->user($this->email))->toBeNull();
})->with(['expired', 'email', 'code', 'signature']);

it('uses the native Panel email code flow when the password method requires a second factor', function (): void {
    ($this->configureAuth)(['password' => ['2fa' => true]], ['kart-magic-link' => []]);
    $password = bin2hex(random_bytes(16));
    $user = kirby()->impersonate('kirby', fn () => kirby()->users()->create([
        'email' => $this->email,
        'role' => 'customer',
        'password' => $password,
    ]));
    $auth = kirby()->auth();
    $status = $auth->authenticate('password', $this->email, $password);
    expect($status->state())->toBe(State::Pending)
        ->and($status->mode())->toBe('2fa')
        ->and($status->challenge())->toBe('kart-magic-link')
        ->and(kirby()->user())->toBeNull();

    $challenge = $auth->challenges()->get($status->challenge(), $user, $status->mode());
    $body = Email::$emails[0]->body()->text();
    expect($body)->not->toContain(Router::MAGIC_LINK);
    preg_match('/\b\d{3} \d{3}\b/', $body, $match);

    expect($challenge)->toBeInstanceOf(MagicLinkChallenge::class)
        ->and($challenge->form($status->data())->render()['component'])->toBe('k-login-email-challenge-form')
        ->and($challenge->verify('wrong', $status->data()))->toBeFalse()
        ->and($auth->verifyChallenge($match[0])?->email())->toBe($this->email);
});
