<?php declare(strict_types=1);

namespace Wsw\Runbook;

use Symfony\Component\Yaml\Tag\TaggedValue;

class Step
{
    private $id;
    private $description;
    private $action;
    private $params = [];
    private $outputs = [];

    private $ignoreErrors = false;

    private $dependsOnSuccess = null;

    /**
     * @var Output
     */
    private $result;

    private $when = [];

    public function __construct(
        string $id, 
        string $description, 
        string $action, 
        array $params = [], 
        array $outputs = [], 
        array $when = [], 
        string $ignoreErrors = 'no', 
        ?string $dependsOnSuccess = null
    ) {
        if (in_array($ignoreErrors, ['yes', 'no']) === false) {
            throw new \InvalidArgumentException('The "ignore_errors" parameter to "'.$id.'" action must be either "yes" or "no". Please correct the value to proceed.');
        }

        if (count($when) > 0 && isset($when[1]) && $when[1] instanceof TaggedValue) {
            $arrWhen[] = $when;
        } elseif (
            (count($when) > 0 && isset($when[1]) && is_array($when[1])) ||
            (count($when) > 0 && !isset($when[1]) && $when[0][1] instanceof TaggedValue)
        ) {
            $arrWhen = $when;
        } else {
            $arrWhen = [];
        }

        $this->id = $id;
        $this->description = $description;
        $this->action = $action;
        $this->params = $params;
        $this->outputs = $outputs;
        $this->when = $arrWhen;
        $this->ignoreErrors = $ignoreErrors === 'yes';
        $this->dependsOnSuccess = $dependsOnSuccess;
    }

    public function addParam(string $key, $value): self
    {
        $this->params[$key] = $value;
        return $this;
    }

    public function addOutput(string $key): self
    {
        $this->outputs[] = $key;
        return $this;
    }

    public function setResult(Output $result): self
    {
        $this->result = $result;
        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getOutputs(): array
    {
        return $this->outputs;
    }

    public function getResult(): Output
    {
        return $this->result;
    }

    public function getIgnoreErrors(): bool
    {
        return $this->ignoreErrors;
    }

    public function getWhen(): array
    {
        return $this->when;
    }

    public function hasWhen(): bool
    {
        return count($this->getWhen()) > 0;
    }

    public function getFieldWhen(int $index = 0)
    {
        return $this->when[$index][0];
    }

    public function getOperatorWhen(int $index = 0): TaggedValue
    {
        return $this->when[$index][1];
    }

    public function getDependsOnSuccess(): ?string
    {
        return $this->dependsOnSuccess;
    }

    public function hasDependencyOnSuccess(): bool
    {
        return !is_null($this->dependsOnSuccess);
    }
}
