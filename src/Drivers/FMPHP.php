<?php

namespace Privateer\FileMaker\Drivers;


use airmoi\FileMaker\FileMaker;
use Privateer\Filemaker\Exceptions\FileMakerConnectionException;
use Privateer\FileMaker\FileMakerRecord;

class FMPHP implements FileMakerDriver
{
    private $connection;

    private $layout;

    private $where = [];

    private $whereNot = [];

    private $whereIn = [];

    private $take;

    private $skip = 0;

    private $order = [];

    private $randomOrder = false;

    private $client;

    private $query;

    private $command;

    private $count = 1;


    const NEW_FIND_COMMAND = 'newFindCommand';
    const NEW_COMPOUND_FIND_COMMAND = 'newCompoundFindCommand';
    const NEW_FIND_REQUEST = 'newFindRequest';

    /**
     * FMPHP constructor.
     */
    public function __construct()
    {
        // May need this later
    }

    /**
     * @param null $connection
     */
    public function setConnection($connection = null)
    {
        // Just assign to a local property for now - no need to boot up
        // the connection until we're ready to make a database request
        $this->connection = $connection;
    }

    /**
     * @param $layout
     *
     * @return $this
     */
    public function layout($layout)
    {
        $this->layout = $layout;

        return $this;
    }

    /**
     * @param $table
     *
     * @return \Databee\Drivers\FMPHP
     */
    public function table($table)
    {
        return $this->layout($table);
    }

    /**
     * @param $field
     * @param $operator
     * @param null $value
     *
     * @return $this
     */
    public function where($field, $operator, $value = null)
    {
        if( is_null($value))
        {
            $value = $operator;
            $operator = '==';
        }

        if($operator == '!=')
        {
            return $this->whereNot($field, $value);
        } else
        {
            $this->where[] = [
                'field'       => $field,
                'operator'    => $operator,
                'value'       => $value,
            ];
        }

        return $this;
    }

    public function whereNot($field, $value)
    {
        $this->whereNot[] = [
            'field'       => $field,
            'value'       => $value,
        ];

        return $this;
    }

    /**
     * @param $field
     * @param array $values
     *
     * @return $this
     */
    public function whereIn($field, $values = [])
    {
        if ( empty($values) ) return $this;

        if( is_string($values) || is_numeric($values)) $values = [$values];

        $this->whereIn[] = [
            'field'     => $field,
            'values'    => $values
        ];

        return $this;
    }

    /**
     *
     */
    private function setCommand()
    {
        if( empty($this->whereNot) && empty($this->whereIn))
        {
            $this->command = self::NEW_FIND_COMMAND;
        } else {
            $this->command = self::NEW_COMPOUND_FIND_COMMAND;
        }
    }

    /**
     * @param $data
     *
     * @return \Illuminate\Support\Collection
     * @throws \Databee\Exceptions\FileMakerConnectionException
     */
    public function insert($data)
    {
        $records = [];

        foreach($data as $item)
        {
            if (is_array($item))
            {
                $records[] = $this->performOneInsert($item);
            } else
            {
                $records[] = $this->performOneInsert($data);
                break;
            }
        }

        return $this->formatResults(
            (new \Illuminate\Support\Collection($records))->flatten()
        );
    }

    /**
     * @param $data
     *
     * @return bool|\Illuminate\Support\Collection
     * @throws \Databee\Exceptions\FileMakerConnectionException
     */
    public function update($data)
    {
        $records = $this->executeQuery();

        // returns a json object
        //if( ! count($records)) return false;

        foreach($records as $index => $record)
        {
            $records[$index] = $this->updateRecord($record, $data);
        }

        return $this->formatResults($records);
    }

    /**
     * @param $record
     * @param $data
     *
     * @return mixed
     */
    private function updateRecord($record, $data)
    {
        foreach($data as $key => $value)
        {
            $record->setField($key, $value);
        }

        $record->commit();

        return $record;
    }

    public function delete()
    {
        $records = $this->executeQuery();

        if( ! count($records)) return false;

        foreach($records as $record)
        {
            try
            {
                $this->client->newDeleteCommand($this->layout, $record->getRecordId())
                            ->execute();
            } catch (\Exception $e)
            {
                return false;
            }
        }

        return true;
    }

    /**
     * @param $data
     *
     * @return array
     * @throws \Privateer\FileMaker\Exceptions\FileMakerConnectionException
     */
    private function performOneInsert($data)
    {
        if( empty($this->layout))
        {
            throw new FileMakerConnectionException('Layout not set');
        }

        // This is where the magic happens
        $this->initConnection();

        $this->query = $this->client->newAddCommand($this->layout);

        foreach($data as $key => $value)
        {
            $this->query->setField($key, $value);
        }

        try
        {
            // Format into a standard object
            return $this->query->execute()->getRecords();
        } catch (\Exception $e)
        {
            // check what the exception code is
            switch($e->getCode())
            {
                case 401:
                    // - if 'no results' then return an empty collection
                    return [];
                    break;

                default:
                    // rethrow an exception
                    throw new FileMakerConnectionException($e->getMessage(), $e->getCode(), $e);
                    break;
            }
        }
    }

    /**
     * @return \Illuminate\Support\Collection
     * @throws \Privateer\FileMaker\Exceptions\FileMakerConnectionException
     */
    public function get()
    {
        $results = $this->formatResults($this->executeQuery());

        return $this->randomOrder ? $results->shuffle() : $results;
    }

    /**
     * @return mixed
     * @throws \Privateer\FileMaker\Exceptions\FileMakerConnectionException
     */
    public function first()
    {
        return $this->get()->first();
    }

    /**
     * @return int
     * @throws \Privateer\FileMaker\Exceptions\FileMakerConnectionException
     */
    public function count()
    {
        return count($this->executeQuery());
    }

    /**
     * @return bool
     * @throws \Privateer\FileMaker\Exceptions\FileMakerConnectionException
     */
    public function exists()
    {
        return (bool) count($this->executeQuery());
    }

    /**
     * @return bool
     * @throws \Privateer\FileMaker\Exceptions\FileMakerConnectionException
     */
    public function doesntExist()
    {
        return ! (bool) count($this->executeQuery());
    }

    /**
     * @param $take
     *
     * @return $this
     */
    public function take($take)
    {
        $this->take = $take;

        return $this;
    }

    /**
     * @param $limit
     *
     * @return \Privateer\FileMaker\Drivers\FMPHP
     */
    public function limit($limit)
    {
        return $this->take($limit);
    }

    /**
     * @param $skip
     *
     * @return $this
     */
    public function skip($skip)
    {
        $this->skip = $skip;

        return $this;
    }

    /**
     * @param $offset
     *
     * @return \Privateer\FileMaker\Drivers\FMPHP
     */
    public function offset($offset)
    {
        return $this->skip($offset);
    }

    /**
     * @param $field
     * @param string $order
     *
     * @return $this
     */
    public function orderBy($field, $order = 'asc')
    {
        $this->order[] = [
            'field' => $field,
            'order' => $order,
        ];

        return $this;
    }

    /**
     * @param $field
     *
     * @return \Privateer\FileMaker\Drivers\FMPHP
     */
    public function orderByAscend($field)
    {
        return $this->orderBy($field, 'asc');
    }

    /**
     * @param $field
     *
     * @return \Privateer\FileMaker\Drivers\FMPHP
     */
    public function orderByDescend($field)
    {
        return $this->orderBy($field, 'desc');
    }

    /**
     * @return $this
     */
    public function inRandomOrder()
    {
        $this->randomOrder = true;

        return $this;
    }

    /**
     * @return $this
     */
    public function reorder()
    {
        $this->order = [];
        $this->randomOrder = false;

        return $this;
    }

    /**
     * @param $value
     * @param null $primary_key
     *
     * @return mixed
     * @throws \Privateer\FileMaker\Exceptions\FileMakerConnectionException
     */
    public function find($value, $primary_key = null)
    {
        // Define the primary key field
        if( is_null($primary_key))
        {
            throw new FileMakerConnectionException('Primary key field not set');
        }

        $this->where($primary_key, $value);

        return $this->first();
    }

    /**
     * @param $field
     *
     * @return mixed
     * @throws \Privateer\FileMaker\Exceptions\FileMakerConnectionException
     */
    public function value($field)
    {
        $result = $this->first();

        if(isset($result->{$field})) return $result->{$field};

        throw new FileMakerConnectionException('Field \'' . $field . '\' not found');
    }

    /**
     * @param $field
     *
     * @return \Illuminate\Support\Collection
     * @throws \Privateer\FileMaker\Exceptions\FileMakerConnectionException
     */
    public function pluck($field)
    {
        return $this->formatResults($this->executeQuery(), $field)->pluck($field);
    }

    /**
     * @param $field
     *
     * @return mixed
     * @throws \Privateer\FileMaker\Exceptions\FileMakerConnectionException
     */
    public function sum($field)
    {
        return $this->pluck($field)->sum();
    }

    /**
     * @param $field
     *
     * @return mixed
     * @throws \Privateer\FileMaker\Exceptions\FileMakerConnectionException
     */
    public function avg($field)
    {
        return $this->pluck($field)->avg();
    }

    /**
     * @param $field
     *
     * @return mixed
     * @throws \Privateer\FileMaker\Exceptions\FileMakerConnectionException
     */
    public function min($field)
    {
        return $this->pluck($field)->min();
    }

    /**
     * @param $field
     *
     * @return mixed
     * @throws \Privateer\FileMaker\Exceptions\FileMakerConnectionException
     */
    public function max($field)
    {
        return $this->pluck($field)->max();
    }

    /**
     *
     */
    private function setOrder()
    {
        if( empty($this->order)) return;

        $cursor = 1;

        foreach($this->order as $rule)
        {
            switch ($rule['order'])
            {
                case 'desc':
                    $this->query->addSortRule($rule['field'], $cursor, FileMaker::SORT_DESCEND);
                    break;

                default:
                    $this->query->addSortRule($rule['field'], $cursor, FileMaker::SORT_ASCEND);
                break;
            }

            $cursor++;
        }
    }

    /**
     * @return array
     * @throws \Privateer\FileMaker\Exceptions\FileMakerConnectionException
     */
    private function executeQuery()
    {
        // is the layout set?
        if( empty($this->layout))
        {
            throw new FileMakerConnectionException('Layout not set');
        }

        // This is where the magic happens
        $this->initConnection();

        $this->setCommand();

        $this->query = $this->client->{$this->command}($this->layout);

        $this->buildQuery();

        if( ! empty($this->take) || ! empty($this->skip))
        {
            $this->query->setRange($this->skip, $this->take);
        }

        $this->setOrder();

        try
        {
            // Format into a standard object
            return $this->query->execute()->getRecords();
        } catch (\Exception $e)
        {
            // check what the exception code is
            switch($e->getCode())
            {
                case 401:
                    // - if 'no results' then return an empty collection
                    return [];
                    break;

                default:
                    // rethrow an exception
                    throw new FileMakerConnectionException($e->getMessage(), $e->getCode(), $e);
                    break;
            }
        }
    }


    /**
     * @return string
     */
    private function getRecordClass()
    {
        return FileMakerRecord::class;
    }

    /**
     * @param $results
     * @param null $pluck
     *
     * @return \Illuminate\Support\Collection
     */
    private function formatResults($results, $pluck = null)
    {
        if( ! count($results)) return new \Illuminate\Support\Collection;

        // Define the class that will be output for each record
        $class = $this->getRecordClass();

        $return = [];

        // Get the fields once initially - should be the same for each row
        $fields = [];

        foreach($results[0]->getFields() as $field)
        {
            if( empty($pluck) || $field == $pluck) {
                $fields[] = $field;
            }
        }

        foreach($results as $result)
        {
            $row = new $class();

            foreach($fields as $field)
            {
                $row->$field = $result->getField($field, false, true);
            }

            if( method_exists($row, 'setRecordId'))
            {
                $row->setRecordId(
                    $result->getRecordId()
                );
            }

            $return[] = $row;
        }

        return new \Illuminate\Support\Collection($return);
    }

    public function byRecordId($recordId)
    {

    }

    /**
     * @throws \Privateer\FileMaker\Exceptions\FileMakerConnectionException
     */
    private function initConnection()
    {
        try
        {
            $this->client = new FileMaker(
                $this->connection['file'],
                $this->connection['host'],
                $this->connection['user'],
                $this->connection['password']
            );
        } catch (\Exception $e)
        {
            // rethrow an exception
            throw new FileMakerConnectionException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     *
     */
    private function buildQuery()
    {
        $this->processWhereClauses();
        $this->processWhereInClauses();
        $this->processWhereNotClauses();

    }

    /**
     *
     */
    private function processWhereClauses()
    {
        if(empty($this->where)) return;

        if($this->command == self::NEW_FIND_COMMAND)
        {
            foreach ($this->where as $criterion)
            {
                $this->query->addFindCriterion(
                    $criterion['field'],
                    $this->evaluateEmptyValue($criterion)
                );
            }
        } elseif($this->command == self::NEW_COMPOUND_FIND_COMMAND && empty($this->whereIn))
        {
            $command = self::NEW_FIND_REQUEST;

            $find = $this->client->$command($this->layout);

            foreach ($this->where as $criterion)
            {
                $find->addFindCriterion(
                    $criterion['field'],
                    $this->evaluateEmptyValue($criterion)
                );
            }

            $this->query->add($this->count, $find);

            $this->count++;
        }
    }

    private function evaluateEmptyValue($criterion)
    {
        if(is_null(trim($criterion['value'])))
        {
            return '=';
        } elseif(isset($criterion['operator']))
        {
            return $criterion['operator'] . $criterion['value'];
        }

        return $criterion['value'];
    }

    /**
     *
     */
    private function processWhereNotClauses()
    {
        if(empty($this->whereNot)) return;

        // Check that the request type is a compound find
        if( $this->command !== self::NEW_COMPOUND_FIND_COMMAND) return;

        foreach ($this->whereNot as $criterion)
        {
            $command = self::NEW_FIND_REQUEST;

            $find = $this->client->$command($this->layout);

            $find->addFindCriterion(
                $criterion['field'],
                $this->evaluateEmptyValue($criterion)
            );

            $find->setOmit(true);

            $this->query->add($this->count, $find);

            $this->count++;
        }
    }

    /**
     *
     */
    private function processWhereInClauses()
    {
        if(empty($this->whereIn)) return;

        // Check that the request type is a compound find
        if( $this->command !== self::NEW_COMPOUND_FIND_COMMAND) return;

        foreach ($this->whereIn as $criterion)
        {
            foreach($criterion['values'] as $value)
            {
                $command = self::NEW_FIND_REQUEST;

                $find = $this->client->$command($this->layout);

                $find->addFindCriterion(
                    $criterion['field'],
                    $value
                );

                // Need to also add all of the global where clauses
                foreach($this->where as $where)
                {
                    $find->addFindCriterion(
                        $where['field'],
                        $this->evaluateEmptyValue($where)
                    );
                }

                $this->query->add($this->count, $find);

                $this->count++;
            }
        }
    }
}