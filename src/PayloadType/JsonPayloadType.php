<?php declare(strict_types=1);

namespace Wsw\Runbook\PayloadType;

class JsonPayloadType implements PayloadTypeInterface, PayloadMappableInterface
{
    private $data = [];
    private $fields = [];

    public static function getName(): string
    {
        return 'json';
    }

    public function getRaw(): string
    {
        return json_encode($this->data);
    }

    public function findInMap(string $item)
    {
        if (isset($this->data[$item]) === false) {
            throw new \InvalidArgumentException('The specified field "'.$item.'" was not found in the provided JSON.');
        }

        return $this->data[$item];
    }

    public function mapFields(array $fields)
    {
        $this->fields = $fields;
    }

    public function allFields(): array
    {
        return $this->fields;
    }

    public function setData(string $data): PayloadTypeInterface 
    {
        $json = json_decode($data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('The provided JSON content is not valid. "'.json_last_error().'"');
        }

        $this->data = array_intersect_key($json, array_flip($this->fields));
        return $this;
    }
}
