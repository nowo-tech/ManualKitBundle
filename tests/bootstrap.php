<?php

declare(strict_types=1);
use Symfony\Component\Filesystem\Filesystem;

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('Run composer install in ManualKitBundle before executing tests.');
}

require $autoload;

// Every run starts from a fresh test kernel cache.
(new Filesystem())->remove(sys_get_temp_dir() . '/nowo-manual-kit-bundle');
