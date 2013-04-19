<?php

namespace EFW;
use \Exception, \ErrorException, \Spyc;

class EFW
{
    public static $conf, $mods_conf;
    public static $mods_enabled = array(), $mods_loaded = array();
    private static $ctrl, $act, $params;



    public static function getCtrl() { return self::$ctrl; }
    public static function getAct() { return self::$act; }
    public static function getParams() { return self::$params; }


    public static function boot()
    {
        self::setupLibsAndAutoloaders();
        self::parseConf();
        self::setupErrorHandling();
        self::parseQS();
        self::loadMods();
        self::userConfig();
        self::setDefaultHeaders();
        self::route();
    }


    private static function setupLibsAndAutoloaders()
    {
        // Import util functions for EFW
        require_once __DIR__ .  '/utils.php';

        // Autoloader for composer
        require_once __DIR__ . '/../vendor/autoload.php'; // composer

        // Autoloader for local libs
        spl_autoload_register(function($class_name)
        {
            $lib_dir = __DIR__ . '/..';

            $file = $lib_dir . '/' . $class_name . '.php';

            try {
                include_once $file;
            } catch (Exception $e) { }
        });

        // Autoloader for mods
        spl_autoload_register(function($class_name)
        {
            $class_name = ltrim($class_name, '\\');

            if (strpos($class_name, __NAMESPACE__ . '\\') !== 0) {
                return;
            }

            $mod_name = substr($class_name, strlen(__NAMESPACE__) + 1);
            $file_name = __DIR__ . '/mods/' . $mod_name . '.php';

            try {
                include_once $file_name;
            } catch (Exception $e) { }
        });
        
        // Autoloader for models and controllers
        spl_autoload_register(function($class_name)
        {
            $model_dir = __DIR__ . '/../../app/model';
            $ctrl_dir = __DIR__ . '/../../app/ctrl';

            $file_name = $class_name . '.php';

            try {
                include_once $model_dir . '/' . $file_name;
            } catch (Exception $e) {
                try {
                    include_once $ctrl_dir . '/' . $file_name;
                } catch (Exception $e) { }
            }
        });
    }


    private static function parseConf()
    {
        $conf_file = __DIR__ . '/../../conf/conf.yml';

        if (!file_exists($conf_file)) {
            throw new Exception('"conf.yml" could not be found.');
        }

        $data = Spyc::YAMLLoad($conf_file);

        self::$conf = $data['core'];
        self::$mods_conf = $data['mods'];
        self::$mods_enabled = array_keys(array_filter(self::$conf['mods']));
    }


    private static function setupErrorHandling()
    {
        ini_set('display_errors', self::$conf['debug']);
        error_reporting(E_ALL);
        set_error_handler(function ($no, $str, $fl, $ln) {
                              throw new ErrorException($str,$no,0,$fl,$ln); });
        set_exception_handler(function($e) { printf('<pre>%s</pre>', $e); });
    }
    

    private static function parseQS()
    {
        self::undo_magic_quotes();

        // get query string
        if (self::$conf['pretty_urls']) {
            $decoded = urldecode($_SERVER['REQUEST_URI']);
            $inter = array_intersect(explode('/', $decoded),
                                     explode('/', self::$conf['url']));
            $qs = ltrim(substr($decoded, strlen(implode('/', $inter))),
                            '/');
        } else {
            $qs = !empty($_GET['q']) ? $_GET['q'] : '';
        }

        // parse query string
        list($ctrl, $act, $params) = sscanf($qs, '%[^/]/%[^/]/%s');

        // check presence of controller & action
        if (!method_exists(ucfirst($ctrl) . 'Ctrl', $act . 'Act')) {
            // Try to redirect to the default action of the ctrl in question
            $act = 'default';
            $params = '';

            if (!method_exists(ucfirst($ctrl) . 'Ctrl', $act . 'Act')) {
                $ctrl = 'default';
            }
        }

        // save
        self::$ctrl = $ctrl;
        self::$act = $act;
        self::$params = $params;
    }


    private static function loadMods()
    {
        foreach (self::$mods_enabled as $mod) {
            // It's checked if the module is loaded because it could already
            // be loaded by a dependency loading statement
            if (!in_array($mod, self::$mods_loaded)) { self::loadMod($mod); }
        }
    }


    private static function loadMod($mod)
    {
        // generate mod class name
        $mod_cls = __NAMESPACE__ . '\\' . $mod;

        // check for presence of module
        if (!class_exists($mod_cls, true)) {
            throw new Exception("Module '{$mod}' could not be found. " .
              'Message: ' . $e->getMessage());
        }

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
            throw new Exception("Error initializing module '{$mod}'. " .
              'Message: ' . $e->getMessage());
        }

        // add to loaded mods array
        self::$mods_loaded[] = $mod;
    }


    private static function userConfig()
    {
        $conf_file = __DIR__ . '/../../conf/conf.php';
        if (is_file($conf_file)) { self::require_sandbox($conf_file); }
    }


    private static function setDefaultHeaders() {
        // encoding
        if (self::$conf['charset']) {
            header('Content-type: text/html; charset=' .
              self::$conf['charset']);
        }
    }


    private static function route()
    {
        // generate ctrl and act names
        $ctrl_name = ucfirst(self::$ctrl) . 'Ctrl';
        $act_name = self::$act . 'Act';

        // check auth if enabled
        if (in_array('Auth', self::$mods_enabled) && isset($ctrl_name::$auth)) {
            if (!is_array($ctrl_name::$auth)) {
                $ctrl_name::$auth = array($ctrl_name::$auth);
            }

            if (!in_array(Auth::getUserRole(), $ctrl_name::$auth)){
                Auth::callAuthErrorCallback($ctrl_name::$auth,
                                            Auth::getUserRole(),
                                            self::$ctrl,
                                            self::$act,
                                            self::$params);
            }
        }
        
        // pass control to relevant action
        $ctrl_name::$act_name(self::$params);
    }

    
    // utils //////////////////////////////////////////////////////////////////
    private static function undo_magic_quotes()
    {
        if (get_magic_quotes_gpc()) {
            $process = array(&$_GET, &$_POST, &$_COOKIE, &$_REQUEST);

            while (list($key, $val) = each($process)) {
                foreach ($val as $k => $v) {
                    unset($process[$key][$k]);
                    if (is_array($v)) {
                        $process[$key][stripslashes($k)] = $v;
                        $process[] = &$process[$key][stripslashes($k)];
                    } else {
                        $process[$key][stripslashes($k)] = stripslashes($v);
                    }
                }
            }

            unset($process);
        }
    }

    private static function require_sandbox($filename)
    {
        $f = function() use ($filename) { require $filename; };
        $f();
    }
    // /utils //////////////////////////////////////////////////////////////////
}

?>