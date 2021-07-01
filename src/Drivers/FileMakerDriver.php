<?php

namespace Privateer\FileMaker\Drivers;


interface FileMakerDriver
{
    public function setConnection($connection = null);

    public function layout($layout);

    public function table($table);

    public function where($field, $operator, $value = null);

    public function whereIn($field, $values = []);

    public function get();

    public function first();

    public function find($value, $primary_key = null);

    public function value($field);

    public function pluck($field);

    public function sum($field);

    public function avg($field);

    public function min($field);

    public function max($field);

    public function take($take);

    public function limit($limit);

    public function skip($skip);

    public function offset($offset);

    public function orderBy($field, $order = 'asc');

    public function orderByAscend($field);

    public function orderByDescend($field);

    public function insert($data);

    public function inRandomOrder();

    public function update($data);

    public function whereNot($field, $value);
}