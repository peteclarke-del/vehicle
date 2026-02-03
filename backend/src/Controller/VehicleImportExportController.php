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
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Controller\Trait\UserSecurityTrait;
use App\Controller\Trait\AttachmentFileOrganizerTrait;
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
    use AttachmentFileOrganizerTrait;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var SluggerInterface
     */
    private SluggerInterface $slugger;

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
     * @param LoggerInterface $logger
     * @param SluggerInterface $slugger
     * @param VehicleExportService $exportService
     * @param VehicleImportService $importService
     *
     * @return void
     */
    public function __construct(
        LoggerInterface $logger,
        SluggerInterface $slugger,
        VehicleExportService $exportService,
        VehicleImportService $importService
    ) {
        $this->logger = $logger;
        $this->slugger = $slugger;
        $this->exportService = $exportService;
        $this->importService = $importService;
    }

    /**
     * function trimString
     *
     * Safely trim a string value, returning null if empty or not a string
     *
     * @param mixed $value
     *
     * @return string
     */
    private function trimString($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * function serializeAttachment
     *
     * Serialize attachment data for export
     *
     * @param Attachment $attachment
     * @param string $zipDir
     *
     * @return array
     */
    private function serializeAttachment(?Attachment $attachment, string $zipDir): ?array
    {
        if (!$attachment || !$zipDir) {
            return null;
        }

        // Validate essential attachment data
        if (!$attachment->getFilename()) {
            $this->logger->warning('[export] Attachment has no filename', ['id' => $attachment->getId()]);
            return null;
        }

        $attachmentData = [
            'filename' => $attachment->getFilename(),
            'storagePath' => $attachment->getStoragePath(),
            'mimetype' => $attachment->getMimeType(),
            'filesize' => $attachment->getFileSize(),
            'uploadedAt' => $attachment->getUploadedAt()?->format('c'),
            'category' => $attachment->getCategory(),
            'description' => $attachment->getDescription(),
        ];

        // Copy the physical file to ZIP directory
        $storagePath = $attachment->getStoragePath() ?: ('attachments/' . $attachment->getFilename());
        $sourcePath = $this->getParameter('kernel.project_dir') . '/uploads/' . ltrim($storagePath, '/');
        
        if (file_exists($sourcePath)) {
            $safeName = 'attachment_' . $attachment->getId() . '_' . basename($attachment->getFilename());
            $targetPath = $zipDir . '/attachments/' . $safeName;
            $destDir = dirname($targetPath);
            
            try {
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                
                if (!copy($sourcePath, $targetPath)) {
                    throw new \RuntimeException('Failed to copy file');
                }
                
                $this->logger->info('[export] Copied attachment to ZIP', [
                    'attachmentId' => $attachment->getId(),
                    'targetPath' => $targetPath
                ]);
                // Store the safe name in the serialized data so import knows where to find it
                $attachmentData['importFilename'] = $safeName;
            } catch (\Throwable $e) {
                $this->logger->error('[export] Failed to copy attachment', [
                    'attachmentId' => $attachment->getId(),
                    'sourcePath' => $sourcePath,
                    'targetPath' => $targetPath,
                    'exception' => $e->getMessage()
                ]);
            }
        } else {
            $this->logger->warning('[export] Attachment file not found', [
                'attachmentId' => $attachment->getId(),
                'sourcePath' => $sourcePath
            ]);
        }

        return $attachmentData;
    }

    /**
     * function deserializeAttachment
     *
     * Deserialize attachment data during import and create Attachment entity
     *
     * @param array $attachmentData
     * @param string $zipDir
     * @param mixed $user
     * @param string $vehicleRegNo
     *
     * @return Attachment
     */
    private function deserializeAttachment(?array $attachmentData, string $zipDir, $user, ?string $vehicleRegNo = null): ?Attachment
    {
        if (!$attachmentData || !isset($attachmentData['importFilename'])) {
            return null;
        }

        // Validate filename
        if (empty($attachmentData['filename'])) {
            $this->logger->warning('[import] Attachment data missing filename');
            return null;
        }

        // Copy file from ZIP to uploads directory
        $sourcePath = $zipDir . '/attachments/' . $attachmentData['importFilename'];
        if (!file_exists($sourcePath)) {
            $this->logger->warning('[import] Attachment file not found in ZIP', [
                'importFilename' => $attachmentData['importFilename'],
                'sourcePath' => $sourcePath
            ]);
            return null;
        }

        // Determine storage path based on vehicle registration and category
        // If no category provided, infer from entityType
        $category = $attachmentData['category'] ?? null;
        if (!$category && isset($attachmentData['entityType'])) {
            $entityType = strtolower($attachmentData['entityType']);
            // Map entity types to sensible category names
            $category = match($entityType) {
                'servicerecord' => 'service',
                'motrecord' => 'mot',
                'fuelrecord' => 'fuel',
                'insurancepolicy' => 'insurance',
                'part' => 'parts',
                'consumable' => 'consumables',
                default => 'misc'
            };
        }
        $category = $category ?? 'misc';
        
        if ($vehicleRegNo) {
            // Sanitize registration number for filesystem use
            $safeRegNo = preg_replace('/[^a-zA-Z0-9-_]/', '_', $vehicleRegNo);
            $uploadDir = $this->getParameter('kernel.project_dir') . '/uploads/vehicles/' . $safeRegNo . '/' . $category;
            $storagePath = 'vehicles/' . $safeRegNo . '/' . $category;
        } else {
            // Fallback to attachments folder if no vehicle registration
            $uploadDir = $this->getParameter('kernel.project_dir') . '/uploads/attachments/' . $category;
            $storagePath = 'attachments/' . $category;
        }
        
        try {
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generate unique filename to avoid collisions
            $newFilename = uniqid('att_') . '_' . basename($attachmentData['filename']);
            $destPath = $uploadDir . '/' . $newFilename;
            
            if (!copy($sourcePath, $destPath)) {
                throw new \RuntimeException('Failed to copy file');
            }
        } catch (\Throwable $e) {
            $this->logger->error('[import] Failed to copy attachment file', [
                'sourcePath' => $sourcePath,
                'destPath' => $destPath ?? 'unknown',
                'exception' => $e->getMessage()
            ]);
            return null;
        }

        // Create Attachment entity
        $attachment = new Attachment();
        $attachment->setFilename($newFilename);
        $attachment->setOriginalFilename($attachmentData['filename']);
        $attachment->setMimeType($attachmentData['mimetype'] ?? 'application/octet-stream');
        $attachment->setFileSize($attachmentData['filesize'] ?? filesize($destPath));
        $attachment->setStoragePath($storagePath . '/' . $newFilename);
        $attachment->setUploadedAt(new \DateTime());
        $attachment->setUser($user);
        
        if (isset($attachmentData['category'])) {
            $attachment->setCategory($attachmentData['category']);
        }
        if (isset($attachmentData['description'])) {
            $attachment->setDescription($attachmentData['description']);
        }

        $this->logger->info('[import] Created attachment from embedded data', [
            'filename' => $newFilename,
            'originalFilename' => $attachmentData['filename'],
            'storagePath' => $storagePath . '/' . $newFilename
        ]);

        return $attachment;
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
            $isAdmin = $this->isAdminForUser($user);

            // Delegate to service
            $result = $this->exportService->exportVehicles(
                $user,
                $isAdmin,
                $includeAttachmentRefs,
                $zipDir
            );

            if (!$result->isSuccess()) {
                return new JsonResponse([
                    'error' => 'Export failed: ' . implode(', ', $result->getErrors())
                ], 500);
            }

            $format = strtolower((string)$request->query->get('format', 'json'));
            $data = $result->getData();

            if ($format === 'csv') {
                $columns = ['registration', 'make', 'model', 'year'];
                $lines = [];
                $lines[] = implode(',', $columns);
                foreach ($data as $v) {
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
            return new JsonResponse(['vehicles' => $data]);
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

            $logger->info('Export ZIP started', ['userId' => $user->getId()]);

            // Create temp directory
            $projectTmpRoot = $this->getParameter('kernel.project_dir') . '/var/tmp';
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

            // Write vehicles.json
            file_put_contents($tempDir . '/vehicles.json', $vehiclesJson);

            // Create ZIP archive
            $zipPath = sys_get_temp_dir() . '/vehicle-export-' . uniqid() . '.zip';
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
                return new JsonResponse(['error' => 'Failed to create zip'], 500);
            }

            // Recursively add all files and directories to ZIP
            $addToZip = function($dir, $zipPath = '') use (&$addToZip, $zip, $tempDir) {
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

            $logger->info('Export ZIP archive created', ['zipPath' => $zipPath]);

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
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'vehicles-export.zip');

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

            $projectTmpRoot = $this->getParameter('kernel.project_dir') . '/var/tmp';
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

            $vehiclesFile = $tmpDir . '/vehicles.json';
            if (!file_exists($vehiclesFile)) {
                return new JsonResponse(['error' => 'Missing vehicles.json in zip'], 400);
            }

            $vehicles = json_decode(file_get_contents($vehiclesFile), true);
            if (!is_array($vehicles)) {
                return new JsonResponse(['error' => 'Invalid vehicles.json'], 400);
            }

            // Check for optional manifest.json (for vehicle images)
            $manifestFile = $tmpDir . '/manifest.json';
            $manifest = null;
            $hasManifest = file_exists($manifestFile);
            if ($hasManifest) {
                $manifest = json_decode(file_get_contents($manifestFile), true);
                if (!is_array($manifest)) {
                    $logger->warning('[import] Invalid manifest.json, skipping vehicle images');
                    $manifest = null;
                    $hasManifest = false;
                }
            }

            $uploadDir = $this->getParameter('kernel.project_dir') . '/uploads';
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
            $importRequest = new Request([], [], [], [], [], [], json_encode($vehicles));
            $result = $this->import($importRequest, $entityManager, $logger, $cache, $tmpDir);

            // build registration -> vehicle map for image import
            $vehiclesList = $vehicles;
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
            if (is_array($vehiclesList)) {
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
            }

            // Import vehicle images if manifest.json is present
            $vehicleImagesSkipped = false;
            $vehicleImagesImported = 0;
            
            if ($hasManifest && $manifest) {
                foreach ($manifest as $m) {
                    if (($m['type'] ?? null) !== 'vehicle_image') {
                        continue;
                    }
                    $src = $tmpDir . '/' . ($m['manifestName'] ?? '');
                    if (!$src || !file_exists($src)) {
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
            @unlink($vehiclesFile);
            @rmdir($tmpDir);

            // Add vehicle images info to result
            $resultData = json_decode($result->getContent(), true);
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
                        $header = str_getcsv(array_shift($lines));
                        $vehicles = [];
                        foreach ($lines as $line) {
                            if ($line === '') {
                                continue;
                            }
                            $row = str_getcsv($line);
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

            // Support wrapped payloads where vehicles are provided under a top-level key
            $isSequential = array_keys($data) === range(0, count($data) - 1);
            if (!$isSequential) {
                if (!empty($data['vehicles']) && is_array($data['vehicles'])) {
                    $data = $data['vehicles'];
                } elseif (!empty($data['data']) && is_array($data['data'])) {
                    $data = $data['data'];
                } elseif (!empty($data['parsed']) && is_array($data['parsed'])) {
                    $data = $data['parsed'];
                } elseif (!empty($data['results']) && is_array($data['results'])) {
                    $data = $data['results'];
                }
            }

            if (!is_array($data)) {
                return new JsonResponse(['error' => 'Invalid data format'], Response::HTTP_BAD_REQUEST);
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
                'executionTime' => $result->getExecutionTime()
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
        $vehicles = $entityManager->getRepository(Vehicle::class)->findBy(['owner' => $user]);

        $vehicleIds = array_map(fn($v) => $v->getId(), $vehicles);
        $count = count($vehicleIds);

        // Remove vehicles (this will cascade to relations configured with cascade remove)
        foreach ($vehicles as $vehicle) {
            $entityManager->remove($vehicle);
        }

        $entityManager->flush();

        // Additional cleanup when cascade=true: remove attachments that reference vehicles
        $cascade = filter_var($request->query->get('cascade'), FILTER_VALIDATE_BOOLEAN);
        $extraDeleted = 0;
        if ($cascade && count($vehicleIds) > 0) {
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
                $policies = $entityManager->getRepository(\App\Entity\InsurancePolicy::class)
                    ->findBy(['holderId' => $user->getId()]);
                foreach ($policies as $policy) {
                    if ($policy->getVehicles()->isEmpty()) {
                        $entityManager->remove($policy);
                    }
                }
                $entityManager->flush();
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
