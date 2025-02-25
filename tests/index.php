<?php

const KIRBY_HELPER_DUMP = false;
const KIRBY_HELPER_E = false;
// const KART_PRODUCTS_UPDATE = false; // make them pure virtual

// require 'kirby/bootstrap.php';
require __DIR__.'/../vendor/autoload.php';

$kirby = new \Kirby\Cms\App;
$render = $kirby->render();

echo $render;
