<?php

namespace Privateer\FileMaker;


class FileMakerRecord
{
    private $recordId;

    public function getField($field)
    {
        if(isset($this->{$field})) return $this->{$field};

        return null;
    }

    public function setRecordId($recordId)
    {
        $this->recordId = $recordId;
    }

    public function getRecordId($record)
    {
        return $this->recordId;
    }
}