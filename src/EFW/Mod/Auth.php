<?php

namespace EFW\Mod;

use \PDO;
use \Exception;


class Auth
{
    public static $dependencies = array('Session', 'DB');

    private static $user,
                   $user_id,
                   $user_username,
                   $user_perms;

    // Callback invoked when authentication error
    private static $auth_error_callback;

    // Callback who populates self::$user variable on init & login & logout
    private static $update_user_callback;

    // table names
    public static $user_table = 'user',
                  $user_perm_table = 'user_perm',
                  $perm_table = 'perm';



    public static function getUser()
    {
        return self::$user;
    }


    public static function getUserID()
    {
        return self::$user_id;
    }


    public static function getUsername()
    {
        return self::$user_username;
    }


    public static function getUserPerms()
    {
        return self::$user_perms;
    }


    public static function init(&$conf)
    {
        self::updateUser();

        // Default callback which is called on auth error
        self::setAuthErrorCallback(
            array(__CLASS__, 'defaultAuthErrorCallback')
        );
    }


    public static function setAuthErrorCallback($callable)
    {
        self::callableParameterSanityCheck($callable, 5);
        self::$auth_error_callback = $callable;
    }

    
    public static function setUpdateUserCallback($callable)
    {
        self::callableParameterSanityCheck($callable, 0);
        self::$update_user_callback = $callable;
        self::updateUser();
    }


    private static function defaultAuthErrorCallback(
        $required_perm,
        $user_perms,
        $ctrl,
        $act,
        $params
    )
    {
        exit('Permission denied!');
    }


    public static function updateUser()
    {
        if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
            self::$user_id = self::$user_username = false;
            self::$user_perms = array();
        } else {
            self::$user_id = $_SESSION['user']['id'];
            self::$user_username = $_SESSION['user']['username'];
            self::$user_perms = $_SESSION['user']['perms'];
        }

        if (!self::$update_user_callback) {
            self::$user = array(
                'id' => self::$user_id,
                'username' => self::$user_username,
                'perms' => self::$user_perms
            );
        } else {
            self::$user = call_user_func(self::$update_user_callback);
        }
    }


    public static function authError(
        $required_perm,
        $user_perms,
        $ctrl,
        $act,
        $params
    )
    {
        call_user_func_array(self::$auth_error_callback, func_get_args());
    }


    // Throws an exception if $callable is not a callable
    // or if the callable hasn't got $num_args parameters
    private static function callableParameterSanityCheck(
        $callable,
        $num_args = 0
    )
    {
        if (!is_callable($callable)) {
            throw new Exception('First parameter must be a callable.');
        }

        // test whether it's a method or function/closure
        if (
            is_array($callable) ||
            (is_string($callable) && strpos($callable, '::') !== false)
        ) {
            if (is_string($callable)) {
                $callable = explode('::', $callable);
            }

            $rfa = new \ReflectionMethod($callable[0], $callable[1]);
        } else {
            $rfa = new \ReflectionFunction($callable);
        }

        if ($rfa->getNumberOfParameters() != $num_args) {
            throw new Exception(
                sprintf('The callable must have %d arguments.', $num_args)
            );
        }
    }


    public static function isLogged()
    {
        return self::$user['username'];
    }


    public static function login($username, $pass)
    {
        $sql_tpl = 'SELECT u.id, u.username'
                 . ', GROUP_CONCAT(p.id) AS perms'
                 . ', GROUP_CONCAT(p.title) AS perm_titles'
                 . ' FROM `%s` u'
                 . ' INNER JOIN `%s` up ON u.id = up.user_id'
                 . ' INNER JOIN `%s` p ON p.id = up.perm_id'
                 . ' WHERE u.username = ? AND u.pass = SHA1(?);';

        $sql = sprintf(
            $sql_tpl, self::$user_table, self::$user_perm_table, self::$perm_table
        );

        $stmt = DB::$pdo->prepare($sql);
        $stmt->execute(array($username, $pass));

        while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (is_null($user['id'])) { return false; }

            $_SESSION['user'] = array(
                'id'        => $user['id'],
                'username'  => $user['username'],
                'perms'     => array_combine(
                    explode(',', $user['perm_titles']),
                    explode(',', $user['perms'])
                )
            );

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
