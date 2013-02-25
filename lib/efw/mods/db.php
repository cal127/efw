<?php

namespace EFW;
use \PDO;

class DB {
    public static $pdo;


    public static function init(&$conf) {
        self::$pdo = new PDO(sprintf('%s:host=%s;dbname=%s;charset=%s',
                                     $conf['driver'],
                                     $conf['host'],
                                     $conf['db'],
                                     $conf['charset']),
                             $conf['user'],
                             $conf['pass'],
                             array(PDO::MYSQL_ATTR_INIT_COMMAND
                              => 'SET NAMES ' . $conf['charset']));

        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
}

?>
