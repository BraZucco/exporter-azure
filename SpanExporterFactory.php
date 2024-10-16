<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Azure;

use OpenTelemetry\API\Behavior\LogsMessagesTrait;
use OpenTelemetry\API\Signals;
use OpenTelemetry\SDK\Common\Configuration\Configuration;
use OpenTelemetry\SDK\Common\Configuration\Defaults;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Common\Export\Http\PsrTransportFactory;
use OpenTelemetry\SDK\Registry;
use OpenTelemetry\SDK\Trace\SpanExporter\SpanExporterFactoryInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;


use OpenTelemetry\Contrib\Azure\AzureVariables as Variables;

class SpanExporterFactory implements SpanExporterFactoryInterface
{
    use LogsMessagesTrait;

    private ?TransportFactoryInterface $transportFactory;

    private const DEFAULT_COMPRESSION = 'none';

    public function __construct(?TransportFactoryInterface $transportFactory = null)
    {
        $this->transportFactory = $transportFactory;
    }

    /**
     * @psalm-suppress ArgumentTypeCoercion
     */
    public function create(): SpanExporterInterface
    {
        $this->getAzureConfig();
        $transport = $this->buildTransport();
//        $transport = Registry::transportFactory('stream')->create('php://stdout', 'application/json');
//        $endpoint = AZURE_EXPORTER_AZURE_ENDPOINT;
//        $transport = PsrTransportFactory::discover()->create($endpoint, 'application/json');
        return new SpanExporter($transport);
    }

    /**
     * @psalm-suppress ArgumentTypeCoercion
     * @psalm-suppress UndefinedClass
     */
    private function buildTransport(): TransportInterface
    {
        $protocol = $this->getProtocol();
        $contentType = 'application/json';//Protocols::contentType($protocol);
        $endpoint = $this->getEndpoint($protocol);
        $headers = []; // OtlpUtil::getHeaders(Signals::TRACE);
        $compression = $this->getCompression();
        //$transport = PsrTransportFactory::discover()->create($endpoint, 'application/json');
        $factoryClass = Registry::transportFactory($protocol);
        $factory = $this->transportFactory ?: new $factoryClass();
        $timeout = 1;
        $retryDelay = 2;
        $maxRetries = 1;
//        ?string $cacert = null,
//        ?string $cert = null,
//        ?string $key = null
        return PsrTransportFactory::discover()->create($endpoint, 'application/json');
        // return PsrTransportFactory::discover()->create($endpoint, $contentType, $headers, $compression, $timeout, $retryDelay, $maxRetries);
        // return $factory->create($endpoint, $contentType, $headers, $compression, $timeout, $retryDelay, $maxRetries);
    }

    private function getProtocol(): string
    {
        return Configuration::has(Variables::AZURE_EXPORTER_OTLP_TRACES_PROTOCOL) ?
            Configuration::getEnum(Variables::AZURE_EXPORTER_OTLP_TRACES_PROTOCOL) :
            Configuration::getEnum(Variables::AZURE_EXPORTER_OTLP_PROTOCOL);
    }

    private function getEndpoint(string $protocol): string
    {
        if (Configuration::has(Variables::OTEL_EXPORTER_OTLP_TRACES_ENDPOINT)) {
            return Configuration::getString(Variables::OTEL_EXPORTER_OTLP_TRACES_ENDPOINT);
        }
        $endpoint = AZURE_EXPORTER_AZURE_ENDPOINT;
        if ($protocol === Protocols::GRPC) {
            return $endpoint . OtlpUtil::method(Signals::TRACE);
        }

        return HttpEndpointResolver::create()->resolveToString($endpoint, Signals::TRACE);
    }

    private function getCompression(): string
    {
        return Configuration::has(Variables::OTEL_EXPORTER_OTLP_TRACES_COMPRESSION) ?
            Configuration::getEnum(Variables::OTEL_EXPORTER_OTLP_TRACES_COMPRESSION) :
            Configuration::getEnum(Variables::OTEL_EXPORTER_OTLP_COMPRESSION, self::DEFAULT_COMPRESSION);
    }
    private function getAzureConfig(): void
    {
        $config = [];
        $connectionString = Configuration::getString(Variables::APPLICATIONINSIGHTS_CONNECTION_STRING);
        if ($connectionString !== null) {
            $azure_data = [];
            if ($connectionString) {
                $pairs = explode(';', $connectionString);
                foreach ($pairs as $pair) {
                    list($key, $value) = explode('=', $pair, 2);
                    $azure_data[$key] = $value;
                }
            }
        }
        define('AZURE_EXPORTER_AZURE_KEY', $azure_data['InstrumentationKey']);
        define('AZURE_EXPORTER_AZURE_ENDPOINT', $azure_data['IngestionEndpoint']);
    }
}
