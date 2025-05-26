<?php declare(strict_types=1);

namespace Wsw\Runbook;
class Vars
{
    private $storage = [];

    public function add(string $key, $value): self
    {
        $this->storage[$key] = $value;
        return $this;
    }

    public function get(string $key)
    {
        return $this->storage[$key];
    }
}
