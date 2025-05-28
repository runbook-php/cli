<?php declare(strict_types=1);
      use Wsw\Runbook\Vault\VaultTaggedParse;

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
use Wsw\Runbook\Vault\AES256CBCEncryption;
use Wsw\Runbook\Vault\Vault;

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
    $commandsList = ['run', 'clear-cache', 'vault', 'help'];

    $filePackages = $dirVendor . DIRECTORY_SEPARATOR .'composer' . DIRECTORY_SEPARATOR . 'installed.json';
    $hashInstalledPackages = hash_file('sha256', $filePackages);
    $dirCache = getHomeDirectory() . DIRECTORY_SEPARATOR . '.runbook';
    $dirSecret = dirname($dirVendor) . DIRECTORY_SEPARATOR . '.secret';
    $dirProviderActionsCache = $dirCache . DIRECTORY_SEPARATOR . 'provider_actions.json';
    $dirProviderTaggedParseCache = $dirCache . DIRECTORY_SEPARATOR . 'provider_tagged_parse.json';
    $renewCacheActions = renewCache($dirProviderActionsCache, $hashInstalledPackages);
    $renewCacheTaggedParse = renewCache($dirProviderTaggedParseCache, $hashInstalledPackages);
    $providersActions = [];
    $providersTaggedParse = [];
    

    $fileSystemConfig = new League\Flysystem\Filesystem(new League\Flysystem\Local\LocalFilesystemAdapter($dirCache));
    $fileSystemSecret = new League\Flysystem\Filesystem(new League\Flysystem\Local\LocalFilesystemAdapter($dirSecret));
    $fileConfigVault = 'vault.json';
    $existVaultKey = false;

    if ($fileSystemConfig->fileExists($fileConfigVault)) {
        $json = $fileSystemConfig->read($fileConfigVault);
        $arrVaultConfig = json_decode($json, true);
        if (isset($arrVaultConfig['key']) && !empty($arrVaultConfig['key'])) {
            $existVaultKey = true;
        }
    }
    $vaultSecretKey = $existVaultKey ? $arrVaultConfig['key'] : null;
    $vaultInstance = new Vault( $fileSystemSecret, new AES256CBCEncryption($vaultSecretKey));

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

    if ($command === 'vault') {
        $subCommand = isset($argv[2]) ? $argv[2] : false;

        if (!in_array($subCommand, ['key:generate', 'secret:put', 'secret:destroy', 'secret:all'])) {
            exibirAjuda();
            exit(1);
        }

        if ($subCommand === 'key:generate') {
            $generatingNewKey = false;

            echo PHP_EOL . "Generating private key..." . PHP_EOL;

            if ($existVaultKey === true) {
                echo sprintf("There is already a key configured (...%s)", substr($vaultSecretKey, -6)) . PHP_EOL;
                do {
                    $input = strtolower(trim(readline("Overwrite existing key? (y/n): ")));
                } while ($input !== 'y' && $input !== 'n');

                if ($input === 'y') {
                    $generatingNewKey = true;
                } else {
                    $generatingNewKey = false;
                }
            } else {
                $generatingNewKey = true;
            }

            if ($generatingNewKey === true) {
                $newKey = $vaultInstance->keyGenerate();
                $newConfigVaultArr = ['key' => $newKey];
                $fileSystemConfig->write($fileConfigVault, json_encode($newConfigVaultArr));
                echo sprintf("Key successfully created. (....%s)", substr($newKey, -6)) . PHP_EOL;
            }

            echo PHP_EOL;
        }

        if ($subCommand === 'secret:put') {
            echo PHP_EOL;
            echo "=== 🔐 Create a new secret ===" . PHP_EOL . PHP_EOL;
            echo "Please provide the path (secret path) where the secret will be stored." . PHP_EOL;
            echo "Only lowercase letters, numbers, and slashes (/) are allowed. Example: example/service/jwt" . PHP_EOL . PHP_EOL;

            $secretPath = readline("Enter the secret path (only lowercase letters, numbers, and '/'): ");
            $secretValue = readline_hidden("Enter the secret value (input hidden): ");
            $confirmValue = readline_hidden("Confirm the secret value (input hidden): ");

            if ($secretValue !== $confirmValue) {
                echo PHP_EOL . PHP_EOL . "❌  Entered values do not match. Operation aborted." . PHP_EOL . PHP_EOL;
                echo "==============================" . PHP_EOL;
                exit(1);
            }

            if ($vaultInstance->exists($secretPath)) {
                echo PHP_EOL . PHP_EOL . "⚠️  A secret is already configured at path '" . $vaultInstance->normalizedSecretPath($secretPath) . "'. Operation aborted." . PHP_EOL . PHP_EOL;
                echo "==============================" . PHP_EOL;
                exit(1);
            }

            $vaultInstance->write($secretPath, $secretValue);
            echo "🔐 Secret successfully created at path '" . $vaultInstance->normalizedSecretPath($secretPath) . "'." . PHP_EOL;
            echo "==============================" . PHP_EOL;
        }

        if ($subCommand === 'secret:destroy') {
            echo PHP_EOL;
            echo "=== 🔐 Destroy secret ===" . PHP_EOL . PHP_EOL;
            echo "⚠️  Warning: This action will permanently delete the secret." . PHP_EOL . PHP_EOL;
            $secretPath = readline("Enter the full path of the secret you want to permanently delete: ");

            if (!$vaultInstance->exists($secretPath)) {
                echo PHP_EOL . PHP_EOL . "❌ The secret at path '" . $vaultInstance->normalizedSecretPath($secretPath) . "' was not found in the vault. Operation aborted." . PHP_EOL . PHP_EOL;
                echo "==============================" . PHP_EOL;
                exit(1);
            }

            echo PHP_EOL;

            do {
                $input = strtolower(trim(readline("This operation is irreversible. Do you want to continue? (y/n): ")));
            } while ($input !== 'y' && $input !== 'n');

            if ($input === 'y') {
                $vaultInstance->destroy($secretPath);
                echo PHP_EOL . PHP_EOL . "✅ Secret permanently deleted successfully." . PHP_EOL . PHP_EOL;
                echo "==============================" . PHP_EOL;
            } else {
                echo PHP_EOL . PHP_EOL . "❌ Operation aborted." . PHP_EOL . PHP_EOL;
                echo "==============================" . PHP_EOL;
                exit(1);
            }
        }

        if ($subCommand === 'secret:all') {
            $allSecrets = $vaultInstance->all();
            echo PHP_EOL;
            echo "======= 🔐 Destroy all =======" . PHP_EOL;
            echo "Secrets found in the vault:" . PHP_EOL;

            if (count($allSecrets) === 0) {
                echo PHP_EOL . "⚠️  No secrets were found in the vault." . PHP_EOL . PHP_EOL;
                echo "==============================" . PHP_EOL . PHP_EOL;
                exit(0);
            } else {
                echo PHP_EOL . "📘 Structure:" . PHP_EOL . PHP_EOL;
                printTree($allSecrets);
                echo PHP_EOL;
            }

            echo "==============================" . PHP_EOL;
        }

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
    $tagParse->register(new VaultTaggedParse($vaultInstance));
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
