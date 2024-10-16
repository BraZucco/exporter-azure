<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Azure;

/**
 * Environment variables defined by the OpenTelemetry specification and language specific variables for the PHP SDK.
 * @see https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/configuration/sdk-environment-variables.md
 */
interface AzureVariables extends \OpenTelemetry\SDK\Common\Configuration\Variables
{
    public const AZURE_EXPORTER_AZURE_ENDPOINT = 'AZURE_EXPORTER_AZURE_ENDPOINT';
    public const AZURE_EXPORTER_OTLP_TRACES_PROTOCOL = 'AZURE_EXPORTER_OTLP_TRACES_PROTOCOL';

    public const AZURE_EXPORTER_AZURE_KEY = 'AZURE_EXPORTER_AZURE_KEY';
    public const APPLICATIONINSIGHTS_CONNECTION_STRING = 'APPLICATIONINSIGHTS_CONNECTION_STRING';

    public const AZURE_SAMPLE_RATE = 'AZURE_SAMPLE_RATE';
    public const AZURE_CLOUD_ROLE_NAME = 'AZURE_CLOUD_ROLE_NAME';
}
