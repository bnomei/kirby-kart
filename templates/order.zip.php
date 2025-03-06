<?php

use Bnomei\Kart\Helper;
use Kirby\Cms\File;
use Kirby\Filesystem\F;

/** @var OrderPage $page */
/** @var File $zip */
$zip = $page->downloads();

if ($zip) {
    $filename = F::safeName($page->title().'.zip');
    if ($alt = Helper::sanitize(get('filename'))) {
        $filename = $alt;
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Content-Length: '.$zip->size());
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    header('Expires: 0');
    header('Content-Transfer-Encoding: binary');

    echo file_get_contents($zip->root());
    // $zip->download($filename);
}

exit();
