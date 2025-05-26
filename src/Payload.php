<?php declare(strict_types=1);

namespace Wsw\Runbook;

use Wsw\Runbook\PayloadType\PayloadTypeInterface;
use Wsw\Runbook\PayloadType\PayloadMappableInterface;

class Payload
{
    private $strage = [];

    public function register(string $nameclass): self
    {
        if (is_subclass_of($nameclass, PayloadTypeInterface::class) === false) {
            throw new \RuntimeException('The provided class does not implement the expected interface.');
        }

        $name = call_user_func([$nameclass, 'getName']);
        $this->strage[$name] = $nameclass;
        return $this;
        
    }

    public function getInstance(string $type, array $outputs = []): PayloadTypeInterface
    {
        if (isset($this->strage[$type]) === false) {
            throw new \RuntimeException('The payload type has not been registered in the system.');
        }

        if (
            isset($outputs) && 
            count($outputs) > 0 &&
            is_subclass_of($this->strage[$type], PayloadMappableInterface::class) === false 
        ) {
            throw new \RuntimeException('The selected payload type does not support field mapping.');
        }
        
        $class = new $this->strage[$type];
        
        if ($class instanceof PayloadMappableInterface) {
            $class->mapFields($outputs);
        }

        return $class;
    }
}
