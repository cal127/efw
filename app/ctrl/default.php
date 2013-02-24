<?php

class DefaultCtrl {
    public static function defaultAct($param) {
        switch ($param) {
            case 'logout':
                self::logout();
                print 'Logged out successfully<br /><br />';
                break;

            case 'login':
                print self::login($_POST['username'], $_POST['pass'])
                  ? 'Successfully logged in<br /><br />'
                  : "Error logging in<br /><br />";
                break;
        }

        if (self::is_logged()) {
            printf('Hello %s<br />', EFW::$user['username']);
            print '<a href="/index.php?q=default/default/logout">Logout</a>';
        } else {
            print 'Hello World!<br />';
            print <<<EOT
                <form method="POST" action="/index.php?q=default/default/login">
                    <input type="text" name="username" />
                    <input type="password" name="pass" />
                    <input type="submit" value="Enter" />
                </form>
EOT;
        }
    }


    public static function is_logged() { return EFW::$user['username']; }


    public static function login($username, $pass) {
        $sql = 'SELECT `username`, `role` FROM `user` '
          . 'WHERE `username` = ? AND `pass` = SHA1(?);';
        $stmt = EFW::$pdo->prepare($sql);
        $stmt->execute(array($username, $pass));

        while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $_SESSION['user'] = array('username' => $user['username'],
                                      'role'     => $user['role']);
            EFW::setupUser();
            return true;
        }

        return false;
    }

    public static function logout() {
        unset($_SESSION['user']);
        EFW::setupUser();
    }
}

?>
