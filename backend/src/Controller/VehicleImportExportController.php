<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Vehicle;
use App\Entity\VehicleType;
use App\Entity\VehicleMake;
use App\Entity\VehicleModel;
use App\Entity\FuelRecord;
use App\Entity\Part;
use App\Entity\Consumable;
use App\Entity\Attachment;
use App\Entity\Todo;
use App\Entity\ConsumableType;
use App\Entity\PartCategory;
use App\Entity\ServiceRecord;
use App\Entity\MotRecord;
use App\Entity\InsurancePolicy;
use App\Entity\RoadTax;
use App\Entity\VehicleStatusHistory;
use App\Entity\VehicleImage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use App\Controller\Trait\UserSecurityTrait;
use App\Service\VehicleExportService;
use App\Service\VehicleImportService;

#[Route('/api/vehicles')]
#[IsGranted('ROLE_USER')]

/**
 * class VehicleImportExportController
 */
class VehicleImportExportController extends AbstractController
{
    use UserSecurityTrait;

    /**
     * @var VehicleExportService
     */
    private VehicleExportService $exportService;

    /**
     * @var VehicleImportService
     */
    private VehicleImportService $importService;

    /**
     * function __construct
     *
     * @param VehicleExportService $exportService
     * @param VehicleImportService $importService
     *
     * @return void
     */
    public function __construct(
        VehicleExportService $exportService,
        VehicleImportService $importService
    ) {
        $this->exportService = $exportService;
        $this->importService = $importService;
    }

    #[Route('/export-stock', name: 'vehicles_export_stock', methods: ['GET'])]
    public function exportStock(LoggerInterface $logger): JsonResponse
    {
        try {
            $user = $this->getUserEntity();
            if (!$user) {
                return new JsonResponse(['error' => 'Unauthorized'], 401);
            }

            $isAdmin = $this->isAdminForUser($user);
            $stockItems = $this->exportService->exportStockItems($user, $isAdmin);

            return new JsonResponse(['stockItems' => $stockItems]);
        } catch (\Exception $e) {
            $logger->error('Stock export failed', ['exception' => $e]);
            return new JsonResponse(['error' => 'Export failed'], 500);
        }
    }

    #[Route('/export', name: 'vehicles_export', methods: ['GET'])]

    /**
     * function export
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface $logger
     * @param string $zipDir
     *
     * @return Response
     */
    public function export(Request $request, EntityManagerInterface $entityManager, LoggerInterface $logger, ?string $zipDir = null): Response
    {
        try {
            $user = $this->getUserEntity();
            if (!$user) {
                return new JsonResponse(['error' => 'Unauthorized'], 401);
            }

            $includeAttachmentRefs = $request->query->getBoolean('includeAttachmentRefs', false);
            $includeGlobalState = $request->query->getBoolean('includeGlobalState', false);
            $isAdmin = $this->isAdminForUser($user);

            // Delegate to service
            $result = $this->exportService->exportVehicles(
                $user,
                $isAdmin,
                $includeAttachmentRefs,
                $zipDir,
                $includeGlobalState
            );

            if (!$result->isSuccess()) {
                return new JsonResponse([
                    'error' => 'Export failed: ' . ($result->getMessage() ?? 'unknown error')
                ], 500);
            }

            $format = strtolower((string)$request->query->get('format', 'json'));
            $data = $result->getData();

            if ($format === 'csv') {
                $vehicles = $data['vehicles'] ?? [];
                $columns = ['registration', 'make', 'model', 'year'];
                $lines = [];
                $lines[] = implode(',', $columns);
                foreach ($vehicles as $v) {
                    $row = [];
                    $row[] = isset($v['registrationNumber']) ? str_replace(',', ' ', $v['registrationNumber']) : '';
                    $row[] = isset($v['make']) ? str_replace(',', ' ', $v['make']) : '';
                    $row[] = isset($v['model']) ? str_replace(',', ' ', $v['model']) : '';
                    $row[] = isset($v['year']) ? (string)$v['year'] : '';
                    $lines[] = implode(',', $row);
                }
                $content = implode("\n", $lines);
                $response = new Response($content);
                $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
                $filename = 'vehicles_' . date('Ymd_His') . '.csv';
                $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
                return $response;
            }

            if ($format === 'xlsx') {
                $response = new Response('');
                $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                $filename = 'vehicles_' . date('Ymd_His') . '.xlsx';
                $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
                return $response;
            }

            // Default JSON export
            return new JsonResponse($data);
        } catch (\Exception $e) {
            $logger->error('Export failed with exception', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new JsonResponse([
                'error' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/export-zip', name: 'vehicles_export_zip', methods: ['GET'])]

    /**
     * function exportZip
     *
     * Exports vehicle data as ZIP with streaming response to avoid timeout on large exports.
     * Query parameters:
     * - includeGlobalState: false (optional, set to true to include global reference data)
     * - includeImages: false (optional, set to true to include vehicle images)
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface $logger
     *
     * @return BinaryFileResponse|JsonResponse
     */
    public function exportZip(Request $request, EntityManagerInterface $entityManager, LoggerInterface $logger): BinaryFileResponse|JsonResponse
    {
        try {
            $user = $this->getUserEntity();
            if (!$user) {
                return new JsonResponse(['error' => 'Unauthorized'], 401);
            }

            $includeGlobalState = $request->query->getBoolean('includeGlobalState', false);
            $includeImages = $request->query->getBoolean('includeImages', false);

            $logger->info('Export ZIP started', [
                'userId' => $user->getId(),
                'includeGlobalState' => $includeGlobalState,
                'includeImages' => $includeImages
            ]);

            // Create temp directory
            $projectDirParam = $this->getParameter('kernel.project_dir');
            if (!is_string($projectDirParam)) {
                return new JsonResponse(['error' => 'Invalid project directory configuration'], 500);
            }
            $projectDir = $projectDirParam;
            $projectTmpRoot = $projectDir . '/var/tmp';
            if (!file_exists($projectTmpRoot)) {
                try {
                    mkdir($projectTmpRoot, 0755, true);
                } catch (\Throwable $e) {
                    $logger->error('Failed to create project tmp root for export', ['path' => $projectTmpRoot, 'exception' => $e->getMessage()]);
                    return new JsonResponse(['error' => 'Unable to prepare temporary directory for export'], 500);
                }
            }

            $tempDir = $projectTmpRoot . '/vehicle-export-' . uniqid();
            try {
                mkdir($tempDir, 0755, true);
            } catch (\Throwable $e) {
                $logger->error('Failed to create export tmp dir', ['path' => $tempDir, 'exception' => $e->getMessage()]);
                return new JsonResponse(['error' => 'Unable to prepare temporary directory for export'], 500);
            }

            // Add flag to include attachment references for ZIP export
            $modifiedRequest = $request->duplicate();
            $modifiedRequest->query->set('includeAttachmentRefs', '1');

            // Reuse export() to get vehicles JSON with attachments
            $exportResponse = $this->export($modifiedRequest, $entityManager, $logger, $tempDir);
            if ($exportResponse->getStatusCode() >= 400) {
                $logger->error('Export ZIP failed: export() returned error', [
                    'status' => $exportResponse->getStatusCode(),
                    'body' => $exportResponse->getContent()
                ]);
                return new JsonResponse([
                    'error' => 'Export failed: ' . ($exportResponse->getContent() ?: 'unknown error')
                ], $exportResponse->getStatusCode());
            }

            $vehiclesJson = $exportResponse->getContent();
            if (!is_string($vehiclesJson) || trim($vehiclesJson) === '') {
                $logger->error('Export returned empty payload for ZIP');
                return new JsonResponse(['error' => 'Export failed: empty payload'], 500);
            }

            // Parse the export data to separate into dedicated files
            $exportData = json_decode($vehiclesJson, true);
            if (!is_array($exportData)) {
                return new JsonResponse(['error' => 'Export failed: invalid payload'], 500);
            }

            // Full payload for forward-compatible restore.
            file_put_contents(
                $tempDir . '/backup.json',
                json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            // Backward-compatible split files.
            file_put_contents(
                $tempDir . '/vehicles.json',
                json_encode($exportData['vehicles'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            if (!empty($exportData['stockItems']) && is_array($exportData['stockItems'])) {
                file_put_contents(
                    $tempDir . '/stock.json',
                    json_encode(['stockItems' => $exportData['stockItems']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                );
                $logger->info('[export-zip] Included stock items', ['count' => count($exportData['stockItems'])]);
            }

            if ($includeGlobalState && !empty($exportData['globalState']) && is_array($exportData['globalState'])) {
                file_put_contents(
                    $tempDir . '/global.json',
                    json_encode(['globalState' => $exportData['globalState']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                );
                $logger->info('[export-zip] Included global state', ['groups' => count($exportData['globalState'])]);
            }

            $manifest = is_array($exportData['manifest'] ?? null)
                ? $exportData['manifest']
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
            file_put_contents(
                $tempDir . '/manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            // Create ZIP archive
            $zipPath = sys_get_temp_dir() . '/vehicle-export-' . uniqid() . '.zip';
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
                return new JsonResponse(['error' => 'Failed to create zip'], 500);
            }

            // Recursively add all files and directories to ZIP
            $addToZip = function($dir, $zipPath = '') use (&$addToZip, $zip) {
                $files = scandir($dir);
                foreach ($files as $f) {
                    if ($f === '.' || $f === '..') {
                        continue;
                    }
                    $fullPath = $dir . '/' . $f;
                    $zipFilePath = $zipPath ? $zipPath . '/' . $f : $f;
                    
                    if (is_dir($fullPath)) {
                        $zip->addEmptyDir($zipFilePath);
                        $addToZip($fullPath, $zipFilePath);
                    } else {
                        $zip->addFile($fullPath, $zipFilePath);
                    }
                }
            };
            
            $addToZip($tempDir);
            $zip->close();

            $logger->info('Export ZIP archive created', ['zipPath' => $zipPath, 'sizeBytes' => filesize($zipPath)]);

            // Cleanup temp dir recursively
            $deleteDir = function($dir) use (&$deleteDir) {
                if (!is_dir($dir)) {
                    return;
                }
                $files = scandir($dir);
                foreach ($files as $f) {
                    if ($f === '.' || $f === '..') {
                        continue;
                    }
                    $fullPath = $dir . '/' . $f;
                    if (is_dir($fullPath)) {
                        $deleteDir($fullPath);
                    } else {
                        @unlink($fullPath);
                    }
                }
                @rmdir($dir);
            };
            $deleteDir($tempDir);

            $response = new BinaryFileResponse($zipPath);
            $response->headers->set('Content-Type', 'application/zip');
            $response->headers->set('Content-Encoding', 'gzip');
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'vehicles-export.zip');
            if ($this->getParameter('kernel.environment') !== 'test') {
                $response->deleteFileAfterSend(true);
            }

            $logger->info('Export ZIP completed', ['zipPath' => $zipPath]);
            return $response;
        } catch (\Exception $e) {
            $logger->error('Zip export failed with exception', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new JsonResponse([
                'error' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/import-zip', name: 'vehicles_import_zip', methods: ['POST'])]

    /**
     * function importZip
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface $logger
     * @param TagAwareCacheInterface $cache
     *
     * @return JsonResponse
     */
    public function importZip(Request $request, EntityManagerInterface $entityManager, LoggerInterface $logger, TagAwareCacheInterface $cache): JsonResponse
    {
        try {
            $user = $this->getUserEntity();
            if (!$user) {
                return new JsonResponse(['error' => 'Unauthorized'], 401);
            }

            $file = $request->files->get('file');
            if (!$file) {
                return new JsonResponse(['error' => 'No file uploaded'], 400);
            }

            $projectDirParam = $this->getParameter('kernel.project_dir');
            if (!is_string($projectDirParam)) {
                return new JsonResponse(['error' => 'Invalid project directory configuration'], 500);
            }
            $projectDir = $projectDirParam;
            $projectTmpRoot = $projectDir . '/var/tmp';
            if (!file_exists($projectTmpRoot)) {
                try {
                    mkdir($projectTmpRoot, 0755, true);
                } catch (\Throwable $e) {
                    $logger->error('Failed to create project tmp root for import', ['path' => $projectTmpRoot, 'exception' => $e->getMessage()]);
                    return new JsonResponse(['error' => 'Unable to prepare temporary directory for import'], 500);
                }
            }
            if (!is_writable($projectTmpRoot)) {
                $logger->error('Project tmp root is not writable', ['path' => $projectTmpRoot]);
                return new JsonResponse(['error' => 'Temporary directory not writable'], 500);
            }

            $tmpDir = $projectTmpRoot . '/vehicle-import-' . uniqid();
            try {
                mkdir($tmpDir, 0755, true);
            } catch (\Throwable $e) {
                $logger->error('Failed to create import tmp dir', ['path' => $tmpDir, 'exception' => $e->getMessage()]);
                return new JsonResponse(['error' => 'Unable to prepare temporary directory for import'], 500);
            }

            $zipPath = $tmpDir . '/' . $file->getClientOriginalName();
            $file->move($tmpDir, basename($zipPath));

            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                return new JsonResponse(['error' => 'Invalid zip file'], 400);
            }
            $zip->extractTo($tmpDir);
            $zip->close();

            $backupFile = $tmpDir . '/backup.json';
            $vehiclesFile = $tmpDir . '/vehicles.json';
            $loadedFromBackup = false;

            if (!file_exists($backupFile) && !file_exists($vehiclesFile)) {
                return new JsonResponse(['error' => 'Missing backup.json or vehicles.json in zip'], 400);
            }

            if (file_exists($backupFile)) {
                $backupContents = file_get_contents($backupFile);
                if (!is_string($backupContents)) {
                    return new JsonResponse(['error' => 'Invalid backup.json'], 400);
                }
                $vehicles = json_decode($backupContents, true);
                if (!is_array($vehicles)) {
                    return new JsonResponse(['error' => 'Invalid backup.json'], 400);
                }
                $loadedFromBackup = true;
            } else {
                $vehiclesContents = file_get_contents($vehiclesFile);
                if (!is_string($vehiclesContents)) {
                    return new JsonResponse(['error' => 'Invalid vehicles.json'], 400);
                }
                $vehicles = json_decode($vehiclesContents, true);
                if (!is_array($vehicles)) {
                    return new JsonResponse(['error' => 'Invalid vehicles.json'], 400);
                }
            }
            
            $payload = $vehicles;
            $isSequentialVehicles = array_keys($vehicles) === range(0, count($vehicles) - 1);
            if ($isSequentialVehicles) {
                $payload = ['vehicles' => $vehicles];
            }

            // Merge optional split files into payload (for old and mixed archives).
            $stockFile = $tmpDir . '/stock.json';
            if (file_exists($stockFile) && (!$loadedFromBackup || empty($payload['stockItems']))) {
                $stockContents = file_get_contents($stockFile);
                $stockData = is_string($stockContents) ? json_decode($stockContents, true) : null;
                if (is_array($stockData)) {
                    $stockItems = $stockData['stockItems'] ?? $stockData;
                    if (is_array($stockItems)) {
                        $payload['stockItems'] = array_merge($payload['stockItems'] ?? [], $stockItems);
                        $logger->info('[import-zip] Merged stock items into import data', ['stockItemCount' => count($stockItems)]);
                    }
                }
            }

            $globalFile = $tmpDir . '/global.json';
            if (file_exists($globalFile) && (!$loadedFromBackup || empty($payload['globalState']))) {
                $globalContents = file_get_contents($globalFile);
                $globalData = is_string($globalContents) ? json_decode($globalContents, true) : null;
                if (is_array($globalData)) {
                    $globalState = $globalData['globalState'] ?? $globalData;
                    if (is_array($globalState)) {
                        $existing = $payload['globalState'] ?? [];
                        $payload['globalState'] = array_merge($existing, $globalState);
                        $logger->info('[import-zip] Merged global state into import data', ['groups' => count($globalState)]);
                    }
                }
            }

            // Check for optional manifest file.
            $manifestFile = file_exists($tmpDir . '/MANIFEST.json')
                ? $tmpDir . '/MANIFEST.json'
                : $tmpDir . '/manifest.json';
            $manifest = null;
            $hasManifest = file_exists($manifestFile);
            if ($hasManifest) {
                $manifestContents = file_get_contents($manifestFile);
                $manifest = is_string($manifestContents) ? json_decode($manifestContents, true) : null;
                if (!is_array($manifest)) {
                    $logger->warning('[import] Invalid manifest.json, skipping vehicle images');
                    $manifest = null;
                    $hasManifest = false;
                }
            }

            $uploadDir = $projectDir . '/uploads';
            if (!file_exists($uploadDir)) {
                try {
                    mkdir($uploadDir, 0755, true);
                } catch (\Throwable $e) {
                    $logger->error('Failed to create uploads directory for import', ['path' => $uploadDir, 'exception' => $e->getMessage()]);
                    return new JsonResponse(['error' => 'Unable to create uploads directory'], 500);
                }
            }
            if (!is_writable($uploadDir)) {
                $logger->error('Uploads directory is not writable', ['path' => $uploadDir]);
                return new JsonResponse(['error' => 'Uploads directory not writable'], 500);
            }

            // Option 3: Attachments are embedded in entity data, no separate manifest needed
            $logger->info('[import] Using Option 3 format (embedded attachments)', [
                'hasManifest' => $hasManifest,
                'manifestItems' => $manifest ? count($manifest) : 0
            ]);

            // call existing import logic by creating a synthetic Request
            $importContent = json_encode($payload);
            if (!is_string($importContent)) {
                return new JsonResponse(['error' => 'Invalid import payload encoding'], 500);
            }
            $importRequest = new Request([], [], [], [], [], [], $importContent);
            $result = $this->import($importRequest, $entityManager, $logger, $cache, $tmpDir);

            // build registration -> vehicle map for image import
            $vehiclesList = $payload;
            $isSequential = array_keys($vehiclesList) === range(0, count($vehiclesList) - 1);
            if (!$isSequential) {
                if (!empty($vehiclesList['vehicles']) && is_array($vehiclesList['vehicles'])) {
                    $vehiclesList = $vehiclesList['vehicles'];
                } elseif (!empty($vehiclesList['data']) && is_array($vehiclesList['data'])) {
                    $vehiclesList = $vehiclesList['data'];
                } elseif (!empty($vehiclesList['results']) && is_array($vehiclesList['results'])) {
                    $vehiclesList = $vehiclesList['results'];
                }
            }

            $vehicleByReg = [];
            foreach ($vehiclesList as $vehicleData) {
                $reg = $vehicleData['registrationNumber'] ?? $vehicleData['registration'] ?? null;
                if (!$reg) {
                    continue;
                }
                $vehicle = $entityManager->getRepository(Vehicle::class)
                ->findOneBy(['registrationNumber' => $reg, 'owner' => $user]);
                if ($vehicle) {
                    $vehicleByReg[$reg] = $vehicle;
                }
            }

            // Import vehicle images if a legacy image-list manifest is present.
            $vehicleImagesSkipped = false;
            $vehicleImagesImported = 0;
            $manifestLooksLikeLegacyImageList = $hasManifest
                && is_array($manifest)
                && isset($manifest[0])
                && is_array($manifest[0])
                && (($manifest[0]['type'] ?? null) === 'vehicle_image');
            
            if ($manifestLooksLikeLegacyImageList) {
                foreach ($manifest as $m) {
                    if (($m['type'] ?? null) !== 'vehicle_image') {
                        continue;
                    }
                    $src = $tmpDir . '/' . ($m['manifestName'] ?? '');
                    if (!file_exists($src)) {
                        continue;
                    }

                    $reg = $m['vehicleRegistrationNumber'] ?? null;
                    if (!$reg) {
                        continue;
                    }
                    $vehicle = $vehicleByReg[$reg] ?? null;
                    if (!$vehicle) {
                        continue;
                    }

                    $storagePath = $m['storagePath'] ?? ('vehicles/' . ($m['filename'] ?? basename($m['manifestName'])));
                    $storagePath = ltrim((string) $storagePath, '/');
                    if (str_starts_with($storagePath, 'uploads/')) {
                        $storagePath = substr($storagePath, strlen('uploads/'));
                    }
                    $subDir = trim(dirname($storagePath), '.');
                    if ($subDir === '') {
                        $subDir = 'vehicles';
                    }
                    $targetDir = $uploadDir . '/' . $subDir;
                    if (!file_exists($targetDir)) {
                        try {
                            mkdir($targetDir, 0755, true);
                        } catch (\Throwable $e) {
                            $logger->error('Failed to create vehicle image target directory', ['path' => $targetDir, 'exception' => $e->getMessage()]);
                            continue;
                        }
                    }

                    $filename = basename($storagePath);
                    $dest = $targetDir . '/' . $filename;
                    if (file_exists($dest)) {
                        $filename = uniqid('img_') . '_' . $filename;
                        $dest = $targetDir . '/' . $filename;
                    }

                    try {
                        if (!rename($src, $dest)) {
                            throw new \RuntimeException('Failed to move file');
                        }
                    } catch (\Throwable $e) {
                        $logger->error('Failed to move vehicle image file', [
                            'source' => $src,
                            'dest' => $dest,
                            'exception' => $e->getMessage()
                        ]);
                        continue;
                    }

                    $image = new VehicleImage();
                    $image->setVehicle($vehicle);
                    $image->setPath('/uploads/' . $subDir . '/' . $filename);
                    if (!empty($m['caption'])) {
                        $image->setCaption($m['caption']);
                    } elseif (!empty($m['description'])) {
                        $image->setCaption($m['description']);
                    }
                    if (isset($m['isPrimary'])) {
                        $image->setIsPrimary((bool) $m['isPrimary']);
                    }
                    if (isset($m['displayOrder'])) {
                        $image->setDisplayOrder((int) $m['displayOrder']);
                    }
                    if (!empty($m['uploadedAt'])) {
                        try {
                            $image->setUploadedAt(new \DateTime($m['uploadedAt']));
                        } catch (\Exception) {
                            // ignore invalid date
                        }
                    }

                    $entityManager->persist($image);
                    $vehicleImagesImported++;
                }
                
                if ($vehicleImagesImported > 0) {
                    $entityManager->flush();
                    $logger->info('[import] Imported vehicle images', ['count' => $vehicleImagesImported]);
                }
            } else {
                $vehicleImagesSkipped = true;
                $logger->info('[import] No manifest.json found, skipping vehicle images');
            }

            // cleanup tmp files
            @unlink($zipPath);
            if ($hasManifest) {
                @unlink($manifestFile);
            }
            @unlink($backupFile);
            @unlink($vehiclesFile);
            @unlink($tmpDir . '/stock.json');
            @unlink($tmpDir . '/global.json');
            @rmdir($tmpDir);

            // Add vehicle images info to result
            $resultContent = $result->getContent();
            $resultData = is_string($resultContent) ? json_decode($resultContent, true) : [];
            if (!is_array($resultData)) {
                $resultData = [];
            }
            if ($vehicleImagesSkipped) {
                $resultData['vehicleImagesSkipped'] = true;
                $resultData['vehicleImagesMessage'] = 'import.no_manifest_vehicle_images_skipped';
            } elseif ($vehicleImagesImported > 0) {
                $resultData['vehicleImagesImported'] = $vehicleImagesImported;
            }
            
            return new JsonResponse($resultData, $result->getStatusCode());
        } catch (\Exception $e) {
            $logger->error('Zip import failed with exception', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return new JsonResponse([
                'success' => false,
                'error' => 'Import failed: ' . $e->getMessage(),
                'imported' => 0,
                'failed' => 0,
                'total' => 0
            ], 500);
        }
    }

    #[Route('/import', name: 'vehicles_import', methods: ['POST'])]

    /**
     * function import
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface $logger
     * @param TagAwareCacheInterface $cache
     * @param string $zipExtractDir
     *
     * @return JsonResponse
     */
    public function import(Request $request, EntityManagerInterface $entityManager, LoggerInterface $logger, TagAwareCacheInterface $cache, ?string $zipExtractDir = null): JsonResponse
    {
        try {
            $user = $this->getUserEntity();
            if (!$user) {
                return new JsonResponse(['error' => 'Unauthorized'], 401);
            }

            $content = (string)$request->getContent();

            // Protect against excessively large imports
            if (strlen($content) > 500000) {
                return new JsonResponse(['error' => 'Payload too large'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
            }

            $data = json_decode($content, true);

            // If JSON decode fails, attempt to parse CSV payloads
            if (!is_array($data)) {
                $ct = strtolower((string)$request->headers->get('Content-Type', ''));
                if (str_contains($ct, 'csv') || str_contains($content, ',')) {
                    $lines = array_values(array_filter(array_map('trim', explode("\n", $content))));
                    if (!empty($lines)) {
                        $rawHeader = str_getcsv(array_shift($lines), ',', '"', '\\');
                        $header = array_map(static fn($value): string => (string) $value, $rawHeader);
                        $vehicles = [];
                        foreach ($lines as $line) {
                            $row = str_getcsv($line, ',', '"', '\\');
                            if (count($row) < count($header)) {
                                $row = array_pad($row, count($header), null);
                            }
                            $vehicles[] = array_combine($header, $row);
                        }
                        $data = $vehicles;
                    }
                }
            }

            if (!is_array($data)) {
                return new JsonResponse(['error' => 'Invalid JSON format'], Response::HTTP_BAD_REQUEST);
            }

            // Delegate to service
            $result = $this->importService->importVehicles($data, $user, $zipExtractDir);

            // Clear cache if import was successful
            if ($result->isSuccess() && $result->getImportedCount() > 0) {
                try {
                    $cache->invalidateTags(['vehicle_list']);
                } catch (\Exception $e) {
                    $logger->warning('Failed to invalidate cache after import', ['exception' => $e->getMessage()]);
                }
            }

            // Build response
            $response = [
                'success' => $result->isSuccess(),
                'imported' => $result->getImportedCount(),
                'failed' => $result->getFailedCount(),
                'total' => $result->getTotalCount(),
                'executionTime' => $result->getExecutionTime(),
                'statistics' => $result->getStatistics(),
            ];

            if (count($result->getErrors()) > 0) {
                $response['errors'] = $result->getErrors();
            }

            $statusCode = $result->isSuccess() ? 200 : ($result->getImportedCount() > 0 ? 207 : 400);

            return new JsonResponse($response, $statusCode);
        } catch (\Exception $e) {
            $logger->error('Import failed with uncaught exception', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new JsonResponse([
                'success' => false,
                'error' => 'Import failed: ' . $e->getMessage(),
                'imported' => 0,
                'failed' => 0,
                'total' => 0
            ], 500);
        }
    }


    #[Route('/purge-all', name: 'vehicles_purge_all', methods: ['DELETE'])]

    /**
     * function purgeAll
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     *
     * @return JsonResponse
     */
    public function purgeAll(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUserEntity();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $vehicleRows = $entityManager->createQueryBuilder()
            ->select('v.id')
            ->from(Vehicle::class, 'v')
            ->where('v.owner = :owner')
            ->setParameter('owner', $user)
            ->getQuery()
            ->getScalarResult();

        $vehicleIds = array_map(static fn(array $row) => (int) ($row['id'] ?? 0), $vehicleRows);

        $count = count($vehicleIds);

        if ($count === 0) {
            return new JsonResponse([
                'success' => true,
                'deleted' => 0,
                'deletedAttachments' => 0,
                'message' => 'No vehicles found for this user',
            ]);
        }

        // Fast path: one bulk delete by owner. DB-level ON DELETE CASCADE
        // handles child rows, avoiding expensive per-entity remove loops.
        $entityManager->createQueryBuilder()
            ->delete(Vehicle::class, 'v')
            ->where('v.owner = :owner')
            ->setParameter('owner', $user)
            ->getQuery()
            ->execute();

        // Additional cleanup when cascade=true: remove attachments that reference vehicles
        $cascade = filter_var($request->query->get('cascade'), FILTER_VALIDATE_BOOLEAN);
        $extraDeleted = 0;
            if ($cascade) {
            // Delete attachments where entityType is 'vehicle' or 'vehicle_image' and entityId in vehicleIds
            try {
                $qb = $entityManager->createQueryBuilder();
                $del = $qb->delete(\App\Entity\Attachment::class, 'a')
                    ->where('a.entityType IN (:types)')
                    ->andWhere('a.entityId IN (:ids)')
                    ->setParameter('types', ['vehicle', 'vehicle_image'])
                    ->setParameter('ids', $vehicleIds)
                    ->getQuery();
                $extraDeleted += $del->execute();
            } catch (\Exception $e) {
                // ignore attachment cleanup failures
            }

            // Remove orphaned insurance policies belonging to the user
            try {
                $entityManager->getConnection()->executeStatement(
                    'DELETE FROM insurance_policies
                     WHERE holder_id = :holderId
                     AND NOT EXISTS (
                         SELECT 1 FROM insurance_policy_vehicles ipv
                         WHERE ipv.insurance_policy_id = insurance_policies.id
                     )',
                    ['holderId' => $user->getId()]
                );
            } catch (\Exception $e) {
                // ignore policy cleanup failures
            }
        }

        return new JsonResponse([
            'success' => true,
            'deleted' => $count,
            'deletedAttachments' => $extraDeleted,
            'message' => "Successfully deleted $count vehicle(s)" . ($cascade ? ' (cascade cleanup attempted)' : ''),
        ]);
    }
}
