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

//class Data
//{
//    public string $baseType;
//    public BaseData $baseData;
//
//    public function __construct(string $baseType, BaseData $baseData)
//    {
//        $this->baseType = $baseType;
//        $this->baseData = $baseData;
//    }
//}

//class DataProperties
//{
//    public int $netPeerPort;
//    public string $dbConnectionString;
//    public string $dbUser;
//    public string $customDimension1;
//    public string $customDimension2;
//    public string $httpClientIp;
//    public string $enduserId;
//    public string $httpTarget;
//    public string $httpErrorName;
//    public string $httpErrorMessage;
//    public string $httpStatusText;
//    public string $httpFlavor;
//    public string $netTransport;
//
//    public function __construct(
//        int $netPeerPort,
//        string $dbConnectionString,
//        string $dbUser,
//        string $customDimension1,
//        string $customDimension2,
//        string $httpClientIp,
//        string $enduserId,
//        string $httpTarget,
//        string $httpErrorName,
//        string $httpErrorMessage,
//        string $httpStatusText,
//        string $httpFlavor,
//        string $netTransport
//    ) {
//        $this->netPeerPort = $netPeerPort;
//        $this->dbConnectionString = $dbConnectionString;
//        $this->dbUser = $dbUser;
//        $this->customDimension1 = $customDimension1;
//        $this->customDimension2 = $customDimension2;
//        $this->httpClientIp = $httpClientIp;
//        $this->enduserId = $enduserId;
//        $this->httpTarget = $httpTarget;
//        $this->httpErrorName = $httpErrorName;
//        $this->httpErrorMessage = $httpErrorMessage;
//        $this->httpStatusText = $httpStatusText;
//        $this->httpFlavor = $httpFlavor;
//        $this->netTransport = $netTransport;
//    }
//}
