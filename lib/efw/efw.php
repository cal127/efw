<?php

namespace EFW;
use \Exception, \ErrorException, \Spyc;

class EFW {
    public static $conf, $mods_conf;
    public static $mods_enabled = array(), $mods_loaded = array();



    public static function boot() {
        self::_loadLibs();
        self::_parseConf();
        self::_setupErrorHandling();
        self::_loadMods();
        self::_route();
    }


    private static function _loadLibs() {
        require_once __DIR__ .  '/../spyc.php'; // YAML
    }


    private static function _parseConf() {
        $conf_file = __DIR__ . '/../../conf/conf.yml';

        if (!file_exists($conf_file)) {
            throw new Exception('"conf.yml" could not be found.');
        }

        $data = Spyc::YAMLLoad($conf_file);

        self::$conf = $data['core'];
        self::$mods_conf = $data['mods'];
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
                          Spyc::YAMLLoad($extra_settings_file));
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
            $mod_cls::init(self::$mods_conf[$mod]);
        } catch (Exception $e) {
            throw new Exception("Error loading module '{$mod}'. " .
              "Message: " . $e->getMessage());
        }

        // add to loaded mods array
        self::$mods_loaded[] = $mod;
    }


    private static function _route() {
        try {
            // parse query string
            $delimiter_count = substr_count($_GET['q'], '/');
            if ($delimiter_count < 2) {
                $_GET['q']= $_GET['q'] . str_repeat('/', 2 - $delimiter_count);
            }
            list($ctrl, $act, $extra_params) = explode('/', $_GET['q'], 3);

            // include controller file
            include_once __DIR__ . '/../../app/ctrl/' . $ctrl . '.php';

            // generate controller class and action method names
            $ctrl = ucwords($ctrl) . 'Ctrl';
            $act = $act . 'Act';

            // check auth
            if (in_array('auth', self::$mods_enabled) && isset($ctrl::$auth)) {
                if (!is_array($ctrl::$auth)) {
                    $ctrl::$auth = array($ctrl::$auth);
                }

                if (!in_array(Auth::$user['role'], $ctrl::$auth)){
                    exit('Permission denied.');
                }
            }
            
            // check presence of controller & action
            if (!is_callable(array($ctrl, $act))) {
                // try to redirect to default action method
                $act = 'defaultAct';
                $extra_params = null;

                if (!is_callable(array($ctrl, $act))) { throw new Exception(); }
            }
        } catch (Exception $e) {
            throw $e;
            // fallback to default controller & action
            include_once __DIR__ . '/../../app/ctrl/default.php';
            $ctrl = 'DefaultCtrl';
            $act = 'defaultAct';
            $extra_params = null;
        }
        
        // set encoding. this can be overridden by the action
        if (self::$conf['charset']) {
            header('Content-type: text/html; charset=' .
              self::$conf['charset']);
        }

        // pass control to relevant action
        $ctrl::$act($extra_params);
    }
}

?>
