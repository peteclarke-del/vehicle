<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:migrate-attachments',
    description: 'Migrate attachments from uploads/attachments/ to uploads/vehicles/ structure'
)]

/**
 * class MigrateAttachmentsCommand
 */
class MigrateAttachmentsCommand extends Command
{
    /**
     * function __construct
     *
     * @param EntityManagerInterface $entityManager
     * @param string $projectDir
     *
     * @return void
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {
        parent::__construct();
    }

    /**
     * function configure
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without making changes')
            ->addOption('cleanup', null, InputOption::VALUE_NONE, 'Remove empty directories after migration');
    }

    /**
     * function execute
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $cleanup = $input->getOption('cleanup');

        $uploadsDir = $this->projectDir . '/uploads';
        $attachmentsDir = $uploadsDir . '/attachments';
        $vehiclesDir = $uploadsDir . '/vehicles';

        if (!is_dir($attachmentsDir)) {
            $io->success('No attachments directory found - nothing to migrate.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->note('DRY RUN MODE - No changes will be made');
        }

        // Get all attachments with storage_path starting with 'attachments/'
        $sql = "SELECT id, storage_path FROM attachments WHERE storage_path LIKE 'attachments/%'";
        $stmt = $this->entityManager->getConnection()->executeQuery($sql);
        $attachments = $stmt->fetchAllAssociative();

        $io->info(sprintf('Found %d attachments to migrate', count($attachments)));

        $migrated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($attachments as $attachment) {
            $id = $attachment['id'];
            $oldStoragePath = $attachment['storage_path'];
            
            // Convert attachments/bt14-udj/part/file.jpg to vehicles/bt14-udj/parts/file.jpg
            $newStoragePath = $this->convertStoragePath($oldStoragePath);
            
            if ($newStoragePath === $oldStoragePath) {
                $io->warning(sprintf('Skipping attachment %d - could not convert path: %s', $id, $oldStoragePath));
                $skipped++;
                continue;
            }

            $oldFullPath = $uploadsDir . '/' . $oldStoragePath;
            $newFullPath = $uploadsDir . '/' . $newStoragePath;

            if (!file_exists($oldFullPath)) {
                $io->warning(sprintf('Skipping attachment %d - file not found: %s', $id, $oldFullPath));
                $skipped++;
                continue;
            }

            $io->text(sprintf('  [%d] %s -> %s', $id, $oldStoragePath, $newStoragePath));

            if (!$dryRun) {
                // Create target directory
                $targetDir = dirname($newFullPath);
                if (!is_dir($targetDir)) {
                    if (!@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                        $io->error(sprintf('Failed to create directory: %s', $targetDir));
                        $errors++;
                        continue;
                    }
                }

                // Move the file
                if (!@rename($oldFullPath, $newFullPath)) {
                    $io->error(sprintf('Failed to move file: %s', $oldFullPath));
                    $errors++;
                    continue;
                }

                // Update database
                $updateSql = "UPDATE attachments SET storage_path = ? WHERE id = ?";
                $this->entityManager->getConnection()->executeStatement($updateSql, [$newStoragePath, $id]);
            }

            $migrated++;
        }

        // Cleanup empty directories
        if ($cleanup && !$dryRun) {
            $io->section('Cleaning up empty directories...');
            $this->removeEmptyDirectories($attachmentsDir, $io);
        }

        $io->newLine();
        $io->success(sprintf(
            'Migration complete: %d migrated, %d skipped, %d errors',
            $migrated,
            $skipped,
            $errors
        ));

        if ($dryRun) {
            $io->note('This was a dry run. Run without --dry-run to apply changes.');
        }

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * function convertStoragePath
     *
     * @param string $path
     *
     * @return string
     */
    private function convertStoragePath(string $path): string
    {
        // Pattern: attachments/<regno>/<category>/filename
        // Target:  vehicles/<regno>/<category>/filename
        
        if (!str_starts_with($path, 'attachments/')) {
            return $path;
        }

        // Simply replace 'attachments/' with 'vehicles/'
        // The category folders should already be correct (part, service, mot, etc.)
        // But we need to pluralize some: part -> parts, consumable -> consumables
        $newPath = 'vehicles/' . substr($path, strlen('attachments/'));
        
        // Fix category names to match the new standard
        $categoryMappings = [
            '/part/' => '/parts/',
            '/consumable/' => '/consumables/',
        ];
        
        foreach ($categoryMappings as $old => $new) {
            if (str_contains($newPath, $old)) {
                $newPath = str_replace($old, $new, $newPath);
                break;
            }
        }

        return $newPath;
    }

    /**
     * function removeEmptyDirectories
     *
     * @param string $dir
     * @param SymfonyStyle $io
     *
     * @return void
     */
    private function removeEmptyDirectories(string $dir, SymfonyStyle $io): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeEmptyDirectories($path, $io);
            }
        }

        // Re-check after recursive cleanup
        $files = array_diff(scandir($dir), ['.', '..']);
        if (empty($files)) {
            if (@rmdir($dir)) {
                $io->text(sprintf('  Removed empty directory: %s', $dir));
            }
        }
    }
}
