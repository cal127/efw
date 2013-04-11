<?php

namespace EFW;

function _url($qs) {
    $u = EFW::$conf['url'];
    $p = EFW::$conf['pretty_urls'];

    if (preg_match('/^.*\.(?:css|js|png|jpg|jpeg|gif)$/', $qs) || $p) {
        return $u . '/' . $qs;
    }

    return $u . '/index.php?q=' . $qs;
};

?>
