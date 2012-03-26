<?php

require_once __DIR__ . '/vendor/.composer/autoload.php';

use Symfony\Component\ClassLoader\UniversalClassLoader;

$loader = new UniversalClassLoader();
$loader->register();

$loader->registerNamespace('ClassWeaver', __DIR__ . '/src');
