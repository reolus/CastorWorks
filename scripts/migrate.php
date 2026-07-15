<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;
use PDO;
use Throwable;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script may only be run from the command line.\n");
    exit(1);
}

$root = dirname(__DIR__);

require_once $root . '/app/core/Env.php';
Env::load($root . '/.env');

require_once $root . '/app/core/Database.php';

$command = $argv[1] ?? 'migrate';

if (!in_array($command, ['migrate', 'status'], true)) {
    fwrite(STDERR, "Usage: php scripts/migrate.php [migrate|status]\n");
    exit(1);
}

try {
    $pdo = Database::connection();
    ensureMigrationTable($pdo);

    $migrationFiles = findMigrationFiles($root . '/database');

    if ($command === 'status') {
        showStatus($pdo, $migrationFiles);
        exit(0);
    }

    applyMigrations($pdo, $migrationFiles);
} catch (Throwable $e) {
    fwrite(STDERR, "\nMigration failed: {$e->getMessage()}\n");
    exit(1);
}

/**
 * Ensure the migration ledger exists and has the columns expected by the runner.
 */
function ensureMigrationTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS schema_migrations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            migration VARCHAR(190) NOT NULL,
            checksum VARCHAR(64) NULL,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_schema_migrations_migration (migration)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    $columns = $pdo->query("SHOW COLUMNS FROM schema_migrations")->fetchAll();
    $columnNames = array_column($columns, 'Field');

    if (!in_array('checksum', $columnNames, true)) {
        $pdo->exec(
            "ALTER TABLE schema_migrations
             ADD COLUMN checksum VARCHAR(64) NULL AFTER migration"
        );
    }

    if (!in_array('applied_at', $columnNames, true)) {
        $pdo->exec(
            "ALTER TABLE schema_migrations
             ADD COLUMN applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP"
        );
    }
}

/**
 * Return migration SQL files in natural phase order.
 *
 * @return list<string>
 */
function findMigrationFiles(string $databaseDirectory): array
{
    $files = glob($databaseDirectory . '/migrate_*.sql') ?: [];

    usort(
        $files,
        static function (string $a, string $b): int {
            return strnatcasecmp(basename($a), basename($b));
        }
    );

    return array_values($files);
}

/**
 * Apply every migration that has not already been recorded.
 *
 * Important: MySQL implicitly commits many DDL statements. A transaction cannot
 * make an entire DDL-heavy migration atomic, so the ledger is written only after
 * the SQL file completes successfully.
 *
 * @param list<string> $migrationFiles
 */
function applyMigrations(PDO $pdo, array $migrationFiles): void
{
    if ($migrationFiles === []) {
        echo "No migration files were found.\n";
        return;
    }

    $lookup = $pdo->prepare(
        "SELECT checksum
         FROM schema_migrations
         WHERE migration = ?
         LIMIT 1"
    );

    $record = $pdo->prepare(
        "INSERT INTO schema_migrations (migration, checksum, applied_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE
             checksum = VALUES(checksum)"
    );

    $applied = 0;
    $skipped = 0;

    foreach ($migrationFiles as $file) {
        $name = basename($file);
        $checksum = hash_file('sha256', $file);

        if ($checksum === false) {
            throw new RuntimeException("Unable to calculate checksum for {$name}.");
        }

        $lookup->execute([$name]);
        $existing = $lookup->fetchColumn();

        if ($existing !== false) {
            if (is_string($existing) && $existing !== '' && !hash_equals($existing, $checksum)) {
                throw new RuntimeException(
                    "Migration {$name} has changed since it was applied.\n" .
                    "Recorded checksum: {$existing}\n" .
                    "Current checksum:  {$checksum}"
                );
            }

            echo "[SKIP] {$name} is already applied.\n";
            $skipped++;
            continue;
        }

        $sql = file_get_contents($file);

        if ($sql === false) {
            throw new RuntimeException("Unable to read {$name}.");
        }

        if (trim($sql) === '') {
            echo "[SKIP] {$name} is empty.\n";
            $record->execute([$name, $checksum]);
            $skipped++;
            continue;
        }

        echo "[RUN ] {$name}\n";
        $startedAt = microtime(true);

        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            throw new RuntimeException(
                "{$name} failed: {$e->getMessage()}",
                0,
                $e
            );
        }

        // Record only after the SQL completed. ON DUPLICATE KEY makes this safe
        // if another migration process recorded the same file concurrently.
        $record->execute([$name, $checksum]);

        $elapsed = number_format(microtime(true) - $startedAt, 3);
        echo "[ OK ] {$name} ({$elapsed}s)\n";
        $applied++;
    }

    echo "\nMigration complete. Applied: {$applied}; skipped: {$skipped}.\n";
}

/**
 * Display migration state without changing the database.
 *
 * @param list<string> $migrationFiles
 */
function showStatus(PDO $pdo, array $migrationFiles): void
{
    $rows = $pdo->query(
        "SELECT migration, checksum, applied_at
         FROM schema_migrations
         ORDER BY migration"
    )->fetchAll();

    $applied = [];

    foreach ($rows as $row) {
        $applied[(string) $row['migration']] = $row;
    }

    printf("%-36s %-10s %-19s %s\n", 'Migration', 'Status', 'Applied at', 'Checksum');
    echo str_repeat('-', 110) . "\n";

    foreach ($migrationFiles as $file) {
        $name = basename($file);
        $checksum = hash_file('sha256', $file) ?: '';
        $row = $applied[$name] ?? null;

        if ($row === null) {
            printf("%-36s %-10s %-19s %s\n", $name, 'PENDING', '-', substr($checksum, 0, 12));
            continue;
        }

        $recordedChecksum = (string) ($row['checksum'] ?? '');
        $status = (
            $recordedChecksum !== '' &&
            !hash_equals($recordedChecksum, $checksum)
        ) ? 'CHANGED' : 'APPLIED';

        printf(
            "%-36s %-10s %-19s %s\n",
            $name,
            $status,
            (string) ($row['applied_at'] ?? '-'),
            substr($recordedChecksum !== '' ? $recordedChecksum : $checksum, 0, 12)
        );
    }
}
