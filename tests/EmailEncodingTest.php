<?php

use Bnomei\Kart\Provider\Kirby as KirbyProvider;
use Bnomei\Kart\Router;
use Kirby\Http\Request;

require_once __DIR__.'/Helpers.php';

it('logs in a customer with a plus-addressed email', function (): void {
    findOrCreateTestUser()->logout();
    $original = kirby()->request();
    $request = new ReflectionProperty(kirby(), 'request');
    $options = ['middlewares.enabled' => [], 'captcha.enabled' => false, 'turnstile.enabled' => false, 'router.mode' => 'json'];
    $previous = [];
    foreach ($options as $key => $value) {
        $previous[$key] = kart()->option($key);
        kart()->setOption($key, $value);
    }
    $user = kirby()->impersonate('kirby', fn () => kirby()->users()->create([
        'email' => 'login+alias@example.test',
        'role' => 'customer',
        'password' => 'test-password-123',
    ]));
    $request->setValue(kirby(), new Request([
        'method' => 'POST',
        'body' => ['email' => $user->email(), 'password' => 'test-password-123'],
    ]));

    try {
        $routes = (require dirname(__DIR__).'/routes.php')(kirby());
        $route = array_values(array_filter($routes, fn ($route) => $route['pattern'] === Router::LOGIN && $route['method'] === 'POST'))[0];
        $response = $route['action']();

        expect($response->code())->toBe(200)
            ->and(kirby()->user()?->email())->toBe('login+alias@example.test');
    } finally {
        kirby()->user($user->id())->logout();
        kirby()->impersonate('kirby', fn () => kirby()->user($user->id())->delete());
        $request->setValue(kirby(), $original);
        foreach ($previous as $key => $value) {
            kart()->setOption($key, $value);
        }
    }
});

it('preserves and escapes email query values in authentication forms', function (string $snippet, string $email): void {
    $original = kirby()->request();
    $request = new ReflectionProperty(kirby(), 'request');
    $request->setValue(kirby(), new Request(['query' => 'email='.urlencode($email)]));

    try {
        $html = snippet($snippet, ['page' => site()], true);
        $dom = new DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        $input = $xpath->query('//input[@name="email"]')->item(0);

        expect($input->getAttribute('value'))->toBe($email)
            ->and($input->hasAttribute('autofocus'))->toBeFalse();
    } finally {
        $request->setValue(kirby(), $original);
    }
})->with(['kart/login', 'kart/login-magic', 'kart/signup-magic'])
    ->with(['name+%2B&alias@example.test', '" autofocus="']);

it('preserves plus aliases and literal percent sequences in completed checkout emails', function (): void {
    $session = kirby()->session();
    $keys = [
        'bnomei.kart.checkout_form_data',
        'bnomei.kart.kirby_cms.session_id',
        'bnomei.kart.kirby_cms.cart_hash',
    ];
    $previous = array_map(fn ($key) => $session->get($key), $keys);
    $session->set($keys[0], ['email' => 'Name+%2B@example.test']);
    $session->set($keys[1], 'email-encoding-test');
    $session->set($keys[2], kart()->cart()->hash());

    try {
        $data = (new KirbyProvider(kirby()))->completed(['session_id' => 'email-encoding-test']);

        expect($data['email'])->toBe('name+%2b@example.test')
            ->and($data['customer']['email'])->toBe('name+%2b@example.test');
    } finally {
        foreach ($keys as $i => $key) {
            if ($previous[$i] === null) {
                $session->remove($key);
            } else {
                $session->set($key, $previous[$i]);
            }
        }
    }
});
