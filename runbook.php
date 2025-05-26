<?php declare(strict_types=1);

$dirVendor = (isset($__runBash__) && $__runBash__ === 1)
    ? dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'vendor'
    : __DIR__ . DIRECTORY_SEPARATOR . 'vendor';

require_once $dirVendor . DIRECTORY_SEPARATOR . 'autoload.php';    

use Symfony\Component\Yaml\Yaml;
use Wsw\Runbook\ActionsContainer;
use Wsw\Runbook\Payload;
use Wsw\Runbook\PayloadType\StringPayloadType;
use Wsw\Runbook\PayloadType\JsonPayloadType;
use Wsw\Runbook\Processor;
use Wsw\Runbook\ResourceReference;
use Wsw\Runbook\Builder;
use Wsw\Runbook\Stdout;
use Wsw\Runbook\TaggedParse;
use Wsw\Runbook\Trigger;
use Wsw\Runbook\Vars;

if (php_sapi_name() !== 'cli') {
    echo "Este script deve ser executado via linha de comando." . PHP_EOL;
    exit(1);
}

try {

    if ($argc < 2) {
        exibirAjuda();
        exit(1);
    }

    $command = $argv[1];
    $filename = isset($argv[2]) ? $argv[2] : false;
    $payloadString = isset($argv[3]) ? $argv[3] : false;
    $commandsList = ['run', 'clear-cache', 'help'];

    $filePackages = $dirVendor . DIRECTORY_SEPARATOR .'composer' . DIRECTORY_SEPARATOR . 'installed.json';
    $hashInstalledPackages = hash_file('sha256', $filePackages);
    $dirCache = getHomeDirectory() . DIRECTORY_SEPARATOR . '.runbook';
    $dirProviderActionsCache = $dirCache . DIRECTORY_SEPARATOR . 'provider_actions.json';
    $dirProviderTaggedParseCache = $dirCache . DIRECTORY_SEPARATOR . 'provider_tagged_parse.json';
    $renewCacheActions = renewCache($dirProviderActionsCache, $hashInstalledPackages);
    $renewCacheTaggedParse = renewCache($dirProviderTaggedParseCache, $hashInstalledPackages);
    $providersActions = [];
    $providersTaggedParse = [];

    if (!in_array($command, $commandsList)) {
        echo 'Command "'.$command.'" is not defined.' . PHP_EOL . PHP_EOL;
        exit(1);
    }

    if ($command === 'help') {
        exibirAjuda();
        exit(0);
    }

    if ($command === 'clear-cache') {
        clearCache($dirProviderActionsCache);
        clearCache($dirProviderTaggedParseCache);
        
        echo "Clearing cache providers (Actions): " . $dirProviderActionsCache . PHP_EOL;
        echo "Clearing cache providers (Tagged): " . $dirProviderTaggedParseCache . PHP_EOL;
        echo "All caches cleared." . PHP_EOL;
        exit(0);
    }


    if (file_exists($filename) === false) {
        throw new \RuntimeException('YAML file not found. Please check the file path and try again.');
    }

    if (is_readable($filename) === false) {
        throw new \RuntimeException('Unable to read the YAML file. Please check file permissions.');
    }

    $runbook = Yaml::parseFile($filename, Yaml::PARSE_CUSTOM_TAGS);

    if (isset($runbook['runbook']) === false) {
        throw new \RuntimeException('The file must start with the "runbook" root tag.');
    }

    if (isset($runbook['runbook']['name']) === false || empty($runbook['runbook']['name'])) {
        throw new \RuntimeException('The runbook name is required.');
    }

    if (isset($runbook['runbook']['description']) === false || empty($runbook['runbook']['description'])) {
        throw new \RuntimeException('The runbook description is required.');
    }

    if (isset($runbook['runbook']['trigger']['type']) === false || empty($runbook['runbook']['trigger']['type'])) {
        throw new \RuntimeException('The runbook type in trigger is required.');
    }

    if (isset($runbook['runbook']['trigger']['severity']) === false || empty($runbook['runbook']['trigger']['severity'])) {
        throw new \RuntimeException('The runbook severity in trigger is required.');
    }

    if (isset($runbook['runbook']['payload']['type']) === false || empty($runbook['runbook']['payload']['type'])) {
        throw new \RuntimeException('The runbook type in payload is required.');
    }

    if ($renewCacheActions === true || $renewCacheTaggedParse === true) {
        $installedPackages = json_decode(file_get_contents($filePackages), true);

        foreach ($installedPackages['packages'] as $package) {
            if (isset($package['extra']['runbook']['actions']) && count($package['extra']['runbook']['actions']) > 0) {
                foreach($package['extra']['runbook']['actions'] as $alias => $action) {
                    $providersActions[$alias] = $action;
                }
            }

            if (isset($package['extra']['runbook']['tagged']) && count($package['extra']['runbook']['tagged']) > 0) {
                foreach($package['extra']['runbook']['tagged'] as $tagged) {
                    $providersTaggedParse[] = $tagged;
                }
            }
        }
 
        if (count($providersActions) > 0 || count($providersTaggedParse) > 0) {
            if(!is_dir($dirCache)) {
                @mkdir($dirCache, 0700);
            }

            $cacheProviderAction = ['checksum' => $hashInstalledPackages, 'providers' => $providersActions];
            $cacheProviderTaggedParse = ['checksum' => $hashInstalledPackages, 'providers' => $providersTaggedParse];

            if (file_exists($dirProviderActionsCache)) {
                unlink($dirProviderActionsCache);
            }

            if (file_exists($dirProviderTaggedParseCache)) {
                unlink($dirProviderTaggedParseCache);
            }

            file_put_contents($dirProviderActionsCache, json_encode($cacheProviderAction));
            file_put_contents($dirProviderTaggedParseCache, json_encode($cacheProviderTaggedParse));
        }
    }

    $arrCacheProviderActions = file_exists($dirProviderActionsCache) 
        ? json_decode(file_get_contents($dirProviderActionsCache), true) 
        : [];

    $container = new ActionsContainer;

    if (
        is_array($arrCacheProviderActions) && 
        isset($arrCacheProviderActions['providers']) && 
        is_array($arrCacheProviderActions['providers']) &&
        count($arrCacheProviderActions['providers']) > 0
    ) {
        foreach($arrCacheProviderActions['providers'] as $alias => $actionClass) {
            $container->register($alias, function (ActionsContainer $ac) use ($actionClass) {
                return new $actionClass;
            });
        }
    }

    $trigger = new Trigger($runbook['runbook']['trigger']['type'], $runbook['runbook']['trigger']['severity']);
    
    $payload = new Payload;
    $payload->register(StringPayloadType::class);
    $payload->register(JsonPayloadType::class);

    $tagParse = new TaggedParse(new ResourceReference);
    $arrCachePrividerTaggedParse = file_exists($dirProviderTaggedParseCache) 
        ? json_decode(file_get_contents($dirProviderTaggedParseCache), true) 
        : [];

    if (
        is_array($arrCachePrividerTaggedParse) && 
        isset($arrCachePrividerTaggedParse['providers']) && 
        is_array($arrCachePrividerTaggedParse['providers']) &&
        count($arrCachePrividerTaggedParse['providers']) > 0
    ) {
        foreach($arrCachePrividerTaggedParse['providers'] as $taggedParsenClass) {
            $tagParse->register(new $taggedParsenClass);
        }
    }

    $payloadTypeStr = $runbook['runbook']['payload']['type'];
    $payloadOutputs = $runbook['runbook']['payload']['outputs'] ?? [];
    $payloadInstance = $payload->getInstance($payloadTypeStr, $payloadOutputs);
    $payloadInstance->setData($payloadString);

    $vars = $runbook['runbook']['vars'] ?? [];
    $arrSteps = $runbook['runbook']['steps'] ?? [];

    $structure = new Builder(new Vars, $tagParse, $container);
    $structure->setName($runbook['runbook']['name']);
    $structure->setDescription($runbook['runbook']['description']);
    $structure->setTrigger($trigger);
    $structure->setPayload($payloadInstance);
    $structure->setVars($vars);
    $structure->setSteps($arrSteps);
    
    $process = new Processor($structure, new Stdout);
    $process->process();

} catch (\Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    fwrite(STDERR, $e->getFile() . PHP_EOL);
    fwrite(STDERR, $e->getLine() . PHP_EOL);
    exit(1);
}
