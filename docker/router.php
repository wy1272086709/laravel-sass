<?php

declare(strict_types=1);

$publicPath = realpath(__DIR__ . '/../public');
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$filePath = realpath($publicPath . $requestPath);

if (
    $publicPath !== false
    && $filePath !== false
    && str_starts_with($filePath, $publicPath . DIRECTORY_SEPARATOR)
    && is_file($filePath)
) {
    return false;
}

require $publicPath . '/index.php';
