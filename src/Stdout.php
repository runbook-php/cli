<?php declare(strict_types=1);

namespace Wsw\Runbook;

use Wsw\Runbook\Contract\StandardOutputContract;

class Stdout implements StandardOutputContract
{
    public function print($message, array $context = [])
    {
        $msg = count($context) === 0
            ? $message
            : sprintf($message, ...$context);

        fwrite(STDOUT, $msg . PHP_EOL);
    }
}
