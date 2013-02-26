<?php

use EFW\EFW, EFW\Auth, EFW\Tpl;

class DefaultCtrl {
    public static function defaultAct($param) {
        switch ($param) {
            case 'logout':
                Auth::logout();
                $vars['report'] = 'Logged out successfully';
                break;

            case 'login':
                $vars['report'] = Auth::login($_POST['username'], $_POST['pass'])
                  ? 'Successfully logged in'
                  : 'Error logging in';
                break;
            default:
                $vars['report'] = null;
        }

        $vars['is_logged'] = Auth::isLogged();
        $vars['username'] = Auth::$user['username'];
        $vars['url'] = EFW::$conf['url'];

        Tpl::render('default', $vars);
    }
}

?>
