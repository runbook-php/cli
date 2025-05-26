<?php declare(strict_types=1);

function renewCache(string $file, string $checksum): bool {
    if (file_exists($file) === false || is_readable($file) === false) {
      return true; 
    }

    $cache = json_decode(file_get_contents($file), true);

    if(is_array($cache) === false) {
        return true;
    }

    if(isset($cache['checksum']) === false) {
        return true;
    }

    if(empty($cache['checksum']) || is_string($cache['checksum']) === false) {
        return true;
    }

    if ($cache['checksum'] !== $checksum) {
        return true;
    }

    if(isset($cache['providers']) === false) {
        return true;
    }

    if(is_array($cache['providers']) === false) {
        return true;
    }

    if(count($cache['providers']) === 0) {
        return true;
    }

    return false;
}

function clearCache(string $file)
{
    if (file_exists($file)) {
        @unlink($file);
    }
}
