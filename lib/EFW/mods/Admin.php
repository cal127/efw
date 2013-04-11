<?php

namespace EFW;

use \Exception;



class Admin
{
    public static $dependencies = array('DB');

    private static $CUD_perms = array();

    private static $op_table = array('create' => 1,
                                     'update' => 2,
                                     'delete' => 4);



    public static function init(&$conf) { }


    /**
     * Usage:
     * Admin::setCUDPerms(array(
     *     1 => array('user' => 1, 'test' => 4),
     *     2 => array('user' => 4, 'test' => 7)));
     *
     * 1 and 2 stands for user role numbers (Enter 0 for anonymous user)
     * 'user' and 'test' stands for table names
     * 1, 3 and 7 stands for permissions bitmask, that is
     *    1 stands for create
     *    4 stands for create + update
     *    7 stands for create + update + delete
     */
    public static function setCUDPerms($perm_table)
    {
        self::$CUD_perms = $perm_table;
    }

    
    public static function askForPermission($role, $table, $operation)
    {
        return 
            array_key_exists($role, self::$CUD_perms)
            && array_key_exists($table, self::$CUD_perms[$role])
            && self::$CUD_perms[$role][$table] & self::$op_table[$operation];
    }


    public static function magicCUD()
    {
        // validate input
        if (empty($_POST['operation']) || empty($_POST['table'])
             || (empty($_POST['props']) && empty($_POST['id'])))
        {
            throw new Exception(__METHOD__ . '(): Bad input.'); 
        }

        $operation = $_POST['operation'];
        $table = $_POST['table'];
        $props = isset($_POST['props']) ? $_POST['props'] : null;
        $id = isset($_POST['id']) ? $_POST['id'] : null;
        $pk_col_name = isset($_POST['pk_col_name']) ? $_POST['pk_col_name'] : 'id';

        return self::CUD($operation, $table, $props, $id, $pk_col_name);
    }


    /**
     * CUD stands for create-update-delete, a diminished version of CRUD
     */
    public static function CUD($operation, $table, $props = null, $id = null,
                               $pk_col_name = 'id')
    {
        // check if current user is permitted to operate on table in question
        $role = (!in_array('Auth', EFW::$mods_loaded) || !Auth::isLogged())
            ? 0 : Auth::getUserRole();

        if (!self::askForPermission($role,
                                    $table,
                                    $operation)) {
            throw new Exception(__METHOD__ . '(): Permission denied.');
        }

        // do it
        switch ($operation)
        {
            case 'create':
                return self::create($table, $props);

            case 'update':
                return self::update($table, $id, $props, $pk_col_name);

            case 'delete':
                return self::delete($table, $id, $pk_col_name);

            default:
                throw new Exception(sprintf('"%s" is not a recognized '
                    . 'operation.', $operation));
        }
    }

    
    private static function create($table, $props)
    {
        $sql = 'INSERT INTO `%s`(%s) VALUES (%s);';

        $fields = '`' . implode('`, `', array_keys($props)) . '`';
        $values = implode(', ', array_fill(0, count($props), '?'));

        // if multiple values entered
        if (is_array($elem = current($props))) {
            $values = implode('), (', array_fill(0, count($elem), $values));

            $params = self::dealWithProps($props);
        } else {
            $params = $props;
        }

        $sql = sprintf($sql, $table, $fields, $values);

        // query
        $stmt = DB::$pdo->prepare($sql);
        return $stmt->execute($params);
    }


    private static function update($table, $id, $props, $pk_col_name = 'id')
    {
        $sql = 'UPDATE `%s` SET %s WHERE `%s` =?';

        $fields = implode(', ',
                          array_map(function ($x) { return "`{$x}` = ?"; },
                                    array_keys($props)));
        
        $sql = sprintf($sql, $table, $fields, $pk_col_name);

        // if multiple values entered
        if (is_array($elem = current($props))) {
            $sql = implode('; ', array_fill(0, count($elem), $sql));

            $params = self::dealWithProps(array_merge($props,
                                                     array('id' => $id)));
        } else {
            $params = array_merge(array_values($props), array($id));
        }

        // query
        $stmt = DB::$pdo->prepare($sql);
        return $stmt->execute($params);
    }


    private static function delete($table, $id, $pk_col_name = 'id')
    {
        $sql = 'DELETE FROM `%s` WHERE `%s` %s;';

        if (is_array($id)) {
            $foo = 'IN (' . implode(', ', array_fill(0, count($id), '?')) . ')';
            $params = $id;
        } else {
            $foo = '= ?';
            $params = array($id);
        }

        $sql = sprintf($sql, $table, $pk_col_name, $foo);

        $stmt = DB::$pdo->prepare($sql);
        return $stmt->execute($params);
    }


    private static function dealWithProps($props)
    {
        $new_props = array();

        foreach ($props as $prop_set) {
            foreach ($prop_set as $i => $prop) {
                $new_props[$i][] = $prop;
            }
        }

        return array_reduce($new_props, function($x, $y) {
            return array_merge($x, $y);
        }, array());
    }
}

?>
