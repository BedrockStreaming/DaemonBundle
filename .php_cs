<?php

$config = new M6Web\CS\Config\Php72();

$config
    ->getFinder()
    ->in(
        [
            __DIR__.'/src',
            __DIR__.'/tests'
        ]
    );

return $config;
