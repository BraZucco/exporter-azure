<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Azure;

use OpenTelemetry\API\Trace as API;
use OpenTelemetry\Contrib\Azure\AzureVariables as Variables;
use OpenTelemetry\SDK\Trace\SpanConverterInterface;
use Opentelemetry\Proto\Common\V1\InstrumentationScope;
use Opentelemetry\Proto\Common\V1\KeyValue;
use Opentelemetry\Proto\Resource\V1\Resource as Resource_;
use Opentelemetry\Proto\Trace\V1\ResourceSpans;
use Opentelemetry\Proto\Trace\V1\ScopeSpans;
use Opentelemetry\Proto\Trace\V1\Span;
use Opentelemetry\Proto\Trace\V1\Span\Event;
use Opentelemetry\Proto\Trace\V1\Span\Link;
use Opentelemetry\Proto\Trace\V1\Status;
use Opentelemetry\Proto\Trace\V1\Status\StatusCode;
use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\SpanDataInterface;

use OpenTelemetry\API\Behavior\LogsMessagesTrait;
use OpenTelemetry\SDK\Trace\Behavior\UsesSpanConverterTrait;

use OpenTelemetry\API\Trace\SpanKind;
use Opentelemetry\Proto\Collector\Trace\V1\ExportTraceServiceResponse;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\CompletedFuture;
use OpenTelemetry\SDK\Common\Future\FutureInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Common\Configuration\Configuration;
use RuntimeException;
use ReflectionClass;
use Throwable;



use OpenTelemetry\Contrib\Azure\AzureResponse;
use function serialize;
use function spl_object_id;

final class SpanConverter implements SpanConverterInterface
{
    private ProtobufSerializer $serializer;

    public const NAME_ATTR = 'name';
    public const TIMESTAMP_ATTR = 'timestamp';
    public const ATTRIBUTES_ATTR = 'attributes';
    public const CONTEXT_ATTR = 'context';

    public function __construct(?ProtobufSerializer $serializer = null)
    {
        $this->serializer = $serializer ?? ProtobufSerializer::getDefault();
    }

    public function convert(iterable $batch): array
    {
        $spans = [];
        foreach ($batch as $span) {
            $pSpan = new AzureResponse(
                1,
                $this->convertName($span->getKind()),
                $this->formatNanoTime($span->getStartEpochNanos()),
                Configuration::getInt(Variables::AZURE_SAMPLE_RATE),
                AZURE_EXPORTER_AZURE_KEY,
                new \stdClass(),
                new AzureData()
            );
            //Configuration::getString(Variables::AZURE_EXPORTER_AZURE_KEY)
            $pDataProperties = new AzureDataProperties();
            $attr = [];
            $attr = array_merge($attr, $this->convertAttributes($span->getAttributes()));
            $attr = array_merge($attr, $this->convertAttributes($span->getResource()->getAttributes()));

            $pDataProperties->setProperty($attr);

            $pData = new AzureData();
            $pData->baseType = $this->convertKind($span->getKind());
//            'duration' => $this->formatNanoTimeDifference($span->getStartEpochNanos(), $span->getEndEpochNanos()),
//            'success' => $span->getAttributes()->get('http.response.status_code') >= 200 && $span->getAttributes()->get('http.response.status_code') < 400,
//            'responseCode' => $span->getAttributes()->get('http.response.status_code'),
            $h = $span->getResource()->getAttributes()->get('telemetry.distro.name');
            //telemetry.sdk.name
            $pData->baseData = new AzureBaseData(
                2,
                $span->getName(),
                $span->getContext()->getSpanId(),
                $span->getAttributes()->get('http.response.status_code') >= 200 && $span->getAttributes()->get('http.response.status_code') < 400,
                (string)$span->getAttributes()->get('http.response.status_code') ?? "0",
                (string)$span->getAttributes()->get('http.response.status_code') ?? "0",
                'http',
                $this->formatNanoTimeDifference($span->getStartEpochNanos(), $span->getEndEpochNanos()),
                '',
                '',
                $pDataProperties,
                new \stdClass(),
            );
            if(!$span->getAttributes()->has('http.response.status_code')) {
                $pData->baseData->unsetData('responseCode');
                $pData->baseData->unsetData('resultCode');
            }

            if($span->getAttributes()->has('db.system')) {
                $pData->baseData->addData('type', $span->getAttributes()->get('db.system'));
            }

            if($span->hasEnded()) {
                $pData->baseData->addData('success', (string)$span->hasEnded());
            }

            if($span->getAttributes()->has('db.statement')) {
                $pData->baseData->addData('data', $span->getAttributes()->get('db.statement'));
            } else {
                $pData->baseData->unsetData('data');
            }

            if($span->getAttributes()->has('url.full')) {
                $pData->baseData->addData('target', $span->getAttributes()->get('url.full'));
            } else {
                $pData->baseData->unsetData('target');
            }

            $pSpan->setData($pData);
            $pSpan->setTags($this->convertTags($span));
            //convertTags
            //$pSpan->addTag('otel.status_code', 'OK');

            $spans[] = $pSpan;
        }
        return $spans;
    }

    private function convertTags(SpanDataInterface $span): array
    {
        return [
            'otel.library.name' => $span->getResource()->getAttributes()->get('telemetry.distro.name'),
            'otel.library.version' => $span->getResource()->getAttributes()->get('telemetry.distro.version'),
            'otel.status_code' => $span->getResource()->getAttributes()->get('telemetry.distro.version'),
            'otel.status_description' => $span->getStatus()->getDescription(),
            'otel.span.kind' => $this->convertKind($span->getKind()),
            'otel.trace_id' => $span->getContext()->getTraceId(),
            'otel.span_id' => $span->getContext()->getSpanId(),
            'otel.parent_span_id' => $span->getParentContext()->getSpanId(),
            'otel.start_time' => $span->getStartEpochNanos(),
            'otel.end_time' => $span->getEndEpochNanos(),
            'otel.attributes' => $this->convertAttributes($span->getAttributes()),
            'otel.events' => $this->convertEvents($span->getEvents()),
            'otel.links' => $this->convertLinks($span->getLinks()),
            'ai.operation.id' => $span->getContext()->getSpanId(),
            'ai.operation.parentId' => $span->getParentContext()->getSpanId(),
            'ai.operation.name' => $span->getName(),
            'ai.operation.syntheticSource' => '',
            'ai.cloud.role' => Configuration::getString(Variables::AZURE_CLOUD_ROLE_NAME),
            'ai.cloud.roleInstance' => $span->getResource()->getAttributes()->get('host.name'),
            'ai.internal.sdkVersion' => $span->getResource()->getAttributes()->get('telemetry.sdk.language') . ':' . $span->getResource()->getAttributes()->get('process.runtime.version'),
            'ai.internal.nodeName' => $span->getResource()->getAttributes()->get('host.name'),
            'ai.internal.agentVersion' => $span->getResource()->getAttributes()->get('process.runtime.version'),
            'ai.internal.sdkLanguage' => $span->getResource()->getAttributes()->get('telemetry.sdk.language'),
//            'ai.internal.monitoringState' => '0',
//            'ai.internal.isAppService' => 'false',
            'ai.internal.sdkInstrumentationName' => $span->getResource()->getAttributes()->get('telemetry.distro.name'),
            'ai.internal.sdkInstrumentationVersion' => $span->getResource()->getAttributes()->get('telemetry.sdk.version')
        ];
    }

    private function convertKind(int $kind): string
    {
        $types = [
            SpanKind::KIND_INTERNAL => 'internal',
            SpanKind::KIND_CLIENT => 'RemoteDependencyData',
            SpanKind::KIND_SERVER => 'RequestData',
            SpanKind::KIND_PRODUCER => 'producer',
            SpanKind::KIND_CONSUMER => 'consumer',
        ];
        $reflection = new ReflectionClass(SpanKind::class);
        $constants = array_flip($reflection->getConstants());

        return $types[$kind] ?? $constants[$kind] ?? 'unknown';
    }

    private function convertName(int $kind): string
    {
        $types = [
            SpanKind::KIND_INTERNAL => 'internal',
            SpanKind::KIND_CLIENT => 'Microsoft.ApplicationInsights.RemoteDependency',
            SpanKind::KIND_SERVER => 'Microsoft.ApplicationInsights.Request',
            SpanKind::KIND_PRODUCER => 'producer',
            SpanKind::KIND_CONSUMER => 'consumer',
        ];
        $reflection = new ReflectionClass(SpanKind::class);
        $constants = array_flip($reflection->getConstants());

        return $types[$kind] ?? $constants[$kind] ?? 'unknown';
    }

    private function formatNanoTime($nanoTime) {
        // Dividir o tempo em segundos e nanossegundos
        $seconds = intval($nanoTime / 1e9); // Parte inteira (segundos)
        $nanoSeconds = $nanoTime % 1e9;     // Parte fracionária (nanossegundos)

        // Converter o tempo para o formato ISO 8601
        $dateTime = new \DateTime("@$seconds"); // "@$seconds" trata como timestamp Unix
        $dateTime->setTimezone(new \DateTimeZone("UTC")); // Definir fuso horário UTC

        // Formatar a data com milissegundos
        $formattedTime = $dateTime->format("Y-m-d\TH:i:s") . sprintf(".%03dZ", $nanoSeconds / 1e6);

        return $formattedTime;
    }

    /**
     * @param \OpenTelemetry\SDK\Common\Attribute\AttributesInterface $attributes
     * @return array
     */
    private function convertAttributes(AttributesInterface $attributes): array
    {
        return $attributes->toArray();
    }

    /**
     * @param array<EventInterface> $events
     * @return array
     */
    private function convertEvents(array $events): array
    {
        $result = [];

        foreach ($events as $event) {
            $result[] = [
                self::NAME_ATTR => $event->getName(),
                self::TIMESTAMP_ATTR => $event->getEpochNanos(),
                self::ATTRIBUTES_ATTR => $this->convertAttributes($event->getAttributes()),
            ];
        }

        return $result;
    }
    /**
     * @param array<LinkInterface> $links
     * @return array
     */
    private function convertLinks(array $links): array
    {
        $result = [];

        foreach ($links as $link) {
            $result[] = [
                self::CONTEXT_ATTR => $this->convertContext($link->getSpanContext()),
                self::ATTRIBUTES_ATTR => $this->convertAttributes($link->getAttributes()),
            ];
        }

        return $result;
    }

    private function formatNanoTimeDifference($startNanoTime, $endNanoTime) {
        // Calcular a diferença entre os dois valores de nanotempo
        $nanoDiff = $endNanoTime - $startNanoTime;

        // Converter a diferença para segundos e nanossegundos
        $seconds = intval($nanoDiff / 1e9);  // Parte inteira (segundos)
        $nanoSeconds = $nanoDiff % 1e9;      // Parte fracionária (nanossegundos)
        $nanoSeconds = str_pad((string)$nanoSeconds, 7, '0', STR_PAD_LEFT); // Preenche com zeros à esquerda se necessário
        $nanoSeconds = substr($nanoSeconds, 0, 7); // Limita a 7 dígitos

        // Converter segundos em horas, minutos e segundos
        $hours = intval($seconds / 3600);
        $minutes = intval(($seconds % 3600) / 60);
        $remainingSeconds = $seconds % 60;

        // Formatar a saída com precisão até os nanossegundos
        $formattedDiff = sprintf("%02d:%02d:%02d.%07d", $hours, $minutes, $remainingSeconds, substr($nanoSeconds, 0, 7));

        return $formattedDiff;
    }
}
