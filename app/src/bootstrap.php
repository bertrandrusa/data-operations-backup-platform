<?php

declare(strict_types=1);

use DataOps\Audit;
use DataOps\Auth;
use DataOps\BackupService;
use DataOps\Config;
use DataOps\Database;

spl_autoload_register(static function (string $class): void {
    $prefix = 'DataOps\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = __DIR__ . '/' . substr($class, strlen($prefix)) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require __DIR__ . '/helpers.php';

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_name(Config::get('APP_SESSION_NAME', 'dataops_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => Config::isProduction(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

$db = Database::connection();
$audit = new Audit($db);
$auth = new Auth($db, $audit);
$backupService = new BackupService($db, $audit);
