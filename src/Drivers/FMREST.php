<?php

namespace Privateer\FileMaker\Drivers;


use Privateer\FileMaker\Exceptions\FileMakerConnectionException;
use GuzzleHttp\Client;
use Privateer\FileMaker\FileMakerRecord;

class FMREST implements FileMakerDriver
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

    private $query = [];

    private $token;

    public function setConnection($connection = null)
    {
        // Just assign to a local property for now - no need to boot up
        // the connection until we're ready to make a database request
        $this->connection = $connection;
    }

    public function layout($layout)
    {
        $this->layout = $layout;

        return $this;
    }

    public function table($table)
    {
        return $this->layout($table);
    }

    public function get()
    {
        $results = $this->formatResults($this->executeQuery());

        return $this->randomOrder ? $results->shuffle() : $results;
    }

    public function byRecordId($recordId)
    {
        return $this->formatResults(
            $this->wrapWithJsonStructure(
                $this->getRecordById($recordId)
            )
        )->first();
    }

    public function delete()
    {
        $records = $this->executeQuery();

        // are there any records?

        foreach($records->response->data as $index => $record)
        {
            $result = $this->deleteRecord($record);  // should this be by record ID?
        }

        return $result;
    }

    private function deleteRecord($record) // should this be by record ID?
    {
        $this->initQuery();

        $recordId = $record->recordId;

        try
        {
            $client = new Client(['base_uri' => $this->connectionURL()]);

            $response = $client->request('DELETE', 'layouts/' . $this->layout . '/records/' . $recordId,
                [
                    'headers'   => [
                        'Content-Type'  => 'application/json',
                        'Authorization' => 'Bearer ' . $this->token,
                    ],

                    // needed for self-signed certificates
                    'verify' => (isset($this->connection['verify_ssl'])) ? $this->connection['verify_ssl'] : true,
                ]);

            $payload = json_decode( $response->getBody() );

            // Check success of request

            // If all good...
            return true;

        } catch ( \Exception $e)
        {
            // empty result - return collection
            // otherwise throw an exception
            dd($e->getMessage());

            return;
        }
    }

    private function wrapWithJsonStructure($record)
    {
        $payload = new \stdClass();
        $payload->response = new \stdClass();

        if( ! is_array($record))
        {
            $record = [$record];
        }

        $payload->response->data = $record;

        return $payload;
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


    public function orderByAscend($field)
    {
        return $this->orderBy($field, 'asc');
    }

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
     * @return string
     */
    private function getRecordClass()
    {
        return FileMakerRecord::class;
    }

    private function formatResults($results, $pluck = null)
    {
        $class = $this->getRecordClass();

        $return = [];

        foreach($results->response->data as $result)
        {
            $row = new $class();

            foreach($result->fieldData as $key => $value)
            {
                if( empty($pluck) || $key == $pluck) {
                    $row->$key = $value;
                }
            }

            if( method_exists($row, 'setRecordId'))
            {
                $row->setRecordId(
                    $result->recordId
                );
            }

            $return[] = $row;
        }

        return new \Illuminate\Support\Collection($return);
    }

    // Trait?
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

    private function executeQuery()
    {
        // are there any criteria?
        // if not we need a different request...
        if(empty($this->where) && empty($this->whereNot) && empty($this->whereIn))
        {
            return $this->executeRecordsQuery();
        } else {
            return $this->executeFindQuery();
        }
    }

    private function executeRecordsQuery()
    {
        $this->initQuery();

        try
        {
            // Build query for skip, take and sort order


            $client = new Client(['base_uri' => $this->connectionURL()]);

            $response = $client->request('GET', 'layouts/' . $this->layout . '/records',
                [
                    'headers'   => [
                        'Content-Type'  => 'application/json',
                        'Authorization' => 'Bearer ' . $this->token,
                    ],

                    // needed for self-signed certificates
                    'verify' => (isset($this->connection['verify_ssl'])) ? $this->connection['verify_ssl'] : true,
                ]);

            return json_decode( $response->getBody() );
        } catch ( \Exception $e)
        {
            // empty result - return collection
            // otherwise throw an exception
            dd($e->getMessage());
        }
    }

    private function executeFindQuery()
    {
        $this->initQuery();

        try
        {
            $client = new Client(['base_uri' => $this->connectionURL()]);

            $response = $client->request('POST', 'layouts/' . $this->layout . '/_find',
                [
                    'headers'   => [
                        'Content-Type'  => 'application/json',
                        'Authorization' => 'Bearer ' . $this->token,
                    ],
                    'body'  => $this->buildFindQueryBody(),

                    // needed for self-signed certificates
                    'verify' => (isset($this->connection['verify_ssl'])) ? $this->connection['verify_ssl'] : true,
                ]);

            return json_decode( $response->getBody() );
        } catch ( \Exception $e)
        {
            // empty result - return collection
            // otherwise throw an exception
            dd($e->getMessage());

            return;
        }
    }

    public function update($data)
    {
        $records = $this->executeQuery();

        // are there any records?

        foreach($records->response->data as $index => $record)
        {
            $records->response->data[$index] = $this->updateRecord($record, $data);

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
        $this->initQuery();

        $recordId = $record->recordId;

        try
        {
            $client = new Client(['base_uri' => $this->connectionURL()]);

            $response = $client->request('PATCH', 'layouts/' . $this->layout . '/records/' . $recordId,
                [
                    'headers'   => [
                        'Content-Type'  => 'application/json',
                        'Authorization' => 'Bearer ' . $this->token,
                    ],
                    'body'  => json_encode(
                        $this->buildInsertQueryBody($data)
                    ),

                    // needed for self-signed certificates
                    'verify' => (isset($this->connection['verify_ssl'])) ? $this->connection['verify_ssl'] : true,
                ]);

            // if it was a success we need to retrieve the record...

            $payload = json_decode( $response->getBody() );

            // If all good...
            return $this->getRecordById($recordId);

        } catch ( \Exception $e)
        {
            // empty result - return collection
            // otherwise throw an exception
            dd($e->getMessage());

            return;
        }
    }

    private function buildFindQueryBody()
    {
        $parameters = new \stdClass();

        // Build query parameters
        $this->processWhereClauses();
        $this->processWhereInClauses();
        $this->processWhereNotClauses();


        $parameters->query = $this->query;

        // Skip, Take, Ordering
        if( ! empty($this->take))
        {
            $parameters->limit = $this->take;
        }

        // TODO: Take, Order

        return json_encode($parameters);
    }

    private function processWhereClauses()
    {
        if(empty($this->where)) return;

        // where clauses are added to the whereIn compound finds
        if(empty($this->whereIn))
        {
            $criteria = new \stdClass();

            foreach ($this->where as $criterion)
            {
                $criteria->{$criterion['field']} = $this->evaluateEmptyValue($criterion);
            }

            $this->query[] = $criteria;
        }
    }

    private function processWhereInClauses()
    {
        if(empty($this->whereIn)) return;

        foreach($this->whereIn as $criterion)
        {
            foreach($criterion['values'] as $value)
            {
                $criteria = new \stdClass();

                $criteria->{$criterion['field']} = $value;

                // Need to also add all of the global where clauses
                foreach($this->where as $where)
                {
                    $criteria->{$where['field']} = $this->evaluateEmptyValue($where);
                }

                $this->query[] = $criteria;
            }
        }

    }

    private function processWhereNotClauses()
    {
        if(empty($this->whereNot)) return;

        foreach ($this->whereNot as $criterion)
        {
            $criteria = new \stdClass();
            $criteria->{$criterion['field']} = $this->evaluateEmptyValue($criterion);
            $criteria->omit = 'true';
            $this->query[] = $criteria;
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

        $response = new \stdClass();

        $response->response = new \stdClass();
        $response->response->data = $records;

        return $this->formatResults($response);

    }

    private function performOneInsert($data)
    {
        $this->initQuery();

        try
        {
            $client = new Client(['base_uri' => $this->connectionURL()]);

            $response = $client->request('POST', 'layouts/' . $this->layout . '/records',
                [
                    'headers'   => [
                        'Content-Type'  => 'application/json',
                        'Authorization' => 'Bearer ' . $this->token,
                    ],
                    'body'  => json_encode(
                                        $this->buildInsertQueryBody($data)
                                        ),

                    // needed for self-signed certificates
                    'verify' => (isset($this->connection['verify_ssl'])) ? $this->connection['verify_ssl'] : true,
                ]);

            // if it was a success we need to retrieve the record...

            $payload = json_decode( $response->getBody() );

            if(isset($payload->response->recordId))
            {
                return $this->getRecordById($payload->response->recordId);
            }
        } catch ( \Exception $e)
        {
            // empty result - return collection
            // otherwise throw an exception
            dd($e->getMessage());

            return;
        }
    }

    private function buildInsertQueryBody($data)
    {
        $fieldData = new \stdClass();

        foreach($data as $key => $value)
        {
            $fieldData->$key = $value;
        }

        $insert = new \stdClass();

        $insert->fieldData = $fieldData;

        return $insert;
    }

    private function getRecordById($recordId)
    {
        $this->initConnection();

        try
        {
            $client = new Client(['base_uri' => $this->connectionURL()]);

            $response = $client->request('GET', 'layouts/' . $this->layout . '/records/' . $recordId,
                [
                    'headers'   => [
                        'Content-Type'  => 'application/json',
                        'Authorization' => 'Bearer ' . $this->token,
                    ],

                    // needed for self-signed certificates
                    'verify' => (isset($this->connection['verify_ssl'])) ? $this->connection['verify_ssl'] : true,
                ]);

            $payload = json_decode( $response->getBody() );
//            dd($payload);
            return $payload->response->data[0];

        } catch ( \Exception $e)
        {
            // empty result - return collection
            // otherwise throw an exception
            dd($e->getMessage());

            return;
        }
    }

    private function executeInsertQuery()
    {

    }

    private function executeUpdateQuery()
    {

    }

    private function executeDeleteQuery()
    {

    }

    private function initQuery()
    {
        // is the layout set?
        if( empty($this->layout))
        {
            throw new FileMakerConnectionException('Layout not set');
        }

        // This is where the magic happens
        $this->initConnection();
    }

    private function initConnection()
    {
        // Initial connection to get the authorisation token

        // only need to do this once per request cycle
        if( ! is_null($this->token)) return;

        try
        {
            $response = $this->connectionClient()->request('POST', 'sessions',
                [
                    'auth' => [$this->connection['user'], $this->connection['password']],
                    'headers'   => [
                        'Content-Type'  => 'application/json'
                    ],

                    // needed for self-signed certificates
                    'verify' => (isset($this->connection['verify_ssl'])) ? $this->connection['verify_ssl'] : true,
                ]);

            $body = json_decode( $response->getBody() );

            if(isset ($body->response->token))
            {
                // set the token
                $this->token = $body->response->token;
            }
        } catch( \Exception $e)
        {
            // Exceptions, exceptions, exceptions...
            echo $e->getMessage();
        }
    }

    private function connectionClient()
    {
        return new Client(['base_uri' => $this->connectionURL()]);
    }

    private function connectionURL()
    {
        return 'https://' . $this->connection['host'] .
                '/fmi/data/v2/databases/' .
                $this->connection['file'] . '/';
    }

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

    public function value($field)
    {
        $result = $this->first();

        if(isset($result->{$field})) return $result->{$field};

        throw new FileMakerConnectionException('Field \'' . $field . '\' not found');
    }

    public function pluck($field)
    {
        return $this->formatResults($this->executeQuery(), $field)->pluck($field);
    }

    public function sum($field)
    {
        return $this->pluck($field)->sum();
    }

    public function avg($field)
    {
        return $this->pluck($field)->avg();
    }

    public function min($field)
    {
        return $this->pluck($field)->min();
    }

    public function max($field)
    {
        return $this->pluck($field)->max();
    }
}