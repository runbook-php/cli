<?php declare(strict_types=1);

namespace Wsw\Runbook;

use Psr\Log\LoggerInterface;
use Wsw\Runbook\Contract\OutputContract;
use Wsw\Runbook\Contract\StandardOutputContract;

class Processor
{
    /**
     * @var Builder
     */
    private $runbook;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var StandardOutputContract
     */
    private $stdOut;

    public function __construct(Builder $runbook, StandardOutputContract $stdOut, ?LoggerInterface $logger = null)
    {
        $this->runbook = $runbook;
        $this->stdOut = $stdOut;
        $this->logger = $logger;
    }

    public function process(): void
    {
        $this->log(sprintf('PLAY [%s]', $this->runbook->getName()));
        $this->log($this->runbook->getDescription());
        $ok = 0;
        $failed = 0;
        $skipped=0;
        $allTimeStart = microtime(true);
        
        foreach ($this->runbook->allSteps() as $step) {
            $this->stdOut->print(PHP_EOL);
            $this->log(sprintf('ACTION [%s] - %s', $step->getId(), $step->getDescription()));
            $start = microtime(true);
            $this->runbook->executeStep($step);
            $end = microtime(true);
            $rc = $step->getResult()->getExitCode();
            $errorLog = $step->getResult()->getError();

            switch ($rc) {
                case OutputContract::SUCCESS:
                    $ok++;
                    $resStatus = 'ok: ';
                    break;
                case OutputContract::FAILURE:
                    $failed++;
                    $resStatus = 'error: ';
                    break;
                case OutputContract::SKIPPED:
                    $skipped++;
                    $resStatus = 'skipped: ';
                    break;
            }

            $time = $end - $start;
            $this->log($resStatus . 'time='.number_format($time, 4));
            if (!empty($errorLog)) {
                $this->log($errorLog);
            }

            if ($rc === 1 && $step->getIgnoreErrors() === false) {
                break;
            }
        }

        $allTimeEnd = microtime(true);
        $allTime = $allTimeEnd - $allTimeStart;

        $this->stdOut->print(PHP_EOL);
        $this->log(sprintf('PLAY RECAP [%s]', $this->runbook->getName()));
        $this->log(sprintf('ok=%d    failed=%d    skipped=%d    time=%f', $ok, $failed, $skipped, $allTime));
        $this->stdOut->print(PHP_EOL);
    }

    private function log($message, array $context = [], bool $useLog = true, string $logLevel = 'info'): void
    {
        $this->stdOut->print($message, []);

        if ($this->logger instanceof LoggerInterface && $useLog === true) {
            $this->logger->{$logLevel}($message, $context);
        }
    }
}
