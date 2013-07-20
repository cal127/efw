This is a minimalistic PHP web framework i've made to use in my projects

This package currently is unstable, has no documentation available,
and has some important features missing. Use at your own risk.

__Usage:__

To use, copy the demo folder, rename it to your project name,
open 'composer.json' and delete the packages you won't need,
then run 'composer install' to install the packages and dependencies
into the vendor folder. Install composer first if it's not already installed.

Configure the conf/conf.yml file

You can also create a conf/conf.php file to be executed during boot

Copy the 'messaround' executable to your project root folder

__Features:__

- MVC architecture
- Works with [Composer][comp] dependency manager
- [PSR][psr] compatible
- Supports clean URLs
- Modular, can be extended with custom modules
- Templating support for [Twig][twig] and [Mustache][stache] template engines
- Built-in support for [Idiorm and Paris][idiorm] ORM libraries

__Missing (To be added/implemented):__

- Routing
- Logging
- Filtering
- Tests
- Documentation

[comp]: http://getcomposer.org
[psr]: http://github.com/php-fig/fig-standards
[twig]: http://twig.sensiolabs.org
[stache]: http://mustache.github.io
[idiorm]: http://j4mie.github.io/idiormandparis/
