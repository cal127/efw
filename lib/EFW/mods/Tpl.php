<?php

namespace EFW;
use \Exception;

class Tpl {
    public static $conf;
    private static $engine;


    public static function init(&$conf) {
        self::$conf = $conf;
        $callback = 'init' . strtoupper($conf['engine']);
        self::$callback();
    }

    public static function initNative() { self::$engine = new NativeTpl(); }

    public static function initMustache() {
        require __DIR__ . '/../../Mustache/Autoloader.php';
        \Mustache_Autoloader::register();

        self::$engine = new \Mustache_Engine(array(
            'loader' => new \Mustache_Loader_FilesystemLoader(__DIR__ .
              '/../../../app/view'),
            'partials_loader' => new \Mustache_Loader_FilesystemLoader(__DIR__ .
              '/../../../app/view/' . self::$conf['options']['partials_path']),
            'helpers' => array(
                '_url' => function($qs, \Mustache_LambdaHelper $h) {
                    return _url($h->render($qs)); 
                }
            )
        ));
    }

    public static function render($tpl, $params) {
        echo self::$engine->render($tpl, $params);
    }
}

class NativeTpl {
    public function render($tpl, $params) {
        $filename = __DIR__ . '/../../../app/view/' . $tpl . '.php';
        if (!file_exists($filename)) {
            throw new Exception("Template '{$tpl}' does not exist.");
        }

        if (Tpl::$conf['options']['auto_escape']) {
            $callback = function ($val) {
                if (is_object($val)) { return $val; }
                return htmlentities($val, ENT_QUOTES, EFW::$conf['charset']);
            };
            $params = self::array_map_recursive($callback, $params);
        }

        extract($params);
        include $filename;
    }
    
    private static function array_map_recursive($fn, $arr) {
        $rarr = array();
        foreach ($arr as $k => $v) {
            $rarr[$k] = is_array($v)
                ? call_user_func(__METHOD__, $fn, $v)
                : $fn($v);
        }
        return $rarr;
    }
}

?>
