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