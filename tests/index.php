<?php

use Kirby\Cms\App;

const KIRBY_HELPER_DUMP = false;
const KIRBY_HELPER_E = false;

// require 'kirby/bootstrap.php';
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/load-env.php';
require __DIR__.'/Testing.php';

$host = $_SERVER['HTTP_HOST'] ?? getenv('KIRBY_HOST') ?: 'frankenphp.kart.orb.local';
$_SERVER['SERVER_NAME'] = explode(':', strval($host))[0]; // k->env->isLocal()
$_SERVER['HTTP_HOST'] ??= $_SERVER['SERVER_NAME'];

$kirby = new App;
$render = $kirby->render();

echo $render;
