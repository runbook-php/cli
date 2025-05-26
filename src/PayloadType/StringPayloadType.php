<?php declare(strict_types=1);

namespace Wsw\Runbook\PayloadType;

class StringPayloadType implements PayloadTypeInterface
{
    private $data;
    
    public function setData(string $data): PayloadTypeInterface
    {
        $this->data = $data;
        return $this;
    }

    public static function getName(): string
    {
        return 'string';
    }

    public function getRaw(): string
    {
        return $this->data;
    }
}
