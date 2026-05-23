<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;

class ExportZipArchiveBuilder
{
    public function __construct(
        private readonly VehicleExportService $exportService
    ) {
    }

    /**
     * Build an export ZIP archive at the provided path.
     *
     * @return array{vehicleCount:int, stockItemCount:int, zipSize:int}
     */
    public function build(
        User $user,
        bool $isAdmin,
        bool $includeGlobalState,
        bool $includeImages,
        string $workDir,
        string $zipPath,
        ?callable $progressReporter = null,
        ?callable $shouldAbort = null
    ): array {
        $this->guardAbort($shouldAbort);
        $this->reportProgress($progressReporter, 20, 'Scanning and exporting database records', 'db_export');

        $exportResult = $this->exportService->exportVehicles(
            $user,
            $isAdmin,
            true,
            $workDir,
            $includeGlobalState,
            $includeImages
        );

        if (!$exportResult->isSuccess()) {
            throw new \RuntimeException($exportResult->getMessage() ?? 'Export service failed');
        }

        $exportData = $exportResult->getData();
        if (!is_array($exportData) || $exportData === []) {
            throw new \RuntimeException('Export service returned empty payload');
        }

        $mediaFiles = $this->collectMediaFiles($exportData);
        $sanitizedExportData = $this->stripInternalZipMetadata($exportData);

        $vehicleCount = count($sanitizedExportData['vehicles'] ?? []);
        $stockItemCount = count($sanitizedExportData['stockItems'] ?? []);
        $this->reportProgress(
            $progressReporter,
            45,
            sprintf(
                'Database export complete (%d vehicles, %d stock items)',
                $vehicleCount,
                $stockItemCount
            ),
            'db_export'
        );

        $jsonFlags = JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

        $this->reportProgress($progressReporter, 50, 'Writing backup.json', 'json_write');
        $this->guardAbort($shouldAbort);
        $this->writeJsonFile($workDir . '/backup.json', $sanitizedExportData, $jsonFlags);

        $this->reportProgress($progressReporter, 55, 'Writing vehicles.json', 'json_write');
        $this->guardAbort($shouldAbort);
        $this->writeJsonFile($workDir . '/vehicles.json', $sanitizedExportData['vehicles'] ?? [], $jsonFlags);

        if (!empty($sanitizedExportData['stockItems']) && is_array($sanitizedExportData['stockItems'])) {
            $this->reportProgress($progressReporter, 58, 'Writing stock.json', 'json_write');
            $this->guardAbort($shouldAbort);
            $this->writeJsonFile($workDir . '/stock.json', ['stockItems' => $sanitizedExportData['stockItems']], $jsonFlags);
        }

        if ($includeGlobalState && !empty($sanitizedExportData['globalState']) && is_array($sanitizedExportData['globalState'])) {
            $this->reportProgress($progressReporter, 60, 'Writing global.json', 'json_write');
            $this->guardAbort($shouldAbort);
            $this->writeJsonFile($workDir . '/global.json', ['globalState' => $sanitizedExportData['globalState']], $jsonFlags);
        }

        $manifest = is_array($sanitizedExportData['manifest'] ?? null)
            ? $sanitizedExportData['manifest']
            : [];

        $manifest['filesIncluded'] = [
            'backup.json' => 'Full backup payload including vehicles, stockItems, globalState, manifest.',
            'vehicles.json' => 'Vehicle records for legacy import compatibility.',
            'stock.json' => 'Stock/inventory items (if present).',
            'global.json' => 'Global reference/user state (if present, only if includeGlobalState=true).',
            'attachments/' => 'Attachment files (if present).',
            'images/' => 'Vehicle images (if present, only if includeImages=true).',
        ];
        $manifest['exportOptions'] = [
            'includeGlobalState' => $includeGlobalState,
            'includeImages' => $includeImages,
        ];

        $this->reportProgress($progressReporter, 63, 'Writing manifest.json', 'json_write');
        $this->guardAbort($shouldAbort);
        $this->writeJsonFile($workDir . '/manifest.json', $manifest, $jsonFlags);

        $this->reportProgress($progressReporter, 68, 'Creating ZIP archive', 'zip_prepare');
        $this->buildZipFromDirectory($workDir, $zipPath, $mediaFiles, $progressReporter, $shouldAbort);

        clearstatcache(true, $zipPath);
        $zipSize = filesize($zipPath);
        if ($zipSize === false) {
            throw new \RuntimeException('Unable to determine ZIP size');
        }

        $this->reportProgress($progressReporter, 95, 'Finalizing archive', 'zip_finalize');

        return [
            'vehicleCount' => $vehicleCount,
            'stockItemCount' => $stockItemCount,
            'zipSize' => $zipSize,
        ];
    }

    private function buildZipFromDirectory(string $sourceDir, string $zipPath, array $mediaFiles = [], ?callable $progressReporter = null, ?callable $shouldAbort = null): void
    {
        $workFiles = $this->collectWorkFiles($sourceDir);
        $allFiles = array_merge($workFiles, $mediaFiles);
        $totalFiles = count($allFiles);
        $processedFiles = 0;

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Failed to create ZIP archive');
        }

        foreach ($allFiles as $entry) {
            $this->guardAbort($shouldAbort);

            $sourcePath = $entry['source'] ?? null;
            $targetPath = $entry['target'] ?? null;

            if (!is_string($sourcePath) || $sourcePath === '' || !is_string($targetPath) || $targetPath === '') {
                continue;
            }

            if (!is_file($sourcePath)) {
                continue;
            }

            $zip->addFile($sourcePath, $targetPath);
            $processedFiles++;

            if ($totalFiles > 0) {
                $ratio = $processedFiles / $totalFiles;
                $progress = (int) round(68 + ($ratio * 26));

                if ($processedFiles === 1 || $processedFiles % 25 === 0 || $processedFiles === $totalFiles) {
                    $this->reportProgress(
                        $progressReporter,
                        $progress,
                        sprintf('Adding files to ZIP (%d/%d)', $processedFiles, $totalFiles),
                        'zip_pack'
                    );
                }
            }
        }
        $zip->close();
    }

    /**
     * @return list<array{source:string,target:string}>
     */
    private function collectWorkFiles(string $directory, string $prefix = ''): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $filesOut = [];
        $files = scandir($directory);
        if (!is_array($files)) {
            return [];
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $directory . '/' . $file;
            $target = $prefix === '' ? $file : ($prefix . '/' . $file);

            if (is_dir($path)) {
                $filesOut = array_merge($filesOut, $this->collectWorkFiles($path, $target));
                continue;
            }

            if (is_file($path)) {
                $filesOut[] = [
                    'source' => $path,
                    'target' => $target,
                ];
            }
        }

        return $filesOut;
    }

    /**
     * @param array<string, mixed> $exportData
     * @return list<array{source:string,target:string}>
     */
    private function collectMediaFiles(array $exportData): array
    {
        $files = [];
        $seen = [];

        $walker = function (mixed $node) use (&$walker, &$files, &$seen): void {
            if (!is_array($node)) {
                return;
            }

            $source = $node['_zipSourcePath'] ?? null;
            $target = $node['_zipTargetPath'] ?? null;
            if (is_string($source) && $source !== '' && is_string($target) && $target !== '') {
                $key = $target . '|' . $source;
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $files[] = [
                        'source' => $source,
                        'target' => $target,
                    ];
                }
            }

            foreach ($node as $value) {
                $walker($value);
            }
        };

        $walker($exportData);

        return $files;
    }

    private function stripInternalZipMetadata(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && str_starts_with($key, '_zip')) {
                continue;
            }

            $result[$key] = $this->stripInternalZipMetadata($item);
        }

        return $result;
    }

    private function reportProgress(?callable $progressReporter, int $progress, string $message, ?string $stage = null): void
    {
        if (!$progressReporter) {
            return;
        }

        $progressReporter($progress, $message, $stage);
    }

    private function guardAbort(?callable $shouldAbort): void
    {
        if (!$shouldAbort) {
            return;
        }

        if ($shouldAbort() === true) {
            throw new \RuntimeException('Export cancelled by user');
        }
    }

    private function writeJsonFile(string $path, mixed $payload, int $jsonFlags): void
    {
        $json = json_encode($payload, $jsonFlags);
        if (file_put_contents($path, $json) === false) {
            throw new \RuntimeException('Failed to write JSON file: ' . $path);
        }
    }
}
