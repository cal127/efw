<?php

class EFW {
    public static $conf, $pdo, $roles, $user;



    public static function boot() {
        self::_parseConf();
        self::_setupErrorHandling();
        self::_setupDB();
        self::_setupSession();
        self::setupUser();
        self::_callLibs();
        self::_route();
    }


    private static function _parseConf() {
        self::$conf = yaml_parse_file(__DIR__ . '/../../conf/conf.yml');
    }


    private static function _setupErrorHandling() {
        ini_set('display_errors', self::$conf['debug']);
        error_reporting(E_ALL);
        set_error_handler(function ($no, $str, $fl, $ln) {
                              throw new ErrorException($str,$no,0,$fl,$ln); });
    }


    private static function _setupDB() {
        extract(self::$conf['db']);

        if (!$driver) { return; }

        self::$pdo = new PDO(sprintf('%s:host=%s;dbname=%s;charset=%s',
                               $driver, $host, $db, $charset),
                            $user,
                            $pass,
                            array(PDO::MYSQL_ATTR_INIT_COMMAND
                              => 'SET NAMES ' . $charset));

        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }


    private static function _setupSession() { session_start(); }


    public static function setupUser() {
        if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
            self::$user = array('username' => null,
                                'role' => 0);
            return;
        }

        self::$user = array('username' => $_SESSION['user']['username'],
                            'role' => $_SESSION['user']['role']);
    }


    private static function _callLibs() { }


    private static function _route() {
        try {
            $q = $_GET['q'] . '/'; // i'm a hack!

            list($ctrl, $act, $param) = explode('/', $q);

            include_once __DIR__ . '/../../app/ctrl/' . $ctrl . '.php';

            $ctrl = ucwords($ctrl) . 'Ctrl';
            $act = $act . 'Act';

            if (isset($ctrl::$auth) && !in_array(self::$user['role'], $ctrl::$auth)){
                exit('Permission denied.');
            }
            
            if (!is_callable(array($ctrl, $act))) { throw new Exception(); }

            $ctrl::$act($param);
        } catch (Exception $e) {
            include_once __DIR__ . '/../../app/ctrl/default.php';
            DefaultCtrl::defaultAct(null);
        }
    }
}

?>
