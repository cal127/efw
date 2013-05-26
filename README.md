This is a minimalistic PHP web framework i've made to use in my projects

This package currently is non-stable, has no documentation available,
and has some important features missing. Use at your own risk.

_Usage:_

To use, copy the demo folder, rename it to your project name,
open 'composer.json' and delete the packages you won't need, then run 'composer install'
to install the packages and dependencies into the vendor folder. Install
composer first if it's not already installed.

Configure the conf/conf.yml file


_Features:_

    -MVC architecture
    -Works with [Composer] dependency manager
    -[PSR] compatible
    -Supports clean URLs
    -Modular, can be extended with custom modules
    -Templating support for [Twig] and [Mustache] template engines
    -Built-in support for [Idiorm and Paris] ORM libraries


_Missing (To be added/implemented):_

    -Routing
    -Logging
    -Filtering
    -Tests
    -Documentation


[Composer]: getcomposer.org
[PSR]: github.com/php-fig/fig-standards
[Twig]: twig.sensiolabs.org
[Mustache]: mustache.github.io
[Idiorm and Paris]: j4mie.github.io/idiormandparis/
