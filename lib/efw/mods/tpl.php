<?php

namespace EFW;
use \Exception;

class Tpl {
    public static $conf;


    public static function init(&$conf) {
        self::$conf = &$conf;
    }

    public static function render($tpl, $params) {
        if (!file_exists(__DIR__ . '/../../../app/view/' . $tpl . '.php')) {
            throw new Exception("Template '{$tpl}' does not exist.");
        }

        if (self::$conf['auto_escape']) {
            include(__DIR__ . '/../utils.php');  

            $callback = function ($val) {
                if (is_object($val)) { return $val; }
                return htmlentities($val, ENT_QUOTES, EFW::$conf['charset']);
            };
            $params = array_map_recursive($callback, $params);
        }

        extract($params);

        include __DIR__ . '/../../../app/view/' . $tpl . '.php';
    }
}

?>
