<?php

namespace EFW;
use \PDO;

class Auth {
    public static $dependencies = array('session',
                                        'db');
    public static $user;



    public static function init($conf) {
        self::updateUser();
    }

    
    public static function updateUser() {
        if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
            self::$user = array('id' => null,
                                'username' => null,
                                'role' => 0);
            return;
        }

        self::$user = array('id' => $_SESSION['user']['username'],
                            'username' => $_SESSION['user']['username'],
                            'role' => $_SESSION['user']['role']);
    }


    public static function isLogged() { return self::$user['username']; }


    public static function login($username, $pass) {
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

    public static function logout() {
        unset($_SESSION['user']);
        self::updateUser();
    }
}

?>
