<?php

// My cool god class. Oh yea

// This module depends on idiorm & paris orm libraries!!
namespace EFW\Mod;

use \Exception;
use \Model;


class CRUD
{
    // EFW module requirement lines ////////////////////////////////////////////
    public static $dependencies = array('DB', 'Session');
    public static function init(&$conf) { }
    ////////////////////////////////////////////////////////////////////////////


    private $orm; // paris ORMWrapper class instance
    private $model_name;
    private $fields;

    /* callbacks
             'pre_save',
             'post_save',
             'pre_read')
     */
    private $callbacks; 

    private $order;
    private $limit;
    private $filters;
    private $page;

    private $page_count; // calculated by limit()
    private $hash;

    // cache
    private $table_titles = array();
    private $widget_titles = array();
    private $widgets = array();

    // custom global callbacks
    private static $global_callbacks = array();


    // getters
    public function getOrder() { return $this->order; }
    public function getLimit() { return $this->limit; }
    public function getFilters() { return $this->filters; }
    public function getPage() { return $this->page; }
    public function getFields() { return $this->fields; }

    public function hash($salt = '')
    {
        return sha1($this->hash . $salt);
    }


    // setters
    public static function addGlobalCallback($type, $callback)
    {
       self::$global_callbacks[$type][] = $callback;
    }


    public function __construct($orm, $fields, $callbacks = array())
    {
        if (is_string($orm)) {
            $orm = Model::factory($orm);
        } elseif (!is_a($orm, 'ORMWrapper')) {
            throw new Exception('You must either provide a model name as a'
                . ' string, or an Idiorm ORMWrapper instance.');
        }

        $this->orm = $orm;
        $this->fields = self::parseFields($fields);
        $this->hash = sha1(serialize($this->orm));

        $this->injectInitialCallbacks();


        $this->callbacks = self::arrayMergeByKey(
            $this->callbacks,
            array_map(function($c) { return array($c); }, $callbacks)
        );
    }


    public function getModelName()
    {
        if (isset($this->model_name)) { return $this->model_name; }

        $model_name = $this->orm->get_class_name();
        return substr($model_name, strrpos($model_name, '\\') + 1); // get rid of the namespace. note that this is tight coupling with environmental settings.
    }


    private static function parseFields($fields)
    {
        return array_map(
            function($field) {
                // mod
                $char = substr($field, 0, 1);
                $map = array('(' => 4, '[' => 2);

                if (in_array($char, array_keys($map))) {
                    $field = substr($field, 1);
                    $mod = $map[$char];
                } else {
                    $mod = 6;
                }

                // type
                $char = substr($field, 0, 1);
                $map = array(
                    '@' => 'rel',
                    '!' => 'rel_many',
                    '%' => 'date',
                    '*' => 'file',
                    '#' => 'long_string'
                );

                if (in_array($char, array_keys($map))) {
                    $name = substr($field, 1);
                    $type = $map[$char];

                    if (in_array($type ,array('rel', 'rel_many'))) {
                        list($name, $rel) = explode(':', $name);
                    }
                } else {
                    $name = $field;
                    $type = 'string';
                }

                return compact('name', 'type', 'rel', 'mod');
            },
            $fields
        );
    }


    // This is a utility function for callback concatenation
    private static function arrayMergeByKey()
    {
        return array_reduce(
            func_get_args(),
            function($sum, $item) {
                array_walk(
                    $item,
                    function($v, $k) use (&$sum) {
                        $sum[$k] = array_merge(
                            isset($sum[$k]) ? $sum[$k] : array(),
                            $v
                        );
                    },
                    array()
                );

                return $sum;
            }
        );
    }


    private function injectInitialCallbacks()
    {
        $this->callbacks = self::$global_callbacks;

        $this->injectManyToManyCallbacks();
        $this->injectVirtualFieldsCallback();
    }


    private function injectManyToManyCallbacks()
    {
        foreach ($this->fields as $f) {
            if ($f['type'] != 'rel_many') { continue; }
            list($rel_obj, $rel_field) = explode('.', $f['rel']);

            // calculate target model //////////////////////////////////////////
            $rf = new \ReflectionMethod(
                $this->orm->get_class_name(), $rel_obj
            );
            preg_match('/@inter (\w+)/', $dc = $rf->getDocComment(), $matches);
            $inter_model = $matches[1];

            preg_match('/@base_key (\w+)/', $dc, $matches);
            $base_key_name = $matches[1];

            preg_match('/@rel_key (\w+)/', $dc, $matches);
            $rel_key_name = $matches[1];

            $f_name = $f['name'];

            $this->callbacks['post_save'][]
                = function($obj_id, $props, $fields)
                  use ($f_name, $inter_model, $base_key_name, $rel_key_name) {
                      Model::factory($inter_model)
                        ->where($base_key_name, $obj_id)
                        ->delete_many();

                      foreach ($props[$f_name] as $rel_obj_id) {
                          $inter = Model::factory($inter_model)->create();
                          $inter->$base_key_name = $obj_id;
                          $inter->$rel_key_name = $rel_obj_id;
                          $inter->save();
                      }
                  };
        }
    }


    private function injectVirtualFieldsCallback()
    {
        $this->callbacks['pre_save'][] = function($props, $fields) {
            foreach ($fields as $f) {
                if (in_array($f['type'], array('rel_many', 'file'))) {
                    unset($props[$f['name']]);
                }
            }

            return $props;
        };
    }


    public function filter($filters)
    {
        $filter_map = array(
            '=' => 'where_equal',
            '>' => 'where_gt',
            '<' => 'where_lt',
            '~' => 'where_like'
        );

        foreach ($filters as $f_name => $f_details) {
            if ($f_details[0] == '~') {
                $f_details[1] = '%' . $f_details[1] . '%';
            }

            $this->orm = $this->orm
                ->$filter_map[$f_details[0]]($f_name, $f_details[1]);
        }

        $this->filters = $filters;

        return $this;
    }


    public function order($order)
    {
        foreach ($order as $o) {
            $this->orm = $this->orm->{'order_by_' . $o[1]}($o[0]);
        }

        $this->order = $order;

        return $this;
    }


    public function limit($limit, $page = 1)
    {
        $offset = ($page - 1) * $limit; 

        // calculate page count ////////////////////////////////////////////////
        $copy = clone $this->orm;

        try {
            $this->page_count = ceil($copy->count() / $limit);

            if (!$this->page_count) { throw new Exception(); }
        } catch (Exception $e) {
            $this->page_count = 1;
        }

        ////////////////////////////////////////////////////////////////////////

        $this->orm = $this->orm
            ->limit($limit)
            ->offset($offset);

        $this->limit = $limit;
        $this->page = $page;

        return $this;
    }


    public function getPageCount()
    {
        if (empty($this->page_count)) {
            throw new Exception(__CLASS__ . '::limit() needs to be called '
                . ' in order to calculate page count.');
        }

        return $this->page_count;
    }


    public function getRows($no_rel = false)
    {
        try {
            $orm_rows = $this->orm->find_many();
        } catch (Exception $e) {
            return;
        }

        $rows = array();

        foreach ($orm_rows as $row) {
            foreach ($this->fields as $field) {
                if (!($field['mod'] & 4)) { continue; }

                switch ($field['type']) {
                    case 'string':
                    case 'long_string':
                    case 'date':

                        $val = (string) $row->$field['name']; 

                        break;


                    case 'rel':

                        if ($no_rel) {
                            $val = (string) $row->$field['name']; 
                            break;
                        }

                        list($related_obj, $related_field)
                            = explode('.', $field['rel']);

                        try {
                            $val = $row->$related_obj()->$related_field;
                        } catch (Exception $e) {
                            $val = null;
                        }

                        break;


                    case 'rel_many':

                        list($related_obj, $related_field)
                            = explode('.', $field['rel']);

                        if ($no_rel) {
                            $related_field = 'id';
                        }

                        try {
                            $val = implode(
                                ', ',
                                array_map(
                                    function($x) use ($related_field) {
                                        return $x[$related_field];
                                    },
                                    $row->$related_obj()->find_array()
                                )
                            );

                            if ($no_rel) {
                                $val = explode(',', $val);
                            }
                        } catch (Exception $e) {
                            $val = null;
                        }

                        break;


                    case 'file':

                        $val = null;

                        break;
                }

                $rows[$row->id][$field['name']] = $val;
            }

            try {
                foreach ($this->callbacks['pre_read'] as $f) {
                    if (is_callable($f)) {
                        $rows[$row->id] = call_user_func($f, $rows[$row->id], $this->fields);
                    }
                }
            } catch (Exception $e) { }
        }

        return $rows;
    }


    public function getSingleRow()
    {
        $rows = $this->getRows();

        return array_shift($rows);
    }


    public function getSingleNorelRow()
    {
        $rows = $this->getRows(true);

        return array_shift($rows);
    }


    private function getTitles($mod, $readOnly = false)
    {
        return array_values(
            array_map(
                function($f) {
                    $name = $f['type'] == 'rel' ? $f['rel'] : $f['name'];
                    return ucwords(str_replace(array('_', '.'), ' ', $name));
                },
                array_filter(
                    $this->fields,
                    function($f) use ($mod, $readOnly) {
                        return ($mod & $f['mod']) || $readOnly;
                    }
                )
            )
        );
    }


    public function getTableTitles()
    {
        if ($this->table_titles) {
            return $this->table_titles;
        }

        return $this->table_titles = $this->getTitles(4);
    }


    public function getWidgetTitles($getReadOnly = false)
    {
        if ($this->widget_titles) {
            return $this->widget_titles;
        }

        return $this->widget_titles = $this->getTitles(2, $getReadOnly);
    }


    public function getWidgets($getReadOnly = false)
    {
        if ($this->widgets) {
            return $this->widgets;
        }

        $map = array(
            'string'      => 'input',
            'long_string' => 'textarea',
            'date'        => 'input_date',
            'hidden'      => 'hidden',
            'rel'         => 'choice',
            'rel_many'    => 'choice_multiple',
            'file'        => 'file'
        );

        $widgets = array();

        foreach ($this->fields as $field) {
            if (!($field['mod'] & 2)) {
                if ($getReadOnly) {
                    $widgets[$field['name']] = 'disabled';
                }

                continue;
            }

            if (!in_array($field['type'], array('rel', 'rel_many'))) {
                $widgets[$field['name']] = $map[$field['type']];
                continue;
            }

            list($rel_obj, $rel_field) = explode('.', $field['rel']);

            // calculate target model //////////////////////////////////////////
            $rf = new \ReflectionMethod(
                $this->orm->get_class_name(), $rel_obj
            );
            preg_match('/@model (\w+)/', $dc = $rf->getDocComment(), $matches);
            $model_name = $matches[1];
            
            // calculate filters ///////////////////////////////////////////////
            if (preg_match('/@filter ([\w=&,]+)/', $dc, $matches)) {
                $filters = explode('&', $matches[1]);
            } else {
                $filters = array();
            }
            ////////////////////////////////////////////////////////////////////

            $model = Model::factory($model_name);

            foreach ($filters as $f) {
                list($f_name, $f_arg) = explode('=', $f);

                if (strpos($f_arg, ',') !== false) {
                    $f_arg = explode(',', $f_arg);
                }

                $model = $model->filter($f_name, $f_arg);
            }

            try {
                $items = $model
                    ->select_many(
                        array('value' => 'id'), array('text' => $rel_field)
                    )
                    ->find_array();
            } catch (Exception $e) {
                $items = array();
            }

            $widgets[$field['name']] = array($map[$field['type']], $items);
        }

        return $this->widgets = $widgets;
    }


    public function getFieldNames()
    {
        return array_map(
            function($f) { return $f['name']; },
            array_filter(
                $this->fields,
                function($f) { return $f['type'] != 'hidden'; }
            )
        );
    }


    public function create($props)
    {
        $processed_props = $this->applyCallbacksChain(
            'pre_save', $props, $this->fields
        );

        $new_item = Model::factory($this->getModelName())->create();
        $new_item->set($processed_props);
        $new_item->save();

        $this->applyCallbacks(
            'post_save', $new_item->id, $props, $this->fields
        );

        return $new_item->id;
    }


    public function update($id, $props)
    {
        $processed_props = $this->applyCallbacksChain(
            'pre_save', $props, $this->fields
        );

        $item = Model::factory($this->getModelName())->find_one($id);
        $item->set($processed_props);
        $item->save();

        $this->applyCallbacks(
            'post_save', $item->id, $props, $this->fields
        );
    }


    public function delete($id)
    {
        Model::factory($this->getModelName())->find_one($id)->delete();

        $this->applyCallbacks('post_delete', $id);
    }


    private function applyCallbacks($callback_type)
    {
        $callback_args = array_slice(func_get_args(), 1);

        try {
            foreach ($this->callbacks[$callback_type] as $f) {
                if (is_callable($f)) {
                    $arg = call_user_func_array($f, $callback_args);
                }
            }
        } catch (Exception $e) { }
    }


    private function applyCallbacksChain($callback_type, $chain)
    {

        $additional_args = array_slice(func_get_args(), 2);

        try {
            foreach ($this->callbacks[$callback_type] as $f) {
                if (is_callable($f)) {
                    $chain = call_user_func_array(
                        $f,
                        array_merge(array($chain), $additional_args)
                    );
                }
            }
        } catch (Exception $e) { }

        return $chain;
    }


    public static function listPage(
        $orm,
        $page,
        $fields,
        $url,
        $details_url,
        $add_new = true,
        $callbacks = array()
    )
    {
        $dp = new self($orm, $fields, $callbacks);

        // current page patch:
        $_SESSION['dp'][$dp->hash($url)]['page'] = $page;

        self::orderLimitFilter($url);
        self::updateOrderLimitFilterFromSess($dp, $url);
        self::createUpdateDelete($dp, $url);

        return array(
            'rows'          => $dp->getRows(),
            'table_titles'  => $dp->getTableTitles(),
            'widget_titles' => $dp->getWidgetTitles(),
            'widgets'       => $dp->getWidgets(),
            'field_names'   => $dp->getFieldNames(),
            'page_count'    => $dp->getPageCount(),
            'filters'       => $dp->getFilters(),
            'order'         => $dp->getOrder(),
            'limit'         => $dp->getLimit(),
            'page'          => $dp->getPage(),
            'hash'          => $dp->hash($url),
            'add_new'       => $add_new,
            'url'           => $url,
            'details_url'   => $details_url
        );
    }


    public static function detailPage(
        $orm, $id, $fields, $relations,
        $url, $parent_url, $callbacks = array()
    )
    {
        $obj_dp = new self($orm, $fields, $callbacks);
        $obj_dp->filter(array('id' => array('=', $id)));

        $rel_dps = array();

        foreach ($relations as $rel) {
            $callbacks      = isset($rel[5]) ? $rel[5] : array();
            $filter_by      = isset($rel[3])
                            ? $rel[3]
                            : strtolower($obj_dp->getModelName()) . '_id';

            $dp = new self($rel[0], $rel[1], $callbacks);


            $rel_dps[] = $dp->filter(array($filter_by => array('=', $id)));
        }

        self::orderLimitFilter($url);
        self::updateOrderLimitFilterFromSess($rel_dps, $url);
        self::createUpdateDelete(
            array_merge(array($obj_dp), $rel_dps), $url
        );

        $object = array(
            'widgetable_fields' => $obj_dp->getSingleNorelRow(),
            'fields'            => $obj_dp->getSingleRow(),
            'table_titles'      => $obj_dp->getTableTitles(),
            'widget_titles'     => $obj_dp->getWidgetTitles(1),
            'widgets'           => $obj_dp->getWidgets(1),
            'field_names'       => $obj_dp->getFieldNames(),
            'hash'              => $obj_dp->hash($url),
            'url'               => $url,
            'id'                => $id
        );

        $rels = array();

        for ($i = 0; $i < count($relations); ++$i) {
            $filter_by = isset($relations[$i][3])
                       ? $relations[$i][3]
                       : strtolower($obj_dp->getModelName()) . '_id';

            $dp = $rel_dps[$i];

            $rels[$relations[$i][0]] = array(
                'rows'          => $dp->getRows(),
                'table_titles'  => $dp->getTableTitles(),
                'widget_titles' => $dp->getWidgetTitles(),
                'widgets'       => $dp->getWidgets(),
                'field_names'   => $dp->getFieldNames(),
                'order'         => $dp->getOrder(),
                'filters'       => $dp->getFilters(),
                'hash'          => $dp->hash($url),
                'details_url'   => $relations[$i][2],
                'add_new'       => $relations[$i][4],
                'filter_by'     => $filter_by
            );
        }

        return array('object' => $object, 'relations' => $rels);
    }


    public static function updateOrderLimitFilterFromSess($dps, $url)
    {
        if (!is_array($dps)) { $dps = array($dps); }

        array_walk(
            $dps,
            function($dp) use ($url) {
                $hash = $dp->hash($url);

                if (isset($_SESSION['dp'][$hash])) {
                    extract($_SESSION['dp'][$hash], EXTR_PREFIX_ALL, 'dp');
                }

                $limit = isset($dp_limit) ? $dp_limit : 20;
                $order = isset($dp_order) ? $dp_order : array();
                $filters = isset($dp_filters) ? $dp_filters : array();
                $page = isset($dp_page) ? $dp_page : 1;

                $dp->filter($filters)->limit($limit, $page)->order($order);
            }
        );
    }


    public static function createUpdateDelete($dps, $url)
    {
        $tasks = array('dp_create', 'dp_update', 'dp_delete');

        if (!isset($_POST['task']) || !in_array($_POST['task'], $tasks)) {
            return;
        }
            
        if (!is_array($dps)) { $dps = array($dps); }

        foreach ($dps as $dp) {
            if ($_POST['dp_hash'] == $dp->hash($url)) {
                switch ($_POST['task']) {
                    case 'dp_create':
                        $dp->create($_POST['props']);
                        header('location: ' . $url);
                        exit;

                    case 'dp_update':
                        $dp->update($_POST['id'], $_POST['props']);
                        header('location: ' . $url);
                        exit;

                    case 'dp_delete':
                        $dp->delete($_POST['id']);
                        header('location: ' . $url);
                        exit;
                }
            }
        }
    }


    public static function orderLimitFilter($redirect_url)
    {
        $tasks = array('dp_orderLimit', 'dp_addFilter',
                       'dp_removeFilter', 'dp_removeAllFilters');

        if (!isset($_POST['task']) || !in_array($_POST['task'], $tasks)) {
            return;
        }

        $method_name = substr($_POST['task'], 3);
        self::$method_name();

        header('location: ' . $redirect_url);
    }


    public static function orderLimit()
    {
        if (!empty($_POST['dp_order_by'])) {
            $_SESSION['dp'][$_POST['dp_hash']]['order'] = array(
                array(
                    $_POST['dp_order_by'],
                    $_POST['dp_order_by_direction']
                )
            );
        }

        if (!empty($_POST['dp_limit'])) {
            $_SESSION['dp'][$_POST['dp_hash']]['limit'] = $_POST['dp_limit']; 
        }
    }


    public static function addFilter()
    {
        $_SESSION['dp']
                 [$_POST['dp_hash']]
                 ['filters']
                 [$_POST['dp_filter_field']]
        = array($_POST['dp_filter_op'], $_POST['dp_filter_value']);
    }


    public static function removeFilter()
    {
        unset($_SESSION['dp']
                       [$_POST['dp_hash']]
                       ['filters']
                       [$_POST['dp_filter_field']]);
    }


    public static function removeAllFilters()
    {
        $_SESSION['dp'][$_POST['dp_hash']]['filters'] = array();
    }
}
