<?php

$autoload_file = __DIR__ . '../vendor/autoload.php';

if (!is_file($autoload_file)) {
    throw new Exception(
          'Either vendor folder or autoload.php is missing.'
        . ' Make sure you\'ve executed "composer install" command before'
        . ' testing your application.'
    );
}

require_once $autoload_file;

EFW\EFW::boot();
