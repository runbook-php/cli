<?php declare(strict_types=1);

namespace Wsw\Runbook;

use Wsw\Runbook\Contract\ResourceReferenceContract;
use Wsw\Runbook\Contract\TaggedParse\TaggedParseContract;
use Wsw\Runbook\Contract\TaggedParse\ResourceReferableContract;

class TaggedParse
{
    private $storage = [];

    private $resourceReference;

    public function __construct(ResourceReferenceContract $resourceReference)
    {
        $this->resourceReference = $resourceReference;
    }

    public function addReference(string $key, $value): self
    {
        $this->resourceReference->add($key, $value);
        return $this;
    }

    public function register(TaggedParseContract $taggedParse): self
    {
        $this->storage[$taggedParse->getName()] = $taggedParse;
        return $this;
    }

    public function has(string $tag): bool
    {
        return isset($this->storage[$tag]);
    }

    public function getInstance(string $tag): TaggedParseContract
    {
        if ($this->has($tag) === false) {
            throw new \RuntimeException('The tag parser "'.$tag.'" was not found or is incompatible with the provided format.');
        }

        if ($this->storage[$tag] instanceof ResourceReferableContract) {
            $this->storage[$tag]->setReferences($this->resourceReference);
        }
        
        return $this->storage[$tag];
    }

    public function hasReference(string $key): bool
    {
        return $this->resourceReference->has($key);
    }

    public function getReference(string $key): bool
    {
        return $this->resourceReference->get($key);
    }
}
