<?php

declare(strict_types=1);

$checks = [
    ['host' => '127.0.0.1', 'port' => 8000, 'name' => 'octane'],
    ['host' => getenv('DB_HOST') ?: 'mysql', 'port' => (int) (getenv('DB_PORT') ?: 3306), 'name' => 'mysql'],
    ['host' => getenv('REDIS_HOST') ?: 'redis', 'port' => (int) (getenv('REDIS_PORT') ?: 6379), 'name' => 'redis'],
];

foreach ($checks as $check) {
    $socket = @fsockopen($check['host'], $check['port'], $errorCode, $errorMessage, 2.0);
    if ($socket === false) {
        fwrite(STDERR, sprintf(
            "%s health check failed (%s:%d): [%d] %s\n",
            $check['name'],
            $check['host'],
            $check['port'],
            $errorCode,
            $errorMessage,
        ));
        exit(1);
    }
    fclose($socket);
}
