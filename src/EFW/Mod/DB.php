<?php

namespace EFW\Mod;

use \PDO;


class DB
{
    public static $pdo;
    private static $conf;



    public static function init(&$conf)
    {
        self::$conf = $conf;
        
        // if an orm library is used, init that, else init PDO
        $callback = 'init' . ($conf['orm'] ? ucfirst($conf['orm']) : 'PDO');
        self::$callback();
    }


    public static function initPDO()
    {
        self::$pdo = new PDO(sprintf('%s:host=%s;dbname=%s;charset=%s',
                                     self::$conf['driver'],
                                     self::$conf['host'],
                                     self::$conf['db'],
                                     self::$conf['charset']),
                             self::$conf['user'],
                             self::$conf['pass'],
                             array(PDO::MYSQL_ATTR_INIT_COMMAND
                              => 'SET NAMES ' . self::$conf['charset']));

        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }


    private static function initIdiorm()
    {
        self::initPDO();

        \ORM::set_db(self::$pdo);

        $foo = !empty(self::$conf['options']['return_result_sets'])
          ? true : false;
        \ORM::configure('return_result_sets', $foo);
    }


    private static function initParis()
    {
        self::initIdiorm();
    }
}
