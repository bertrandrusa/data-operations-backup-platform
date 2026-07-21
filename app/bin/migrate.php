<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use DataOps\Config;

try {
    $db->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            version varchar(100) PRIMARY KEY,
            applied_at timestamptz NOT NULL DEFAULT now()
        )'
    );

    $migrationPath = dirname(__DIR__, 2) . '/database/migrations';
    $files = glob($migrationPath . '/*.sql') ?: [];
    sort($files, SORT_STRING);

    foreach ($files as $file) {
        $version = basename($file);
        $check = $db->prepare('SELECT 1 FROM schema_migrations WHERE version = :version');
        $check->execute(['version' => $version]);
        if ($check->fetchColumn() !== false) {
            continue;
        }

        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException("Could not read migration: {$version}");
        }

        $db->beginTransaction();
        try {
            $db->exec($sql);
            $record = $db->prepare('INSERT INTO schema_migrations (version) VALUES (:version) ON CONFLICT DO NOTHING');
            $record->execute(['version' => $version]);
            $db->commit();
            fwrite(STDOUT, "Applied migration {$version}\n");
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }

    $adminEmail = strtolower(Config::get('ADMIN_EMAIL', 'admin@example.com'));
    $adminPassword = Config::get('ADMIN_PASSWORD', 'ChangeMe-Now-2026!');
    $admin = $db->prepare(
        "INSERT INTO users (email, password_hash, role)
         VALUES (:email, :password_hash, 'admin')
         ON CONFLICT (email) DO NOTHING"
    );
    $admin->execute([
        'email' => $adminEmail,
        'password_hash' => password_hash($adminPassword, PASSWORD_DEFAULT),
    ]);

    fwrite(STDOUT, "Database is ready.\n");
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'Migration error: ' . $error->getMessage() . "\n");
    exit(1);
}

