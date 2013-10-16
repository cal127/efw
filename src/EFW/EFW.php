<?php

namespace EFW;

use \Exception;
use \ErrorException;
use \Spyc;
use \EFW\Mod\Auth;


class EFW
{
    public static $conf, $mods_conf;
    public static $mods_enabled = array(), $mods_loaded = array();
    private static $ctrl, $act, $params;



    // Utility functions //////////////////////////////////////////////////////
    public static function url($qs)
    {
        $full_url = EFW::$conf['url'];

        if (EFW::$conf['clean_urls']) {
            return $full_url . '/' . $qs;
        }

        return $full_url . '/index.php?q=' . $qs;
    }


    public static function getCtrl()
    {
        return self::$ctrl;
    }


    public static function getAct()
    {
        return self::$act;
    }


    public static function getParams()
    {
        return self::$params;
    }

    
    public static function getURL()
    {
        return self::url(self::getCtrl() . '/' . self::getAct());
    }
    ///////////////////////////////////////////////////////////////////////////


    public static function boot($custom_conf_file = null)
    {
        self::parseConf($custom_conf_file);
        self::setupErrorHandling();
        self::parseQS();
        self::loadMods();
        self::userConfig();
        self::setDefaultHeaders();
        self::route();
    }


    public static function shellBoot($custom_conf_file = null)
    {
        self::parseConf($custom_conf_file);
        self::setupErrorHandling();
        self::loadMods();
        self::userConfig();
    }


    public static function parseConf($custom_conf_file = null)
    {
        $conf_file = $custom_conf_file
            ? $custom_conf_file
            : __DIR__ . '/../../../../../conf/conf.yml';

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
        if (self::$conf['clean_urls']) {
            $decoded = urldecode($_SERVER['REQUEST_URI']);
            $inter = array_intersect(explode('/', $decoded),
                                     explode('/', self::$conf['url']));
            $qs = ltrim(substr($decoded, strlen(implode('/', $inter))),
                            '/');
        } else {
            $qs = !empty($_GET['q']) ? $_GET['q'] : '';
        }

        self::redirect($qs);

        // parse query string
        list($ctrl, $act, $params) = sscanf($qs, '%[^/]/%[^/]/%s');


        // generate controller name
        $ctrl_ns = '\\' . self::$conf['app_namespace'] . '\\Ctrl';
        $ctrl_name = $ctrl_ns . '\\' . ucfirst($ctrl) . 'Ctrl';

        // check presence of controller & action
        if (!method_exists($ctrl_name, $act . 'Act')) {
            // Try to redirect to the default action of the ctrl in question
            $act = 'default';
            $params = '';

            if (!method_exists($ctrl_name, $act . 'Act')) {
                $ctrl = 'default';
            }
        }

        // save
        self::$ctrl = $ctrl;
        self::$act = $act;
        self::$params = $params;
    }


    private static function redirect($qs)
    {
        // apply redirection rules
        $redirects_file = __DIR__ . '/../../../../../conf/redirect.yml';

        if (file_exists($redirects_file)) {
            $current_route = str_replace('/', ':', $qs);

            $patterns = Spyc::YAMLLoad($redirects_file);
            
            foreach ($patterns as $route_pattern => $redirect_pattern) {
                $route_pattern = '/' . $route_pattern . '/';

                if (preg_match($route_pattern, $current_route)) {
                    $redirect_to = str_replace(':', '/', preg_replace($route_pattern, $redirect_pattern, $current_route));
                    header('Location: /' . $redirect_to);
                }
            }
        }
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
        $mod_cls = __NAMESPACE__ . '\\Mod\\' . $mod;

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
        $conf_file = __DIR__ . '/../../../../../conf/conf.php';
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
        $ctrl_ns = '\\' . self::$conf['app_namespace'] . '\\Ctrl';
        $ctrl_name = $ctrl_ns . '\\' . ucfirst(self::$ctrl) . 'Ctrl';
        $act_name = self::$act . 'Act';

        // check auth if enabled
        if (in_array('Auth', self::$mods_enabled) && isset($ctrl_name::$perm)) {
            if (!is_array($ctrl_name::$perm)) {
                $ctrl_name::$perm = array($ctrl_name::$perm);
            }

            if (
                !count(array_intersect($ctrl_name::$perm, Auth::getUserPerms()))
            ) {
                Auth::authError(
                    $ctrl_name::$perm,
                    Auth::getUserPerms(),
                    self::$ctrl,
                    self::$act,
                    self::$params
                );
            }
        }
        
        // pass control to relevant action
        $ctrl_name::$act_name(self::$params);
    }

    
    // Helpers ////////////////////////////////////////////////////////////////
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
    ///////////////////////////////////////////////////////////////////////////
}
