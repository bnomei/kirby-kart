<?php

use Bnomei\Kart\Router;
use Kirby\Email\Email;
use Kirby\Filesystem\F;
use Kirby\Http\Request;

beforeEach(function (): void {
    kirby()->impersonate(null);
    kirby()->auth()->logout();
    if (kirby()->users()->isEmpty()) {
        kirby()->impersonate('kirby', fn () => kirby()->users()->create([
            'email' => 'fixture-admin@example.test',
            'password' => bin2hex(random_bytes(16)),
            'role' => 'admin',
        ]));
    }
    $this->loginFile = kirby()->auth()->logfile();
    $this->loginLog = is_file($this->loginFile) ? file_get_contents($this->loginFile) : null;
    F::remove($this->loginFile);
    $this->email = 'magic+'.bin2hex(random_bytes(5)).'@example.test';
    $this->customer = fn () => kirby()->impersonate('kirby', fn () => kirby()->users()->create([
        'email' => $this->email,
        'password' => bin2hex(random_bytes(16)),
        'role' => 'customer',
    ]));
    $this->originalRequest = kirby()->request();
    $this->originalOptions = (new ReflectionProperty(kirby(), 'options'))->getValue(kirby());
    $options = $this->originalOptions;
    $options['auth']['methods'] = ['code', 'kart-magic-link', 'password'];
    $options['auth']['challenges'] = ['kart-magic-link'];
    (new ReflectionProperty(kirby(), 'options'))->setValue(kirby(), $options);
    $this->storedOptions = [];
    foreach (['middlewares.enabled' => [], 'middlewares.ratelimit.enabled' => false, 'captcha.enabled' => false, 'turnstile.enabled' => false, 'router.mode' => 'json'] as $key => $value) {
        $this->storedOptions[$key] = kart()->option($key);
        kart()->setOption($key, $value);
    }
    $this->debug = Email::$debug;
    $this->emails = Email::$emails;
    Email::$debug = true;
    Email::$emails = [];
    $this->route = function (string $method, string $pattern, array $data): void {
        (new ReflectionProperty(kirby(), 'request'))->setValue(kirby(), new Request([
            'method' => $method,
            'url' => url($pattern).($method === 'GET' ? '?'.http_build_query($data) : ''),
            $method === 'GET' ? 'query' : 'body' => $data,
        ]));
        foreach ((require dirname(__DIR__).'/routes.php')(kirby()) as $route) {
            if ($route['pattern'] === $pattern && $route['method'] === $method) {
                $route['action']();

                return;
            }
        }
        throw new LogicException('Missing route '.$pattern);
    };
});

afterEach(function (): void {
    kirby()->auth()->logout();
    kirby()->session()->remove('kart.magic-link');
    if ($this->loginLog === null) {
        F::remove($this->loginFile);
    } else {
        file_put_contents($this->loginFile, $this->loginLog);
    }
    if ($user = kirby()->user($this->email)) {
        kirby()->impersonate('kirby', fn () => $user->delete());
    }
    (new ReflectionProperty(kirby(), 'options'))->setValue(kirby(), $this->originalOptions);
    (new ReflectionProperty(kirby(), 'request'))->setValue(kirby(), $this->originalRequest);
    foreach ($this->storedOptions as $key => $value) {
        kart()->setOption($key, $value);
    }
    Email::$emails = $this->emails;
    Email::$debug = $this->debug;
});

it('redeems a storefront link once without starting a Kirby challenge', function (string $flow): void {
    if ($flow !== 'signup') {
        $user = ($this->customer)();
    }
    if ($flow === 'method') {
        $user->sendMagicLink('/account');
    } else {
        ($this->route)('POST', $flow === 'signup' ? Router::SIGNUP_MAGIC : Router::MAGIC_LINK, ['email' => $this->email]);
    }
    expect(kirby()->session()->get('kirby.challenge.type'))->toBeNull()
        ->and(Email::$emails)->toHaveCount(1);
    preg_match('~https?://[^\s]+/kart/magic-link\?[^\s]+~', Email::$emails[0]->body()->text(), $matches);
    parse_str(parse_url($matches[0], PHP_URL_QUERY), $query);
    $query['prg'] = '1';
    ($this->route)('GET', Router::MAGIC_LINK, $query);
    expect(kirby()->user()?->email())->toBe($this->email);
    kirby()->auth()->logout();
    ($this->route)('GET', Router::MAGIC_LINK, $query);
    expect(kirby()->user())->toBeNull();
})->with(['login', 'signup', 'method']);

it('rejects an expired signup link before creating a customer', function (): void {
    ($this->route)('POST', Router::SIGNUP_MAGIC, ['email' => $this->email]);
    preg_match('~https?://[^\s]+/kart/magic-link\?[^\s]+~', Email::$emails[0]->body()->text(), $matches);
    parse_str(parse_url($matches[0], PHP_URL_QUERY), $query);
    $link = kirby()->session()->get('kart.magic-link');
    $link['expires'] = time() - 1;
    kirby()->session()->set('kart.magic-link', $link);
    ($this->route)('GET', Router::MAGIC_LINK, $query + ['prg' => '1']);
    expect(kirby()->user())->toBeNull()->and(kirby()->user($this->email))->toBeNull();
});

it('preserves a pending Kirby code when a storefront link is requested', function (): void {
    $user = ($this->customer)();
    kirby()->auth()->createChallenge($this->email);
    $body = Email::$emails[0]->body()->text();
    expect($body)->not->toContain(Router::MAGIC_LINK);
    preg_match('/\b\d{3} \d{3}\b/', $body, $matches);
    $user->sendMagicLink('/account');
    expect(kirby()->auth()->verifyChallenge($matches[0])?->email())->toBe($this->email);
});
