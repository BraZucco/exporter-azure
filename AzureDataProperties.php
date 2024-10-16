<?php
namespace OpenTelemetry\Contrib\Azure;
class AzureDataProperties
{
    public function __construct() {
    }

    public function addProperty(string $property, string $value): void {
        $this->{$property} = $value;
    }

    public function setProperty(array $properties): void {
        foreach ($properties as $key => $value) {
            $this->{$key} = $value;
        }
    }
}
