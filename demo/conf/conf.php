<?php

/*
 * conf.php
 * 
 * This is the custom configuration file called during boot
 * 
 * No documentation available, but below you can examine several
 * configurations i've made in my previous projects
 *
 */

// use \EFW\EFW;
// use \EFW\Mod\Tpl;
// use \EFW\Auth;
// use \Model;


// Paris: Set model prefix
// Model::$auto_prefix_models = '\\Demo\\Model\\';

// Mustache: Add global variables to use in templates
// Tpl::mustacheAddHelpers(array(
//     '_ctrl' => EFW::getCtrl(),
//     '_act' => EFW::getAct(),
//     '_params' => EFW::getParams(),
//     '_curURL' => EFW::getURL()
// ));

// Twig: Add global variables to use in templates
// $twig = Tpl::getEngine();
// $twig->addGlobal('_ctrl', EFW::getCtrl());
// $twig->addGlobal('_act', EFW::getAct());
// $twig->addGlobal('_params', EFW::getParams());
// $twig->addGlobal('_curURL', EFW::getURL());


// mod Auth: Define a callback to be called when user tries to access
// unauthorized content
// Auth::setAuthErrorCallback(
//     function($allowed, $user, $ctrl, $act, $params) {
//         if (!$user) {
//             header('Location: ' . EFW::url('default/default'));
//         } else {
//             exit('Bu sayfaya erişme yetkiniz yok.');
//         }
//     }
// );


// mod Auth: Define a callback to be called when a user is logged in.
// It's return value is stored in the Auth class and can be fetched
// via the Auth::getUser() method anytime later
// Auth::setUpdateUserCallback(
//     function() {
//         return Model::factory('User')->find_one(Auth::getUserID());        
//     }
// );
