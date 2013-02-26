<?php

function array_map_recursive($fn, $arr) {
    $rarr = array();
    foreach ($arr as $k => $v) {
        $rarr[$k] = is_array($v)
            ? array_map_recursive($fn, $v)
            : $fn($v);
    }
    return $rarr;
}

?>
