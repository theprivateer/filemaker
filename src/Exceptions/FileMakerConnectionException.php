<?php

namespace Privateer\FileMaker\Exceptions;


use Throwable;

class FileMakerConnectionException extends \Exception
{
    /**
     * FileMakerConnectionException constructor.
     */
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}