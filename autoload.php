<?php

require_once __DIR__ . '/vendor/.composer/autoload.php';
require_once __DIR__ . '/vendor/nikic/php-parser/lib/bootstrap.php';
require_once __DIR__ . '/vendor/symfony/class-loader/Symfony/Component/ClassLoader/UniversalClassLoader.php';

use Symfony\Component\ClassLoader\UniversalClassLoader;

$loader = new UniversalClassLoader();
$loader->register();

$loader->registerNamespace('ClassWeaver', __DIR__ . '/src');
