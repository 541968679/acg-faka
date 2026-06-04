<?php
declare (strict_types=1);

return [
    'driver' => getenv('DB_DRIVER') ?: 'mysql',
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => (int)(getenv('DB_PORT') ?: 3306),
    'database' => getenv('DB_DATABASE') ?: 'acgfaka',
    'username' => getenv('DB_USERNAME') ?: 'faka',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    'collation' => getenv('DB_COLLATION') ?: 'utf8mb4_unicode_ci',
    'prefix' => getenv('DB_PREFIX') ?: 'acg_',
];
