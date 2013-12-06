<?php

$finder = Symfony\CS\Finder\DefaultFinder::create()
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->exclude('vendor')
    ->exclude('Fixtures')
    ;

return Symfony\CS\Config\Config::create()
    ->finder($finder)
;
