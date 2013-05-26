<?php

namespace HP\Ctrl;

use \EFW\EFW;
use \EFW\Mod\Auth;
use \EFW\Mod\Tpl;
use \Model;


class DefaultCtrl
{
    public static function defaultAct($params)
    {
        switch ($params) {
            case 'logout':
                Auth::logout();
                header('Location: ' . EFW::url('default'));
                break;

            case 'login':
                if (Auth::login($_POST['username'], $_POST['pass'])) {
                    header('Location: ' . EFW::url('default'));
                } else {
                    $vars['hatali_giris'] = true;
                }

                break;
        }

        echo Tpl::getEngine()->render('default', $vars); // mustache
        // echo Tpl::getEngine()->render('default.html', $vars); // twig
    }
}
