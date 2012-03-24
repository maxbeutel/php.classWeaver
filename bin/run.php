<?php

require_once __DIR__ . '/../autoload.php';

$classesDirectory = __DIR__ . '/../vendor/symfony/event-dispatcher/';

$weaver = new ClassWeaver\Weaver();
$weaver->weaveFilesInDirectory($classesDirectory);