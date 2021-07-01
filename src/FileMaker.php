<?php

namespace Privateer\FileMaker;

use Privateer\FileMaker\Drivers\FMPHP;
use Privateer\FileMaker\Drivers\FMREST;
use Privateer\FileMaker\Exceptions\FileMakerConnectionException;

class FileMaker
{
    private $driver;

    private $config = [];

    /**
     * FileMaker constructor.
     *
     * @param array $config
     *
     * @throws \Privateer\FileMaker\Exceptions\FileMakerConnectionException
     */
    public function __construct($config = [])
    {
        $this->resetConnection();

        if( ! empty($config))
        {
            $this->connection($config);
        }
    }

    /**
     * @return $this
     */
    public function resetConnection()
    {
        $this->config = [
            'driver'    => '',
            'host'      => '',
            'file'      => '',
            'user'      => '',
            'password'  => '',
            'verify_ssl'    => true,
        ];

        return $this;
    }

    /**
     * @param $config
     *
     * @return \Privateer\FileMaker\FileMaker
     * @throws \Privateer\FileMaker\Exceptions\FileMakerConnectionException
     */
    public function connection($config)
    {
        if( ! is_array($config))
        {
            throw new FileMakerConnectionException('Unable to load connection');
        }

        $this->config = array_merge($this->config, $config);

        if(empty($this->config['driver']))
        {
            // Throw an exception - no driver set
            throw new FileMakerConnectionException('No connection driver set');
        }

        // Boot up a driver instance
        switch( strtolower($this->config['driver']) )
        {
            case 'fmphp':
                $this->bootFMPHPDriver();
                break;

            case 'fmrest':
                $this->bootFMRESTDriver();
                break;

            default:
                // Throw an exception - unknown or blank driver
                throw new FileMakerConnectionException('Unknown connection driver');
                break;
        }

        return $this;
    }

    /**
     *
     */
    private function bootFMPHPDriver()
    {
        // Boot up a new instance of FMPHP and assign to $this->driver
        $this->driver = new FMPHP();

        $this->driver->setConnection($this->config);
    }

    /**
     *
     */
    private function bootFMRESTDriver()
    {
        // Boot up a new instance of DataAPI and assign to $this->driver
        $this->driver = new FMREST();

        $this->driver->setConnection($this->config);
    }

    /**
     * Magic method to call all methods on the underlying $driver
     *
     * @param $method
     * @param $parameters
     *
     * @return mixed|null
     */
    public function __call($method, $parameters)
    {
        if(method_exists($this->driver, $method))
        {
            return call_user_func_array([$this->driver, $method], $parameters);
        }

        return null;
    }
}
