<?php

declare(strict_types=1);
\OpenTelemetry\SDK\Registry::registerSpanExporterFactory('azure', \OpenTelemetry\Contrib\Azure\SpanExporterFactory::class);
\OpenTelemetry\SDK\Registry::registerMetricExporterFactory('azure', \OpenTelemetry\Contrib\Azure\MetricExporterFactory::class);
\OpenTelemetry\SDK\Registry::registerTransportFactory('http', \OpenTelemetry\Contrib\Azure\OtlpHttpTransportFactory::class);
\OpenTelemetry\SDK\Registry::registerLogRecordExporterFactory('azure', \OpenTelemetry\Contrib\Azure\LogsExporterFactory::class);
