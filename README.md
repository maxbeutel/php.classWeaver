# Whats this?

ClassWeaver is a proof of concept/explorative coding effort in order to demonstrate how AOP in PHP could be implemeneted by directly weaving class methods instead of subclassing and overriding the parent methods. 

It´s mostly geared towards Symfony projects.

## How does it work?

All of the magic is possible thanks to [Nikic´s PHP Parser](https://github.com/nikic/PHP-Parser/)

## Pro

- you directly work with the "real" instance, not with some Proxy class – this can avoid conflicts with other libraries such as Doctrine which also create proxied subclasses
- as less runtime cost as subclassing approach (as less as possible with AOP)
- can speed up autoloading as the path to a weaved class is stored in a lookup map

## Contra

- very slow for large source trees when each and every class needs to be weaved
- rather complicated
- code is quite ugly at the moment
- ...

# Try it

In this repo a checkout of [Symfony HttpKernel component](https://github.com/symfony/HttpKernel) is included via [composer](https://github.com/composer/composer) in order to see how ClassWeaver can work with a more complex/larger codebase.

When you cloned this repo and downloaded composer, do:

    php composer.phar install
    php bin/run.php

You should see some debug output which class files were processed by ClassWeaver and which class methods were intercepted.

## Beware

- I didnt do any more testing then run bin/run.php so its quite likely that ClassWeaver wont work with your codebase
