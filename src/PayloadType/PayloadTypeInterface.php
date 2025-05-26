<?php declare(strict_types=1);

namespace Wsw\Runbook\PayloadType;

interface PayloadTypeInterface
{
    public function setData(string $data): PayloadTypeInterface;
    
    public static function getName(): string;

    public function getRaw(): string;
}
