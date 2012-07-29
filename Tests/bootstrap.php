<?php

if (!is_file('vendor/autoload.php')) {
    throw new \RuntimeException('Did not find vendor/autoload.php. Did you run "composer install --dev"?');
}

require_once 'vendor/autoload.php';
