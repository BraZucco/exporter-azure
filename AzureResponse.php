<?php

namespace OpenTelemetry\Contrib\Azure;
class AzureResponse {
    public int $ver;
    public string $name;
    public string $time;
    public int $sampleRate;
    public string $iKey;
    public \stdClass $tags;
    public AzureData $data;

    public function __construct(
        int $ver,
        string $name,
        string $time,
        int $sampleRate,
        string $iKey,
        \stdClass $tags = new \stdClass(),
        AzureData $data = new AzureData()
    ) {
        $this->ver = $ver;
        $this->name = $name;
        $this->time = $time;
        $this->sampleRate = $sampleRate;
        $this->iKey = $iKey;
        $this->tags = $tags;
        $this->data = $data;
    }

    public function setData(AzureData $data): void {
        $this->data = $data;
    }

    public function addData(string $data, string $value): void {
        $this->data->{$data} = $value;
    }

    public function setTags(array $tags): void {
        foreach ($tags as $key => $value) {
            $this->tags->{$key} = $value;
        }
    }
    public function addTag(string $tag, string $value): void {
        $this->tags->{$tag} = $value;
    }

    public function __toString(): string {
        return json_encode($this, JSON_PRETTY_PRINT);
    }
}
