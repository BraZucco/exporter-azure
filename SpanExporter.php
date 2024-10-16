<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Azure;

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
use RuntimeException;
use ReflectionClass;
use Throwable;

use OpenTelemetry\Contrib\Azure\AzureVariables as Variables;


/**
 * @psalm-import-type SUPPORTED_CONTENT_TYPES from ProtobufSerializer
 */
final class SpanExporter implements SpanExporterInterface
{
    use UsesSpanConverterTrait;
    use LogsMessagesTrait;

    private TransportInterface $transport;
    private ProtobufSerializer $serializer;

    /**
     * @psalm-param TransportInterface<SUPPORTED_CONTENT_TYPES> $transport
     */
    public function __construct(TransportInterface $transport)
    {
        if (!class_exists('\Google\Protobuf\Api')) {
            throw new RuntimeException('No protobuf implementation found (ext-protobuf or google/protobuf)');
        }
        $this->transport = $transport;
        $this->setSpanConverter($converter ?? new SpanConverter());
        $this->serializer = ProtobufSerializer::forTransport($transport);
    }

    public function export(iterable $batch, ?CancellationInterface $cancellation = null): FutureInterface
    {

        $spans = $this->getSpanConverter()->convert($batch);
        //$this->transport->send(json_encode($spans, JSON_PRETTY_PRINT));
        $payload = json_encode($spans);
        return $this->transport->send($payload)
            ->map(function (?string $payload): bool {
                if ($payload === null) {
                    return true;
                }

                $serviceResponse = new ExportTraceServiceResponse();
                $this->serializer->hydrate($serviceResponse, $payload);

                $partialSuccess = $serviceResponse->getPartialSuccess();
                if ($partialSuccess !== null && $partialSuccess->getRejectedSpans()) {
                    self::logError('Export partial success', [
                        'rejected_spans' => $partialSuccess->getRejectedSpans(),
                        'error_message' => $partialSuccess->getErrorMessage(),
                    ]);

                    return false;
                }
                if ($partialSuccess !== null && $partialSuccess->getErrorMessage()) {
                    self::logWarning('Export success with warnings/suggestions', ['error_message' => $partialSuccess->getErrorMessage()]);
                }

                return true;
            })
            ->catch(static function (Throwable $throwable): bool {
                self::logError('Export failure', ['exception' => $throwable]);
                return false;
            });
    }

    public function export21(iterable $batch, ?CancellationInterface $cancellation = null): FutureInterface
    {
        return $this->transport
            ->send($this->serializer->serialize((new SpanConverter($this->serializer))->convert($batch)), $cancellation)
            ->map(function (?string $payload): bool {
                if ($payload === null) {
                    return true;
                }

                $serviceResponse = new ExportTraceServiceResponse();
                $this->serializer->hydrate($serviceResponse, $payload);

                $partialSuccess = $serviceResponse->getPartialSuccess();
                if ($partialSuccess !== null && $partialSuccess->getRejectedSpans()) {
                    self::logError('Export partial success', [
                        'rejected_spans' => $partialSuccess->getRejectedSpans(),
                        'error_message' => $partialSuccess->getErrorMessage(),
                    ]);

                    return false;
                }
                if ($partialSuccess !== null && $partialSuccess->getErrorMessage()) {
                    self::logWarning('Export success with warnings/suggestions', ['error_message' => $partialSuccess->getErrorMessage()]);
                }

                return true;
            })
            ->catch(static function (Throwable $throwable): bool {
                self::logError('Export failure', ['exception' => $throwable]);
                return false;
            });
    }

    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        return $this->transport->shutdown($cancellation);
    }

    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        return $this->transport->forceFlush($cancellation);
    }
}
