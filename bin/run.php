<?php

require_once __DIR__ . '/../autoload.php';


$weaver = new ClassWeaver\Weaver();

// weave 'em, e. g. some symfony components
$classesDirectory = __DIR__ . '/../vendor/symfony/';
$weavedClassesMap = $weaver->weaveFilesInDirectory($classesDirectory)->getWeavedClassesMap();

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


###
### Testing
###

# symfony event component

// register normal autoloader for components (loader comes from autoload.php)
$loader->registerNamespace('Symfony\\Component\\EventDispatcher', __DIR__ . '/../vendor/symfony/event-dispatcher');
$loader->registerNamespace('Symfony\\Component\\HttpKernel', __DIR__ . '/../vendor/symfony/http-kernel');
$loader->registerNamespace('Symfony\\Component\\HttpFoundation', __DIR__ . '/../vendor/symfony/http-foundation');

// EventDispatcher::removeListener should be intercepted
$dispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
$dispatcher->removeListener('FROB', function() {});

// these constructor calls are also intercepted
$resolver = new Symfony\Component\HttpKernel\Controller\ControllerResolver();
$httpKernel = new Symfony\Component\HttpKernel\HttpKernel($dispatcher, $resolver);


