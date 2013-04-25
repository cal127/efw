<?php

namespace EFW\Mod;

use \PDO;
use \Exception;


class Auth
{
    public static $dependencies = array('Session',
                                        'DB');
    private static $user;
    private static $auth_error_callback;



    public static function getUser() { return self::$user; }
    public static function getUserID() { return self::$user['id']; }
    public static function getUsername() { return self::$user['username']; }
    public static function getUserRole() { return self::$user['role']; }


    public static function init(&$conf)
    {
        self::updateUser();

        self::registerAuthErrorCallback(function ($permitted_roles, $user_role,
                                                  $ctrl, $act, $params)
        {
            exit('Permission denied.');
        });
    }


    public static function updateUser()
    {
        if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
            self::$user = array('id' => false,
                                'username' => false,
                                'role' => false);
            return;
        }

        self::$user = array('id' => $_SESSION['user']['id'],
                            'username' => $_SESSION['user']['username'],
                            'role' => $_SESSION['user']['role']);
    }


    public static function registerAuthErrorCallback($callable)
    {
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

        if ($rfa->getNumberOfParameters() != 5) {
            throw new Exception('The callable must have five arguments.');
        }

        self::$auth_error_callback = $callable;
    }


    public static function callAuthErrorCallback($permitted_roles,
                                                 $user_role,
                                                 $ctrl,
                                                 $act,
                                                 $params) {
        call_user_func_array(self::$auth_error_callback, func_get_args());
    }


    public static function isLogged()
    {
        return self::$user['username'];
    }


    public static function login($username, $pass)
    {
        $sql = 'SELECT `id`, `username`, `role` FROM `user` '
          . 'WHERE `username` = ? AND `pass` = SHA1(?);';
        $stmt = DB::$pdo->prepare($sql);
        $stmt->execute(array($username, $pass));

        while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $_SESSION['user'] = array('id' => $user['id'],
                                      'username' => $user['username'],
                                      'role'     => $user['role']);
            self::updateUser();
            return true;
        }

        return false;
    }


    public static function logout()
    {
        unset($_SESSION['user']);
        self::updateUser();
    }
}

?>
