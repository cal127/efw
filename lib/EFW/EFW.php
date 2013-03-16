<?php

namespace EFW;
use \Exception, \ErrorException, \Spyc;

class EFW {
    public static $conf, $mods_conf;
    public static $mods_enabled = array(), $mods_loaded = array();
    private static $no_auth_callable;



    public static function boot() {
        self::_loadLibs();
        self::_parseConf();
        self::_setupErrorHandling();
        self::_loadMods();
        self::_other();
        self::_userConfig();
        self::_route();
    }


    public static function registerNoAuthFunc($callable) {
        if (!is_callable($callable)) {
            throw new Exception('First parameter must be a callable.'); }

        // test whether it's a method or function/closure
        if (is_array($callable)
          || (is_string($callable) && strpos($callable, '::') !== false)) {
            if (is_string($callable)) { $callable = explode('::', $callable); }
            $rfa = new \ReflectionMethod($callable[0], $callable[1]);
        } else {
            $rfa = new \ReflectionFunction($callable);
        }

        if ($rfa->getNumberOfParameters() != 3) {
            throw new Exception('The callable must have three arguments.');
        }

        self::$no_auth_callable = $callable;
    }


    private static function defaultNoAuthFunc($permitted_roles,
                                              $user_role,
                                              $query_string) {
        exit('Permission denied.');
    }


    private static function _loadLibs() {
        require_once __DIR__ .  '/utils.php';
        require_once __DIR__ . '/../vendor/autoload.php'; // composer
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


        $mod_cls = __NAMESPACE__ . '\\' . $mod;

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


    private static function _other() {
        spl_autoload_register('autoload');
        self::registerNoAuthFunc(array(__CLASS__, 'defaultNoAuthFunc'));
    }


    private static function _userConfig() {
        $conf_file = __DIR__ . '/../../conf/conf.php';
        if (is_file($conf_file)) { require_sandbox($conf_file); }
    }


    private static function _route() {
        try {
            // self-explanatory
            undo_magic_quotes();

            // what the hell?
            if (self::$conf['pretty_urls']) {
                $decoded = urldecode($_SERVER['REQUEST_URI']);
                $inter = array_intersect(explode('/', $decoded),
                                         explode('/', self::$conf['url']));
                $params = ltrim(substr($decoded, strlen(implode('/', $inter))),
                                '/');
            } else {
                $params = $_GET['q'];
            }

            // parse query string
            sscanf($params,
                   '%[^/]/%[^/]/%s',
                   $ctrl,
                   $act,
                   $extra_params);

            // generate controller class and action method names
            $ctrl = ucwords($ctrl) . 'Ctrl';
            $act = $act . 'Act';

            // check presence of controller & action
            if (!class_exists($ctrl, true)) { throw new Exception(); }

            if (!method_exists($ctrl, $act)) {
                $act = 'defaultAct';
                $extra_params = null;

                if (!method_exists($ctrl, $act)) { throw new Exception(); }
            }
            
            // check auth
            if (in_array('Auth', self::$mods_enabled) && isset($ctrl::$auth)) {
                if (!is_array($ctrl::$auth)) {
                    $ctrl::$auth = array($ctrl::$auth);
                }

                if (!in_array(Auth::$user['role'], $ctrl::$auth)){
                    call_user_func(self::$no_auth_callable,
                                   $ctrl::$auth,
                                   Auth::$user['role'],
                                   $params);
                }
            }
        } catch (Exception $e) {
            // fallback to default controller & action
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
