<?php declare(strict_types=1);

namespace Wsw\Runbook\PayloadType;

interface PayloadMappableInterface
{
    /**
     * @param string $item
     * @throws \InvalidArgumentException
     * @return mixed
     */
    public function findInMap(string $item);

    public function mapFields(array $fields);

    public function allFields(): array;
}
