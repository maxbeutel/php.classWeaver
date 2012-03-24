<?php

require_once __DIR__ . '/../autoload.php';


$weaver = new ClassWeaver\Weaver();

// weave 'em, e. g. symfony event dispatcher component
$classesDirectory = __DIR__ . '/../vendor/symfony/event-dispatcher/';
$weavedClassesMap = $weaver->weaveFilesInDirectory($classesDirectory)->getWeavedClassesMap();

// register normal autoloader for event component (laoder comres from autoload.php)
$loader->registerNamespace('Symfony\\Component\\EventDispatcher', __DIR__ . '/../vendor/symfony/event-dispatcher');

// register our autoloader which intercepts autoload requests for weaved classes
spl_autoload_register(
    function($className) use($weavedClassesMap) {
        if (isset($weavedClassesMap[$className])) {
            require_once $weavedClassesMap[$className];
        }
    },
    true, 
    true
);

$dispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
$dispatcher->removeListener('FROB');

