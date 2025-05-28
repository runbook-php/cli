<?php declare(strict_types=1);

function getHomeDirectory() {
    if (stripos(PHP_OS, 'WIN') === 0) {
        // Ambiente Windows
        return getenv('USERPROFILE');
    } else {
        // Ambiente Unix-like
        $home = getenv('HOME');
        if (!empty($home)) {
            return $home;
        }

        if (function_exists('posix_getuid') && function_exists('posix_getpwuid')) {
            $userInfo = posix_getpwuid(posix_getuid());
            if (isset($userInfo['dir'])) {
                return $userInfo['dir'];
            }
        }

        return null;
    }
}

function readline_hidden_posix(string $prompt = ''): string {
    if (!empty($prompt)) {
        echo $prompt;
    }

    shell_exec('stty -echo');
    $input = rtrim(fgets(STDIN), PHP_EOL);
    shell_exec('stty echo');
    echo PHP_EOL;

    return $input;
}

function readline_hidden_windows(string $prompt = ''): string {
    if (!empty($prompt)) {
        echo $prompt;
    }

    $script = <<<POWERSHELL
powershell -Command "$pword = Read-Host -AsSecureString; [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR(\$pword))"
POWERSHELL;

    $input = rtrim(shell_exec($script));
    echo PHP_EOL;

    return $input;
}


function readline_hidden(string $prompt = ''): string {
    if (stripos(PHP_OS, 'WIN') === 0) {
        return readline_hidden_windows($prompt);
    } else {
        return readline_hidden_posix($prompt);
    }
}

function printTree(array $paths): void
{
    $tree = [];

    foreach ($paths as $path) {
        $parts = explode('/', trim($path, '/'));
        $ref = &$tree;
        foreach ($parts as $part) {
            if (!isset($ref[$part])) {
                $ref[$part] = [];
            }
            $ref = &$ref[$part];
        }
    }

    $print = function ($branch, $prefix = '') use (&$print) {
        $total = count($branch);
        $i = 0;
        foreach ($branch as $key => $sub) {
            $i++;
            $isLast = $i === $total;
            $pointer = $isLast ? '└── ' : '├── ';
            echo $prefix . $pointer . $key . (is_array($sub) && $sub ? '/' : '') . PHP_EOL;
            if (!empty($sub)) {
                $newPrefix = $prefix . ($isLast ? '    ' : '│   ');
                $print($sub, $newPrefix);
            }
        }
    };

    $print($tree);
}
