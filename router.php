<?php

declare(strict_types=1);

// Laat de ingebouwde PHP-server bestaande statische bestanden zelf afhandelen.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . '/public' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        if ($extension === 'html') {
            header('Content-Type: text/html; charset=utf-8');
        }
        readfile($file);
        exit;
    }
}

require __DIR__ . '/public/index.php';
