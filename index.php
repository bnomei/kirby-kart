<?php

@include_once __DIR__.'/vendor/autoload.php';

if (! function_exists('kart')) {
    function kart(): \Bnomei\Kart\Kart
    {
        return \Bnomei\Kart\Kart::singleton();
    }
}

Kirby::plugin(
    name: 'bnomei/kart',
    license: fn ($plugin) => new \Bnomei\Kart\License($plugin, \Bnomei\Kart\License::NAME),
    extends: [
        'options' => [
            'license' => '', // set your license from https://buy-turbo.bnomei.com code in the config `bnomei.turbo.license`
            'cache' => [
                'stripe' => true,
            ],
            'expire' => 0, // 0 = forever, null to disable caching
        ],
        'commands' => [
            'kart:flush' => [
                'description' => 'Flush Kart Cache(s)',
                'args' => [
                    'name' => [
                        'prefix' => 'n',
                        'longPrefix' => 'name',
                        'description' => 'Name of the cache to flush [*/all/inventory/storage/tub].',
                        'defaultValue' => 'all', // flush all
                        'castTo' => 'string',
                    ],
                ],
                'command' => static function ($cli): void {
                    $name = $cli->arg('name');
                    $cli->out("🚽 Flushing Kart Cache [$name]...");
                    \Bnomei\Kart\Kart::flush($name);
                    $cli->success('✅ Done.');

                    if (function_exists('janitor')) {
                        janitor()->data($cli->arg('command'), [
                            'status' => 200,
                            'message' => "Kart Cache [$name] flushed.",
                        ]);
                    }
                },
            ],
        ],
    ]);
