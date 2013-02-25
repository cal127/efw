<?php

namespace EFW;
use \Exception, \ErrorException;

class EFW {
    public static $conf, $mods_conf;
    public static $mods_enabled = array(), $mods_loaded = array();



    public static function boot() {
        self::_parseConf();
        self::_setupErrorHandling();
        self::_loadMods();
        self::_route();
    }


    private static function _parseConf() {
        self::$conf = yaml_parse_file(__DIR__ . '/../../conf/conf.yml', 0);
        self::$mods_conf = yaml_parse_file(__DIR__ . '/../../conf/conf.yml', 1);
        self::$mods_enabled = array_keys(array_filter(self::$conf['mods']));
    }


    private static function _setupErrorHandling() {
        ini_set('display_errors', self::$conf['debug']);
        error_reporting(E_ALL);
        set_error_handler(function ($no, $str, $fl, $ln) {
                              throw new ErrorException($str,$no,0,$fl,$ln); });
        set_exception_handler(function($e) { printf('<pre>%s</pre>', $e); });
    }


    private static function _loadMods() {
        foreach (self::$mods_enabled as $mod) {
            // It's checked if the module is loaded because it could already
            // be loaded by a dependency loading statement
            if (!in_array($mod, self::$mods_loaded)) { self::_loadMod($mod); }
        }
    }


    private static function _loadMod($mod) {
        $prefix = __DIR__ . '/mods/' . $mod;
        $mod_file = $prefix . '.php';
        $extra_settings_file = $prefix . '.yml';

        // include module file
        try {
            include_once $mod_file;
        } catch (Exception $e) {
            throw new Exception("Error loading module '{$mod}'. " .
              "Message: " . $e->getMessage());
        }

        // load additional settings
        if (file_exists($extra_settings_file)) {
            self::$mods_conf[$mod] =
              array_merge(self::$mods_conf[$mod],
                          yaml_parse_file($extra_settings_file));
        }

        // get or generate class name
        $mod_cls = isset(self::$mods_conf[$mod]['class_name'])
                            ? self::$mods_conf[$mod]['class_name']
                            : ucwords($mod);

        $mod_cls = __NAMESPACE__ . '\\' . $mod_cls;

        // load dependencies
        if (isset($mod_cls::$dependencies)) {
            foreach ($mod_cls::$dependencies as $dep) {
                if (!in_array($dep, self::$mods_enabled)) {
                    throw new Exception("Mod '{$dep}' needs to be enabled "
                      . "in order for mode '{$mod}' to work.");
                }

                if (!in_array($dep, self::$mods_loaded)) {
                    self::_loadMod($dep);
                }
            }
        }

        // init mod
        try {
            $mod_cls::init(&self::$mods_conf[$mod]);
        } catch (Exception $e) {
            throw new Exception("Error loading module '{$mod}'. " .
              "Message: " . $e->getMessage());
        }

        // add to loaded mods array
        self::$mods_loaded[] = $mod;
    }


    private static function _route() {
        try {
            $q = $_GET['q'] . '//'; // i'm a hack!

            list($ctrl, $act, $param) = explode('/', $q);

            include_once __DIR__ . '/../../app/ctrl/' . $ctrl . '.php';

            $ctrl = ucwords($ctrl) . 'Ctrl';
            $act = $act . 'Act';

            if (in_array('auth', self::$mods_enabled) && isset($ctrl::$auth)) {
                if (!is_array($ctrl::$auth)) {
                    $ctrl::$auth = array($ctrl::$auth);
                }

                if (!in_array(Auth::$user['role'], $ctrl::$auth)){
                    exit('Permission denied.');
                }
            }
            
            if (!is_callable(array($ctrl, $act))) {
                $act = 'defaultAct';
                if (!is_callable(array($ctrl, $act))) { throw new Exception(); }
            }
        } catch (Exception $e) {
            include_once __DIR__ . '/../../app/ctrl/default.php';
            $ctrl = 'DefaultCtrl';
            $act = 'defaultAct';
            $param = null;
        }

        $ctrl::$act($param);
    }
}

?>
