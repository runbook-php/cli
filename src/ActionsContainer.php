<?php declare(strict_types=1);

namespace Wsw\Runbook;

class ActionsContainer
{
    private $definitions = [];
    private $instances = [];

    public function register(string $id, callable $factory): void
    {
        $this->definitions[$id] = $factory;
    }

    public function get(string $id)
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->definitions[$id])) {
            throw new \RuntimeException("Service action provider '{$id}' not found.");
        }

        $this->instances[$id] = $this->definitions[$id]($this);

        return $this->instances[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->definitions[$id]);
    }
}
