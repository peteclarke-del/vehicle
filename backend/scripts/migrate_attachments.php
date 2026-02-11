#!/usr/bin/env php
<?php
/**
 * Script to migrate attachments from uploads/attachments/ to uploads/vehicles/
 * and update the database to reflect the new locations.
 * 
 * Usage:
 *   php scripts/migrate_attachments.php --dry-run    # Preview changes
 *   php scripts/migrate_attachments.php              # Apply changes
 *   php scripts/migrate_attachments.php --cleanup    # Apply and remove empty dirs
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Load environment variables
$dotenv = new Dotenv();
$dotenv->loadEnv(dirname(__DIR__) . '/.env');

// Parse command line arguments
$dryRun = in_array('--dry-run', $argv);
$cleanup = in_array('--cleanup', $argv);

$projectDir = dirname(__DIR__);
$uploadsDir = $projectDir . '/uploads';
$attachmentsDir = $uploadsDir . '/attachments';
$vehiclesDir = $uploadsDir . '/vehicles';

echo "=== Attachment Migration Script ===\n\n";

if ($dryRun) {
    echo "** DRY RUN MODE - No changes will be made **\n\n";
}

// Database connection
$dbUrl = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
if (!$dbUrl) {
    die("ERROR: DATABASE_URL not found in environment\n");
}

// Parse DATABASE_URL (supports mysql://, pgsql://, postgresql://)
$parts = parse_url($dbUrl);
if (!$parts || empty($parts['host'])) {
    die("ERROR: Could not parse DATABASE_URL\n");
}

$scheme = $parts['scheme'] ?? 'mysql';
$dbHost = $parts['host'];
$dbUser = rawurldecode($parts['user'] ?? '');
$dbPass = rawurldecode($parts['pass'] ?? '');
$dbName = ltrim($parts['path'] ?? '', '/');
$dbName = explode('?', $dbName)[0]; // Remove query params

// Normalise driver and set default port
$driver = match ($scheme) {
    'mysql', 'mysql2', 'mariadb' => 'mysql',
    'pgsql', 'postgresql', 'postgres' => 'pgsql',
    default => $scheme,
};
$defaultPort = $driver === 'pgsql' ? 5432 : 3306;
$dbPort = $parts['port'] ?? $defaultPort;

// Build the PDO DSN
$charset = $driver === 'pgsql' ? 'UTF8' : 'utf8mb4';
$pdoDsn = "{$driver}:host={$dbHost};port={$dbPort};dbname={$dbName}";
if ($driver === 'mysql') {
    $pdoDsn .= ";charset={$charset}";
}

try {
    $pdo = new PDO(
        $pdoDsn,
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    if ($driver === 'pgsql') {
        $pdo->exec("SET client_encoding TO '{$charset}'");
    }
} catch (PDOException $e) {
    die("ERROR: Database connection failed: " . $e->getMessage() . "\n");
}

echo "Connected to database: $dbName\n\n";

// Check if attachments directory exists
if (!is_dir($attachmentsDir)) {
    echo "No attachments directory found - nothing to migrate.\n";
    exit(0);
}

// Get all attachments with storage_path starting with 'attachments/'
$stmt = $pdo->query("SELECT id, storage_path FROM attachments WHERE storage_path LIKE 'attachments/%'");
$attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($attachments) . " attachments to migrate\n\n";

$migrated = 0;
$skipped = 0;
$errors = 0;

foreach ($attachments as $attachment) {
    $id = $attachment['id'];
    $oldStoragePath = $attachment['storage_path'];
    
    // Convert path
    $newStoragePath = convertStoragePath($oldStoragePath);
    
    if ($newStoragePath === $oldStoragePath) {
        echo "  [SKIP] ID $id - could not convert path: $oldStoragePath\n";
        $skipped++;
        continue;
    }

    $oldFullPath = $uploadsDir . '/' . $oldStoragePath;
    $newFullPath = $uploadsDir . '/' . $newStoragePath;

    if (!file_exists($oldFullPath)) {
        echo "  [SKIP] ID $id - file not found: $oldFullPath\n";
        $skipped++;
        continue;
    }

    echo "  [$id] $oldStoragePath\n";
    echo "     -> $newStoragePath\n";

    if (!$dryRun) {
        // Create target directory
        $targetDir = dirname($newFullPath);
        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                echo "  [ERROR] Failed to create directory: $targetDir\n";
                $errors++;
                continue;
            }
        }

        // Move the file
        if (!@rename($oldFullPath, $newFullPath)) {
            echo "  [ERROR] Failed to move file: $oldFullPath\n";
            $errors++;
            continue;
        }

        // Update database
        $updateStmt = $pdo->prepare("UPDATE attachments SET storage_path = ? WHERE id = ?");
        $updateStmt->execute([$newStoragePath, $id]);
    }

    $migrated++;
}

// Cleanup empty directories
if ($cleanup && !$dryRun) {
    echo "\n=== Cleaning up empty directories ===\n";
    removeEmptyDirectories($attachmentsDir);
}

echo "\n=== Migration Summary ===\n";
echo "  Migrated: $migrated\n";
echo "  Skipped:  $skipped\n";
echo "  Errors:   $errors\n";

if ($dryRun) {
    echo "\n** This was a dry run. Run without --dry-run to apply changes. **\n";
}

exit($errors > 0 ? 1 : 0);

// ==================== Helper Functions ====================

/**
 * function convertStoragePath
 *
 * @param string $path
 *
 * @return string
 */
function convertStoragePath(string $path): string
{
    if (!str_starts_with($path, 'attachments/')) {
        return $path;
    }

    // Replace 'attachments/' with 'vehicles/'
    $newPath = 'vehicles/' . substr($path, strlen('attachments/'));
    
    return $newPath;
}

/**
 * function removeEmptyDirectories
 *
 * @param string $dir
 *
 * @return void
 */
function removeEmptyDirectories(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $files = array_diff(scandir($dir), ['.', '..']);
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            removeEmptyDirectories($path);
        }
    }

    // Re-check after recursive cleanup
    $files = array_diff(scandir($dir), ['.', '..']);
    if (empty($files)) {
        if (@rmdir($dir)) {
            echo "  Removed empty directory: $dir\n";
        }
    }
}
