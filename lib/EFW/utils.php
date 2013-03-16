<?php

use EFW\EFW;

function undo_magic_quotes() {
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

function require_sandbox($filename) {
    $f = function() use ($filename) { require $filename; };
    $f();
}

function _url($qs) {
    $u = EFW::$conf['url'];
    $p = EFW::$conf['pretty_urls'];

    if (preg_match('/^.*\.(?:css|js|png|jpg|jpeg|gif)$/', $qs) || $p) {
        return $u . '/' . $qs;
    }

    return $u . '/index.php?q=' . $qs;
};

function autoload($class_name) {
    $ctrl_dir = __DIR__ . '/../../app/ctrl';
    $model_dir = __DIR__ . '/../../app/model';

    $file_name = $class_name . '.php';

    try {
        include_once $ctrl_dir . '/' . $file_name;
    } catch (Exception $e) {
        require_once $model_dir . '/' . $file_name;
    }
}

?>
