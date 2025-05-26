<?php declare(strict_types=1);

namespace Wsw\Runbook;

class Trigger
{
    private $type;
    private $severity;

    private $allowedTypes = ['manual'];
    private $allowedSeverity = ['informational', 'low', 'medium', 'high', 'critical'];

    public function __construct(string $type, string $severity)
    {
        $this->setType($type);
        $this->setSeverity($severity);
    }

    public function setType(string $type): self
    {
        if (in_array(mb_strtolower($type), $this->allowedTypes) === false) {
            throw new \InvalidArgumentException('Unsupported trigger type. Accepted types: ('.implode(', ', $this->allowedTypes).')');
        }

        $this->type = $type;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setSeverity(string $severity): self
    {
        if (in_array(mb_strtolower($severity), $this->allowedSeverity) === false) {
            throw new \InvalidArgumentException('Unsupported trigger severity. Accepted severity: ('.implode(', ', $this->allowedSeverity).')');
        }

        $this->severity = $severity;
        return $this;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }
}
