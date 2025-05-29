<?php declare(strict_types=1);

namespace Wsw\Runbook;

use Symfony\Component\Yaml\Tag\TaggedValue;
use Wsw\Runbook\Contract\OutputContract;
use Wsw\Runbook\PayloadType\PayloadTypeInterface;
use Wsw\Runbook\PayloadType\PayloadMappableInterface;
use Wsw\Runbook\Contract\TaggedParse\ComparisonOperatorContract;
use Wsw\Runbook\TaggedParse;

class Builder
{
    private $name;
    private $description;
    private $trigger;
    private $payload;
    private $vars;
    private $steps = [];

    /**
     * @var TaggedParse
     */
    private $tagParse;

    private $container;

    public function __construct(Vars $vars, TaggedParse $taggedParse, ActionsContainer $container)
    {
        $this->vars = $vars;
        $this->tagParse = $taggedParse;
        $this->container = $container;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        $this->tagParse->addReference('name', $this->getName());
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        $this->tagParse->addReference('description', $this->getDescription());
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setTrigger(Trigger $trigger): self
    {
        $this->trigger = $trigger;
        $this->tagParse->addReference('trigger.type', $trigger->getType());
        $this->tagParse->addReference('trigger.severity', $trigger->getSeverity());
        return $this;
    }

    public function getTrigger(): Trigger
    {
        return $this->trigger;
    }

    public function setPayload(PayloadTypeInterface $payload): self
    {
        $this->payload = $payload;
        $this->tagParse->addReference('payload.type', $payload->getName());
        $this->tagParse->addReference('payload._raw', $payload->getRaw());
        
        if ($payload instanceof PayloadMappableInterface) {
            foreach($payload->allFields() as $item) {
                $this->tagParse->addReference('payload.outputs.' . $item, $payload->findInMap($item));
            }
        }

        return $this;
    }

    public function getPayload(): PayloadTypeInterface
    {
        return $this->payload;
    }

    public function setVars(array $vars): self
    {
        foreach($vars as $key => $value) {
            if (ctype_alpha($key) === false) {
                throw new \InvalidArgumentException('The provided value "'.$key.'" must contain only letters (A–Z, a–z), with no spaces or special characters.');
            }

            if ($value instanceof TaggedValue) {
                $instanceTag = $this->tagParse->getInstance($value->getTag());
                $valueParse = $instanceTag->parse($value->getValue());

            } else {
                $valueParse = $value;
            }

            $this->vars->add($key, $valueParse);
            $this->tagParse->addReference('vars.'.$key, $valueParse);
        }
        return $this;
    }

    public function setSteps(array $steps): self
    {
        foreach($steps as $step) {
            $stepId = $step['id'];
            $stepDescription = $step['description'];
            $stepAction  = $step['action'];
            $stepParams  = $step['params'] ?? [];
            $stepOutputs = $step['outputs'] ?? [];
            $stepWhen    = $step['when'] ?? [];
            $ignoreErrors = isset($step['ignore_errors']) ? mb_strtolower($step['ignore_errors']) : 'no';
            $dependsOnSuccess = isset($step['depends_on_success']) ? $step['depends_on_success'] : null;
    
            $stepEntiry = new Step($stepId, $stepDescription, $stepAction, [], $stepOutputs, $stepWhen, $ignoreErrors, $dependsOnSuccess);
            foreach ($stepParams as $paramKey => $paramValue) {
                $stepEntiry->addParam($paramKey, $paramValue);
            }

            $this->steps[$stepId] = $stepEntiry;
            $this->tagParse->addReference('steps.'.$stepId.'.description', $stepDescription);
            $this->tagParse->addReference('steps.'.$stepId.'.action', $stepAction);
        }
        return $this;
    }

    public function allSteps(): array
    {
        return $this->steps;
    }

    public function getStep(string $key): Step
    {
        if (!isset($this->steps[$key])) {
            throw new \RuntimeException('The step id "'.$key.'" not found.');
        }

        return $this->steps[$key];
    }

    public function executeStep(Step &$step)
    {
        if ($step->hasDependencyOnSuccess()) {
            $dependsSuccess = true;
            $actionDependency = $step->getDependsOnSuccess();
            $keyReferenceDepends = 'steps.' . $actionDependency . '.rc';

            if ($this->tagParse->hasReference($keyReferenceDepends) === false || $this->tagParse->getReference($keyReferenceDepends) !== OutputContract::SUCCESS) {
                $dependsSuccess = false;
            }

            if ($dependsSuccess === false) {
                throw new \RuntimeException('Execution of action "'.$step->getId().'" was halted: it depends on the success of action "'.$actionDependency.'", which did not complete successfully.');
            }
        }

        $instanceAction = $this->container->get($step->getAction());
        $arrParam = [];
        
        foreach($step->getParams() as $keyParam => $paramItem) {
            $arrParam[$keyParam] = $this->resolveParam($paramItem);
        }

        $resultWhen = true;
        if ($step->hasWhen()) {
            $fieldValue = $this->resolveParam($step->getFieldWhen());
            $resultWhen = $this->resolveWhen($step->getOperatorWhen(), $fieldValue);
        }

        if ($resultWhen === true) {
            $output = $instanceAction->execute(new Params($arrParam), new Output);
        } else {
            $output = new Output;
            $output->skip();
        }

        $step->setResult($output);
        $this->tagParse->addReference('steps.'.$step->getId().'.rc', $output->getExitCode());

        if ($output->getExitCode() === OutputContract::FAILURE || $output->getExitCode() === OutputContract::SKIPPED) {
            return;
        }

        $validateKeysOutput = array_diff($step->getOutputs(), array_keys($output->allProperties()));

        if (count($validateKeysOutput) > 0) {
            throw new \InvalidArgumentException('The output properties ("'.implode('", "', $validateKeysOutput).'") must be defined in the action');
        }

        foreach ($step->getOutputs() as $outputPropertie) {
            $this->tagParse->addReference('steps.'.$step->getId().'.outputs.'.$outputPropertie, $output->getProperty($outputPropertie));
        }
    }

    private function resolveParam($param) 
    {
        if ($param instanceof TaggedValue) {
            $instanceTag = $this->tagParse->getInstance($param->getTag());
            $resolveItem = is_array($param->getValue())
                ? $this->resolveParam($param->getValue())
                : $param->getValue();

            return $instanceTag->parse($resolveItem);
        } elseif (is_array($param)) {
            $resolved = [];
            foreach ($param as $key => $value) {
                $resolved[$key] = $this->resolveParam($value);
            }
            return $resolved;
        } else {
            return $param;
        }
    }

    private function resolveWhen(TaggedValue $when, $value): bool 
    {
        $instanceTag = $this->tagParse->getInstance($when->getTag());
        $fieldValue = $when->getValue();
        
        if (is_string($when->getValue()) && preg_match('/^!([A-Za-z0-9_]+)\s+(.*)$/', $when->getValue(), $matches)) {
            $innerTag = $matches[1];
            $innerValue = $matches[2];
            $valueInnerTag = new TaggedValue($innerTag, $innerValue);
            $fieldValue = $this->resolveParam($valueInnerTag);
        }

        if ($instanceTag instanceof ComparisonOperatorContract) {
            $instanceTag->setValue($value);
        }
        return $instanceTag->parse($fieldValue);
    }
}
