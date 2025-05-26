<?php declare(strict_types=1);

namespace Wsw\Runbook;

use Wsw\Runbook\Contract\OutputContract;

class Output implements OutputContract
{
    private $storage = [];

    private $actionExitCode = 0;

    private $actionErrorLog = null;
    
    public function addProperty(string $key, $values): void
    {
        $this->storage[$key] = $values;
        return;
    }

    public function getProperty(string $key)
    {
        return isset($this->storage[$key]) 
            ? $this->storage[$key]
            : null; 
    }

    public function allProperties(): array
    {
        return $this->storage;
    }

    public function success(): void
    {
        $this->actionExitCode = 0;
    }

    public function failure(?string $errorlog = null): void
    {
        $this->actionExitCode = 1;
        $this->actionErrorLog = $errorlog;
    }

    public function skip(): void
    {
        $this->actionExitCode = 2;
    }

    public function getExitCode(): int
    {
        return (int) $this->actionExitCode;
    }

    public function getError(): ?string
    {
        return $this->actionErrorLog;
    }
}
