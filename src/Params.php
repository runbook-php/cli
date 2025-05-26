<?php declare(strict_types=1);

namespace Wsw\Runbook;

use Wsw\Runbook\Contract\ParamsContract;

class Params implements ParamsContract
{
    private $storage = [];

    public function __construct(array $params = [])
    {
        $this->storage = $params;
    }

    public function count(): int
    {
        return count($this->storage);
    }

    public function get(string $key, $default = null)
    {
        return $this->has($key) 
            ?  $this->storage[$key]
            : $default;
    }

    public function has(string $key): bool
    {
        return isset($this->storage[$key]);
    }
}
