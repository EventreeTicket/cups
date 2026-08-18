<?php

declare(strict_types=1);

// Laat de ingebouwde PHP-server bestaande statische bestanden zelf afhandelen.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
    if ($path === '/' || $path === '/test') {
        require __DIR__ . '/public/test.php';
        exit;
    }
    if ($path === '/api-docs') {
        require __DIR__ . '/public/api.php';
        exit;
    }
    if ($path === '/history') {
        require __DIR__ . '/public/history.php';
        exit;
    }
    if ($path === '/logs') {
        require __DIR__ . '/public/logs.php';
        exit;
    }

    $file = __DIR__ . '/public' . $path;
    if (is_file($file)) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            require $file;
            exit;
        }
        return false;
    }
}

require __DIR__ . '/public/index.php';
