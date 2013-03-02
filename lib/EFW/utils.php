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
    $p = EFW::$mods_conf['Tpl']['options']['pretty_urls'];

    if (!preg_match('/^.*\.(?:css|js|png|jpg|jpeg|gif)$/', $qs) || $p) {
        return $u . '/' . $qs;
    }

    return $u . '/index.php?q=' . $qs;
};

?>
