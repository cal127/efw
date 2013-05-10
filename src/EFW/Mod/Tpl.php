<?php

namespace EFW\Mod;

use \Exception;
use \EFW\EFW;


class Tpl
{
    public static $conf;
    private static $engine;

    public static function getEngine()
    {
        return self::$engine;
    }


    public static function init(&$conf)
    {
        self::$conf = $conf;
        $callback = 'init' . ucfirst($conf['engine']);
        self::$callback();
    }


    // native template engine, defined below
    private static function initNative()
    {
        self::$engine = new NativeTpl();
    }


    // twig template engine
    private static function initTwig()
    {
        $loader = new \Twig_Loader_Filesystem(__DIR__ .
            '/../../../../../../app/' . EFW::$conf['app_namespace'] .
            '/View');

        self::$engine = new \Twig_Environment(
            $loader,
            array(
                'cache' => __DIR__ .
                    '/../../../../../../app/' . EFW::$conf['app_namespace'] .
                    '/View/cache',
                'auto_reload' => true
            )
        );

        self::$engine->addFunction(
            new \Twig_SimpleFunction(
                '_url',
                function($x) { return EFW::url($x); }
            )
        );
    }


    // mustache
    private static function initMustache()
    {
        self::$engine = self::mustacheFactory();
    }


    private static function mustacheFactory($extra_helpers = null)
    {
        if (!is_array($extra_helpers)) {
            $extra_helpers = array();
        }

        $partials_path = isset(self::$conf['options']['partials_path'])
          ? '/' . self::$conf['options']['partials_path'] : '';

        return new \Mustache_Engine(array(
            'loader' => new \Mustache_Loader_FilesystemLoader(__DIR__ .
              '/../../../../../../app/' . EFW::$conf['app_namespace'] .
              '/View'),
            'partials_loader' => new \Mustache_Loader_FilesystemLoader(__DIR__ .
              '/../../../../../../app/' . EFW::$conf['app_namespace'] .
              '/View/' .  $partials_path),
            'helpers' => array_merge($extra_helpers, array(
                '_url' => function($qs, \Mustache_LambdaHelper $h) {
                    return EFW::url($h->render($qs)); 
                }
            )
        )));
    }


    public static function mustacheAddHelpers($extra_helpers) {
        if (self::$conf['engine'] != 'mustache') {
            throw new Exception(__METHOD__ . '():You can\'t use this method '
             . 'unless you are using "mustache" engine.');
        }

        self::$engine = self::mustacheFactory($extra_helpers);
    }
}

class NativeTpl
{
    public function render($tpl, $params)
    {
        $filename = __DIR__ . '/../../../app/view/' . $tpl . '.php';
        if (!file_exists($filename)) {
            throw new Exception("Template '{$tpl}' does not exist.");
        }

        if (!empty(Tpl::$conf['options']['auto_escape'])) {
            $callback = function ($val) {
                if (is_object($val)) { return $val; }
                return htmlentities($val, ENT_QUOTES, EFW::$conf['charset']);
            };
            $params = self::array_map_recursive($callback, $params);
        }

        extract($params);
        include $filename;
    }

    
    private static function array_map_recursive($fn, $arr)
    {
        $rarr = array();

        foreach ($arr as $k => $v) {
            $rarr[$k] = is_array($v)
                ? call_user_func(__METHOD__, $fn, $v)
                : $fn($v);
        }

        return $rarr;
    }
}
