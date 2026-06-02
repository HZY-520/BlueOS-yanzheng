<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$username = $argv[1] ?? 'admin';
$password = $argv[2] ?? 'admin123';

init_schema();
$created = seed_admin($username, $password);

echo "Database initialized at " . APP_DB_PATH . PHP_EOL;
echo $created
    ? "Admin created: {$username}" . PHP_EOL
    : "Admin already exists: {$username}" . PHP_EOL;
