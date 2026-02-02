<?php

declare(strict_types=1);

namespace App\Service;

use App\Config\ImportExportConfig;
use App\DTO\ImportResult;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleType;
use App\Entity\VehicleMake;
use App\Entity\VehicleModel;
use App\Entity\Part;
use App\Entity\Consumable;
use App\Entity\PartCategory;
use App\Entity\ConsumableType;
use App\Entity\ServiceRecord;
use App\Entity\MotRecord;
use App\Entity\FuelRecord;
use App\Entity\InsurancePolicy;
use App\Entity\RoadTax;
use App\Entity\Todo;
use App\Entity\Attachment;
use App\Exception\ImportException;
use App\Trait\EntityHydratorTrait;
use App\Controller\Trait\AttachmentFileOrganizerTrait;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Service for importing vehicle data
 */
class VehicleImportService
{
    use EntityHydratorTrait;
    use AttachmentFileOrganizerTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private ImportExportConfig $config,
        private TagAwareCacheInterface $cache,
        private string $projectDir
    ) {}

    /**
     * Import vehicles from JSON data
     */
    public function importVehicles(
        array $data,
        User $user,
        ?string $zipExtractDir = null,
        bool $dryRun = false
    ): ImportResult {
        $startTime = microtime(true);
        
        if (!$dryRun) {
            $this->entityManager->beginTransaction();
        }

        try {
            $this->logger->info('[import] Starting import', [
                'vehicleCount' => count($data),
                'dryRun' => $dryRun,
                'zipExtractDir' => $zipExtractDir
            ]);

            // Validate data
            $validationErrors = $this->validateImportData($data);
            if (!empty($validationErrors)) {
                if (!$dryRun) {
                    $this->entityManager->rollback();
                }
                return ImportResult::createFailure(
                    $validationErrors,
                    'Validation failed'
                );
            }

            // Normalize data format
            $data = $this->normalizeImportData($data);

            // Pre-load existing data for duplicate detection  
            $isAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);
            $existingVehiclesMap = $this->buildExistingVehiclesMap($user, $isAdmin);

            // Process all vehicles
            $stats = [
                'processed' => 0,
                'errors' => [],
                'vehicleMap' => []
            ];

            $partImportMap = [];
            $consumableImportMap = [];
            $batchSize = $this->config->getBatchSize();
            
            foreach ($data as $index => $vehicleData) {
                try {
                    $vehicle = $this->processVehicleImport(
                        $vehicleData,
                        $user,
                        $existingVehiclesMap,
                        $partImportMap,
                        $consumableImportMap,
                        $zipExtractDir,
                        $stats
                    );
                    
                    $stats['vehicleMap'][$vehicleData['registrationNumber']] = $vehicle;
                    
                    // Batch flush
                    if (($stats['processed'] % $batchSize) === 0) {
                        if (!$dryRun) {
                            $this->entityManager->flush();
                        }
                    }
                    
                } catch (\Exception $e) {
                    $this->logger->error('[import] Vehicle import failed', [
                        'index' => $index,
                        'registration' => $vehicleData['registrationNumber'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                    $stats['errors'][] = "Vehicle at index $index: " . $e->getMessage();
                }
            }

            if (!$dryRun) {
                $this->entityManager->flush();
                $this->entityManager->commit();
                
                // Clear caches
                $this->cache->invalidateTags(['vehicles', 'parts', 'consumables']);
            }

            $statistics = [
                'vehiclesImported' => $stats['processed'],
                'errors' => count($stats['errors']),
                'processingTimeSeconds' => round(microtime(true) - $startTime, 2),
                'memoryPeakMB' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ];

            $this->logger->info('[import] Completed successfully', $statistics);

            $message = count($stats['errors']) > 0 
                ? "Import completed with " . count($stats['errors']) . " errors"
                : "Import completed successfully";

            return ImportResult::createSuccess(
                $statistics,
                $message,
                $stats['vehicleMap']
            );

        } catch (\Exception $e) {
            if (!$dryRun) {
                $this->entityManager->rollback();
            }
            
            $this->logger->error('[import] Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new ImportException(
                'Import failed: ' . $e->getMessage(),
                null,
                'import',
                0,
                $e
            );
        }
    }

    /**
     * Validate import data structure
     */
    public function validateImportData(array $data): array
    {
        $errors = [];

        if (empty($data)) {
            $errors[] = 'No vehicles to import';
            return $errors;
        }

        foreach ($data as $index => $vehicleData) {
            if (!is_array($vehicleData)) {
                $errors[] = "Vehicle at index $index: invalid data format";
                continue;
            }

            // Check for required fields
            if (empty($vehicleData['name']) && 
                empty($vehicleData['registrationNumber']) && 
                (empty($vehicleData['make']) || empty($vehicleData['model']))) {
                $errors[] = "Vehicle at index $index: name, registrationNumber, or make+model is required";
            }

            // Validate sub-arrays
            if (isset($vehicleData['fuelRecords']) && !is_array($vehicleData['fuelRecords'])) {
                $errors[] = "Vehicle at index $index: fuelRecords must be an array";
            }
            if (isset($vehicleData['parts']) && !is_array($vehicleData['parts'])) {
                $errors[] = "Vehicle at index $index: parts must be an array";
            }
            if (isset($vehicleData['consumables']) && !is_array($vehicleData['consumables'])) {
                $errors[] = "Vehicle at index $index: consumables must be an array";
            }
        }

        return $errors;
    }

    /**
     * Normalize import data format
     */
    private function normalizeImportData(array $data): array
    {
        // Support wrapped payloads
        $isSequential = array_keys($data) === range(0, count($data) - 1);
        if (!$isSequential) {
            if (!empty($data['vehicles']) && is_array($data['vehicles'])) {
                $data = $data['vehicles'];
            } elseif (!empty($data['data']) && is_array($data['data'])) {
                $data = $data['data'];
            }
        }

        // Normalize each vehicle
        foreach ($data as $index => $vehicleData) {
            // Normalize CSV variations
            if (isset($vehicleData['registration']) && empty($vehicleData['registrationNumber'])) {
                $data[$index]['registrationNumber'] = $vehicleData['registration'];
            }
            if (isset($vehicleData['colour']) && empty($vehicleData['vehicleColor'])) {
                $data[$index]['vehicleColor'] = $vehicleData['colour'];
            }

            // Auto-generate name if missing
            if (empty($data[$index]['name'])) {
                $fallback = null;
                if (!empty($vehicleData['registrationNumber'])) {
                    $fallback = $vehicleData['registrationNumber'];
                } elseif (!empty($vehicleData['make']) || !empty($vehicleData['model'])) {
                    $fallback = trim(($vehicleData['make'] ?? '') . ' ' . ($vehicleData['model'] ?? ''));
                }
                if ($fallback) {
                    $data[$index]['name'] = $fallback;
                }
            }
        }

        return $data;
    }

    private function buildExistingVehiclesMap(User $user, bool $isAdmin): array
    {
        $qb = $this->entityManager->getRepository(Vehicle::class)->createQueryBuilder('v');
        
        if (!$isAdmin) {
            $qb->andWhere('v.user = :user')->setParameter('user', $user);
        }
        
        $vehicles = $qb->getQuery()->getResult();
        
        $map = [];
        foreach ($vehicles as $vehicle) {
            if ($vehicle->getRegistrationNumber()) {
                $map[trim($vehicle->getRegistrationNumber())] = $vehicle;
            }
        }
        
        return $map;
    }

    private function buildExistingPartsMap(User $user, bool $isAdmin): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Part::class, 'p')
            ->join('p.vehicle', 'v');
        
        if (!$isAdmin) {
            $qb->andWhere('v.user = :user')->setParameter('user', $user);
        }
        
        $parts = $qb->getQuery()->getResult();
        
        $map = [];
        foreach ($parts as $part) {
            if ($part->getId()) {
                $map[$part->getId()] = $part;
            }
        }
        
        return $map;
    }

    private function buildExistingConsumablesMap(User $user, bool $isAdmin): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Consumable::class, 'c')
            ->join('c.vehicle', 'v');
        
        if (!$isAdmin) {
            $qb->andWhere('v.user = :user')->setParameter('user', $user);
        }
        
        $consumables = $qb->getQuery()->getResult();
        
        $map = [];
        foreach ($consumables as $consumable) {
            if ($consumable->getId()) {
                $map[$consumable->getId()] = $consumable;
            }
        }
        
        return $map;
    }

    private function processVehicleImport(
        array $vehicleData,
        User $user,
        array &$existingVehiclesMap,
        array &$partImportMap,
        array &$consumableImportMap,
        ?string $zipExtractDir,
        array &$stats
    ): Vehicle {
        // Check for duplicate
        $regNum = trim($vehicleData['registrationNumber']);
        if (isset($existingVehiclesMap[$regNum])) {
            throw new ImportException("Vehicle with registration '$regNum' already exists");
        }

        // Resolve vehicle type
        $vehicleType = $this->resolveVehicleType($vehicleData);
        
        // Create vehicle entity
        $vehicle = new Vehicle();
        $vehicle->setOwner($user);
        $vehicle->setName($this->trimString($vehicleData['name']));
        $vehicle->setVehicleType($vehicleType);

        // Set basic fields
        $this->hydrateVehicleBasicFields($vehicle, $vehicleData);
        
        // Set dates
        $dateFields = ['createdAt', 'purchaseDate', 'vinDecodedAt'];
        $dates = $this->hydrateDates($vehicleData, $dateFields);
        
        if (isset($dates['createdAt'])) {
            $vehicle->setCreatedAt($dates['createdAt']);
        }
        if (isset($dates['purchaseDate'])) {
            $vehicle->setPurchaseDate($dates['purchaseDate']);
        } elseif (!empty($vehicleData['purchaseDate'])) {
            try {
                $vehicle->setPurchaseDate(new \DateTime($vehicleData['purchaseDate']));
            } catch (\Exception $e) {
                $vehicle->setPurchaseDate(new \DateTime());
            }
        }
        if (isset($dates['vinDecodedAt'])) {
            $vehicle->setVinDecodedAt($dates['vinDecodedAt']);
        }

        // Resolve make and model if provided
        if (!empty($vehicleData['make'])) {
            $this->resolveVehicleMakeModel($vehicle, $vehicleData, $vehicleType);
        }

        $this->entityManager->persist($vehicle);
        $existingVehiclesMap[$regNum] = $vehicle;
        
        $stats['processed']++;
        
        return $vehicle;
    }

    private function resolveVehicleType(array $vehicleData): VehicleType
    {
        $vehicleType = null;
        
        if (!empty($vehicleData['vehicleType'])) {
            $vehicleType = $this->entityManager->getRepository(VehicleType::class)
                ->findOneBy(['name' => $vehicleData['vehicleType']]);
        }
        
        if (!$vehicleType) {
            $vehicleType = $this->entityManager->getRepository(VehicleType::class)->findOneBy([]);
        }
        
        if (!$vehicleType) {
            $vehicleType = new VehicleType();
            $vehicleType->setName('Car');
            $this->entityManager->persist($vehicleType);
            $this->entityManager->flush();
        }
        
        return $vehicleType;
    }

    private function hydrateVehicleBasicFields(Vehicle $vehicle, array $data): void
    {
        $stringFields = [
            'make', 'model', 'vin', 'registrationNumber', 'engineNumber',
            'v5DocumentNumber', 'vehicleColor', 'securityFeatures',
            'depreciationMethod'
        ];
        
        $trimmed = $this->trimArrayValues($data, $stringFields);
        
        if (isset($trimmed['make'])) $vehicle->setMake($trimmed['make']);
        if (isset($trimmed['model'])) $vehicle->setModel($trimmed['model']);
        if (isset($trimmed['vin'])) $vehicle->setVin($trimmed['vin']);
        if (isset($trimmed['registrationNumber'])) {
            $vehicle->setRegistrationNumber($trimmed['registrationNumber']);
        }
        if (isset($trimmed['engineNumber'])) $vehicle->setEngineNumber($trimmed['engineNumber']);
        if (isset($trimmed['v5DocumentNumber'])) {
            $vehicle->setV5DocumentNumber($trimmed['v5DocumentNumber']);
        }
        if (isset($trimmed['vehicleColor'])) $vehicle->setVehicleColor($trimmed['vehicleColor']);
        if (isset($trimmed['securityFeatures'])) {
            $vehicle->setSecurityFeatures($trimmed['securityFeatures']);
        }
        if (isset($trimmed['depreciationMethod'])) {
            $vehicle->setDepreciationMethod($trimmed['depreciationMethod']);
        }

        // Numeric fields
        if (!empty($data['year'])) $vehicle->setYear((int)$data['year']);
        if (isset($data['purchaseCost'])) {
            $vehicle->setPurchaseCost((string)($data['purchaseCost'] ?? 0));
        }
        if (isset($data['purchaseMileage'])) {
            $vehicle->setPurchaseMileage($this->extractNumeric($data, 'purchaseMileage', true));
        }
        if (isset($data['serviceIntervalMonths'])) {
            $vehicle->setServiceIntervalMonths($this->extractNumeric($data, 'serviceIntervalMonths', true));
        }
        if (isset($data['serviceIntervalMiles'])) {
            $vehicle->setServiceIntervalMiles($this->extractNumeric($data, 'serviceIntervalMiles', true));
        }
        if (isset($data['depreciationYears'])) {
            $vehicle->setDepreciationYears($this->extractNumeric($data, 'depreciationYears', true));
        }
        if (isset($data['depreciationRate'])) {
            $vehicle->setDepreciationRate($this->extractNumeric($data, 'depreciationRate', false));
        }

        // Boolean fields
        if (isset($data['roadTaxExempt'])) {
            $vehicle->setRoadTaxExempt($this->extractBoolean($data, 'roadTaxExempt'));
        }
        if (isset($data['motExempt'])) {
            $vehicle->setMotExempt($this->extractBoolean($data, 'motExempt'));
        }

        // JSON data
        if (!empty($data['vinDecodedData'])) {
            $vehicle->setVinDecodedData($data['vinDecodedData']);
        }

        // Status with validation
        if (!empty($data['status'])) {
            $allowed = ['Live', 'Sold', 'Scrapped', 'Exported'];
            $status = (string)$data['status'];
            if (in_array($status, $allowed, true)) {
                $vehicle->setStatus($status);
            }
        }
    }

    private function resolveVehicleMakeModel(
        Vehicle $vehicle,
        array $vehicleData,
        VehicleType $vehicleType
    ): void {
        $makeName = $this->trimString($vehicleData['make']);
        if (!$makeName) {
            return;
        }

        $vehicleMake = $this->entityManager->getRepository(VehicleMake::class)
            ->findOneBy(['name' => $makeName, 'vehicleType' => $vehicleType]);

        if (!$vehicleMake) {
            $vehicleMake = new VehicleMake();
            $vehicleMake->setName($makeName);
            $vehicleMake->setVehicleType($vehicleType);
            $this->entityManager->persist($vehicleMake);
            $this->entityManager->flush();
        }

        if (!empty($vehicleData['model']) && !empty($vehicleData['year'])) {
            $modelName = $this->trimString($vehicleData['model']);
            $year = (int)$vehicleData['year'];
            
            $vehicleModel = $this->entityManager->getRepository(VehicleModel::class)
                ->findOneBy([
                    'name' => $modelName,
                    'make' => $vehicleMake,
                    'startYear' => $year
                ]);

            if (!$vehicleModel) {
                $vehicleModel = new VehicleModel();
                $vehicleModel->setName($modelName);
                $vehicleModel->setMake($vehicleMake);
                $vehicleModel->setStartYear($year);
                $vehicleModel->setEndYear($year);
                $this->entityManager->persist($vehicleModel);
                $this->entityManager->flush();
            }
        }
    }

    private function deserializeAttachment(
        array $attachmentData,
        ?string $zipExtractDir,
        User $user,
        ?string $vehicleReg = null
    ): ?Attachment {
        if (empty($attachmentData['filename'])) {
            return null;
        }

        $attachment = new Attachment();
        $attachment->setUser($user);
        
        if (!empty($attachmentData['originalFilename'])) {
            $attachment->setOriginalFilename($attachmentData['originalFilename']);
        }
        
        if (!empty($attachmentData['mimeType'])) {
            $attachment->setMimeType($attachmentData['mimeType']);
        }
        
        if (isset($attachmentData['fileSize'])) {
            $attachment->setFileSize((int)$attachmentData['fileSize']);
        }
        
        if (!empty($attachmentData['description'])) {
            $attachment->setDescription($attachmentData['description']);
        }
        
        if (!empty($attachmentData['uploadedAt'])) {
            try {
                $attachment->setUploadedAt(new \DateTime($attachmentData['uploadedAt']));
            } catch (\Exception $e) {
                $attachment->setUploadedAt(new \DateTime());
            }
        } else {
            $attachment->setUploadedAt(new \DateTime());
        }

        // Handle file copying from ZIP
        if ($zipExtractDir) {
            $sourcePath = $zipExtractDir . '/' . $attachmentData['filename'];
            if (file_exists($sourcePath)) {
                $uploadsDir = $this->projectDir . '/uploads/attachments';
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0777, true);
                }
                
                $slugger = new AsciiSlugger();
                $safeFilename = $slugger->slug(pathinfo($attachment->getOriginalFilename() ?? 'file', PATHINFO_FILENAME));
                $newFilename = $safeFilename . '-' . uniqid() . '.' . pathinfo($attachment->getOriginalFilename() ?? 'file', PATHINFO_EXTENSION);
                
                $destPath = $uploadsDir . '/' . $newFilename;
                if (copy($sourcePath, $destPath)) {
                    $attachment->setFilename($newFilename);
                }
            }
        }
        
        return $attachment;
    }

    // Continue with helper methods...
    // Will add comprehensive import logic in following updates
}
