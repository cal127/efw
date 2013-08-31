<?php

// This module depends on idiorm & paris orm libraries!!

namespace EFW\Mod;

use \PDO;
use \Exception;
use \Model;


class DataPresenter
{
    public static $dependencies = array('DB', 'Session'); // orm: paris

    private $idiorm_ormwrapper;
    private $fields = array();
    private $date_format = 'd.m.Y';
    private $filters;
    private $order_by;
    private $limit = 20;
    private $current_page;
    private $page_count;
    private $titles;
    private $widgets;
    private $rows;



    public static function init(&$conf) { }


    public static function getAll(
        $idiorm_ormwrapper,
        $fields,
        $current_page = 1,
        $limit = null,
        $order_by = null,
        $filters = null
    )
    {
        $dp = new DataPresenter(
            $idiorm_ormwrapper,
            $fields,
            $current_page,
            $limit,
            $order_by,
            $filters
        );

        return array(
            'rows'         => $dp->getRows(),
            'titles'       => $dp->generateTitles(),
            'widgets'      => $dp->getWidgets(),
            'page_count'   => $dp->getPageCount(),
            'current_page' => $current_page
        );
    }


    public function __construct(
        $idiorm_ormwrapper,
        $fields,
        $current_page = 1,
        $limit = null,
        $order_by = null,
        $filters = null
    )
    {
        if (is_string($idiorm_ormwrapper)) {
            $idiorm_ormwrapper = Model::factory($idiorm_ormwrapper);
        } elseif (!is_a($idiorm_ormwrapper, 'ORMWrapper')) {
            throw new Exception('You must either provide a model name as a'
                . ' string, or an Idiorm ORMWrapper instance.');
        }

        $this->idiorm_ormwrapper = $idiorm_ormwrapper;
        $this->fields = $this->parseFields($fields);
        $this->current_page = $current_page;

        if ($filters) {
            $this->filters = $filters;
            $this->applyFilters();
        }

        if ($order_by) {
            $this->order_by = $order_by;
            $this->applyOrderBy();
        }

        if ($limit) {
            $this->limit = $limit;
        }

        $this->calculatePageCount(); // This has to come before limit

        $this->applyLimit();
    }


    public function getPageCount()
    {
        return $this->page_count;
    }


    public function setDateFormat($dformat) {
        $this->date_format = $dformat;
        return $this;
    }


    private function parseFields($fields)
    {
        return array_map(
            function($field) {
                if (strpos($field, '@') !== false) {
                    $type = 'relation';
                    list($name, $relation) = explode('@', $field);
                    return compact('name', 'type', 'relation');
                }

                return array(
                    'name' => $field,
                    'type' => substr($field, -4) == 'date' ? 'date' : 'string'
                );
            },
            $fields
        );
    }


    private function applyFilters()
    {
        foreach ($this->filters as $f) {
            $this->idiorm_ormwrapper = $this->idiorm_ormwrapper
                ->where($f[0], $f[1]);
        }
    }


    private function applyOrderBy()
    {
        if (!is_array($this->order_by)) {
            $this->order_by = array($this->order_by);
        }

        foreach ($this->order_by as $o) {
            if (substr($o, 0, 1) == '-') {
                $direction = 'desc';
                $o = substr($o, 1);
            } else {
                $direction = 'asc';
            }

            $this->idiorm_ormwrapper = $this->idiorm_ormwrapper    
                ->{'order_by_' . $direction}($o);
        }
    }


    private function applyLimit()
    {
        $offset = ($this->current_page - 1) * $this->limit; 

        $this->idiorm_ormwrapper = $this ->idiorm_ormwrapper
            ->limit($this->limit)
            ->offset($offset);
    }


    private function calculatePageCount()
    {
        $original = $this->idiorm_ormwrapper;
        $copy = clone $original;
        $this->page_count = ceil($copy->count() / $this->limit);
    }


    public function getRows()
    {
        if ($this->rows) {
            return $this->rows;
        }

        $rows = array();
        $counter = 0;

        foreach ($this->idiorm_ormwrapper->find_many() as $row) {
            $rows[++$counter] = array();

            foreach ($this->fields as $field) {
                switch ($field['type']) {
                    case 'string':
                        $val = (string) $row->$field['name']; 
                        break;

                    case 'date':
                        $val = date($this->date_format, $row->$field['name']);
                        break;

                    case 'relation':
                        list($related_obj, $related_field)
                            = explode('.', $field['relation']);

                        $val = $row->$related_obj()->$related_field;
                        break;
                }

                $rows[$counter][] = $val;
            }
        }

        return $rows;
    }


    public function generateTitles()
    {
        if ($this->titles) {
            return $this->titles;
        }

        return $this->titles = array_map(
            function($f) {
                $name = $f['type'] == 'relation' ? $f['relation'] : $f['name'];
                return ucwords(str_replace(array('_', '.'), ' ', $name));
            },
            $this->fields
        );
    }


    public function getWidgets()
    {
        if ($this->widgets) {
            return $this->widgets;
        }

        $map = array('string' => 'input', 'date' => 'input_date');

        $widgets = array();

        foreach ($this->fields as $field) {
            if ($field['type'] != 'relation') {
                $widgets[$field['name']] = $map[$field['type']];
                continue;
            }

            list($rel_obj, $rel_field) = explode('.', $field['relation']);
            $items = Model::factory(ucwords($rel_obj))
                ->select_many('id', $rel_field)
                ->find_array();
            $widgets[$field['name']] = $items;
        }

        return $widgets;
    }
}
