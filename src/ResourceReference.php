<?php declare(strict_types=1);

namespace Wsw\Runbook;

use Wsw\Runbook\Contract\ResourceReferenceContract;

class ResourceReference implements ResourceReferenceContract
{
    private $storage = [];

    public function add(string $key, $value): self
    {
        $this->storage[$key] = $value;
        return $this;
    }

    public function get(string $key) 
    {
        if (!isset($this->storage[$key])) {
            throw new \RuntimeException('The key "'.$key.'" was not found in the available references.');
        }
        
        return $this->storage[$key];
    }

    public function has(string $key): bool
    {
        return isset($this->storage[$key]);
    }
}
