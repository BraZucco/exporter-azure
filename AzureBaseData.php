<?php

namespace OpenTelemetry\Contrib\Azure;
class AzureBaseData {
    public int $ver;
    public string $name;
    public string $id;
    public bool $success;
    public string $resultCode;
    public string $responseCode;
    public string $type;
    public string $duration;
    public string $data;
    public string $target;
    public AzureDataProperties $properties;
    public \stdClass $measurements;

    public function __construct(
        int $ver,
        string $name,
        string $id,
        bool $success,
        string $resultCode,
        string $responseCode,
        string $type,
        string $duration,
        string $data,
        string $target,
        AzureDataProperties $properties,
        \stdClass $measurements
    ) {
        $this->ver = $ver;
        $this->name = $name;
        $this->id = $id;
        $this->success = $success;
        $this->resultCode = $resultCode ?? "0";
        $this->responseCode = $responseCode ?? "0";
        $this->type = $type;
        $this->duration = $duration;
        $this->data = $data;
        $this->target = $target;
        $this->properties = $properties;
        $this->measurements = $measurements;
    }

    public function addData(string $key, string $value) {
        $this->{$key} = $value;
    }

    public function unsetData(string $value) {
        unset($this->{$value});
    }
}
