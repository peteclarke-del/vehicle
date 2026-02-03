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
use App\Service\Trait\EntityHydratorTrait;
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

            // Flush after first pass
            if (!$dryRun) {
                $this->entityManager->flush();
            }

            // Second pass: import related entities
            foreach ($data as $index => $vehicleData) {
                $regNum = $vehicleData['registrationNumber'] ?? null;
                if (!$regNum || !isset($stats['vehicleMap'][$regNum])) {
                    continue;
                }
                
                $vehicle = $stats['vehicleMap'][$regNum];
                
                try {
                    $this->processRelatedEntities(
                        $vehicle,
                        $vehicleData,
                        $user,
                        $zipExtractDir,
                        $partImportMap,
                        $consumableImportMap,
                        $stats
                    );
                } catch (\Exception $e) {
                    $this->logger->error('[import] Related entities import failed', [
                        'index' => $index,
                        'registration' => $regNum,
                        'error' => $e->getMessage()
                    ]);
                    $stats['errors'][] = "Related entities for vehicle at index $index: " . $e->getMessage();
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

    private function processRelatedEntities(
        Vehicle $vehicle,
        array $vehicleData,
        User $user,
        ?string $zipExtractDir,
        array &$partImportMap,
        array &$consumableImportMap,
        array &$stats
    ): void {
        // Import specification
        if (!empty($vehicleData['specification']) && is_array($vehicleData['specification'])) {
            $this->importSpecification($vehicle, $vehicleData['specification']);
        }

        // Import status history
        if (!empty($vehicleData['statusHistory']) && is_array($vehicleData['statusHistory'])) {
            $this->importStatusHistory($vehicle, $vehicleData['statusHistory'], $user);
        }

        // Import fuel records
        if (!empty($vehicleData['fuelRecords']) && is_array($vehicleData['fuelRecords'])) {
            $this->importFuelRecords($vehicle, $vehicleData['fuelRecords'], $user, $zipExtractDir);
        }

        // Import standalone parts (not linked to service or MOT)
        if (!empty($vehicleData['parts']) && is_array($vehicleData['parts'])) {
            $this->importParts($vehicle, $vehicleData['parts'], $user, $zipExtractDir, $partImportMap);
        }

        // Import standalone consumables (not linked to service or MOT)
        if (!empty($vehicleData['consumables']) && is_array($vehicleData['consumables'])) {
            $this->importConsumables($vehicle, $vehicleData['consumables'], $user, $zipExtractDir, $consumableImportMap);
        }

        // Import todos
        if (!empty($vehicleData['todos']) && is_array($vehicleData['todos'])) {
            $this->importTodos($vehicle, $vehicleData['todos']);
        }

        // Import attachments
        if (!empty($vehicleData['attachments']) && is_array($vehicleData['attachments'])) {
            $this->importAttachments($vehicle, $vehicleData['attachments'], $user, $zipExtractDir);
        }

        // Import vehicle images
        if (!empty($vehicleData['vehicleImages']) && is_array($vehicleData['vehicleImages'])) {
            $this->importVehicleImages($vehicle, $vehicleData['vehicleImages'], $user, $zipExtractDir);
        }

        // Import insurance records
        if (!empty($vehicleData['insuranceRecords']) && is_array($vehicleData['insuranceRecords'])) {
            $this->importInsuranceRecords($vehicle, $vehicleData['insuranceRecords'], $user);
        }

        // Import road tax records
        if (!empty($vehicleData['roadTaxRecords']) && is_array($vehicleData['roadTaxRecords'])) {
            $this->importRoadTaxRecords($vehicle, $vehicleData['roadTaxRecords']);
        }

        // Import service records
        if (!empty($vehicleData['serviceRecords']) && is_array($vehicleData['serviceRecords'])) {
            $this->importServiceRecords($vehicle, $vehicleData['serviceRecords'], $user, $zipExtractDir, $partImportMap, $consumableImportMap);
        }

        // Import MOT records
        if (!empty($vehicleData['motRecords']) && is_array($vehicleData['motRecords'])) {
            $this->importMotRecords($vehicle, $vehicleData['motRecords'], $user, $zipExtractDir, $partImportMap, $consumableImportMap);
        }
    }

    private function importSpecification(Vehicle $vehicle, array $specData): void
    {
        $spec = $this->entityManager->getRepository(\App\Entity\Specification::class)
            ->findOneBy(['vehicle' => $vehicle]);
        
        if (!$spec) {
            $spec = new \App\Entity\Specification();
            $spec->setVehicle($vehicle);
        }

        // Trim all string fields
        $stringFields = [
            'engineType', 'displacement', 'power', 'torque', 'compression',
            'bore', 'stroke', 'fuelSystem', 'cooling', 'sparkplugType',
            'coolantType', 'coolantCapacity', 'gearbox', 'transmission',
            'finalDrive', 'clutch', 'engineOilType', 'engineOilCapacity',
            'transmissionOilType', 'transmissionOilCapacity',
            'middleDriveOilType', 'middleDriveOilCapacity', 'frame',
            'frontSuspension', 'rearSuspension', 'staticSagFront', 'staticSagRear',
            'frontBrakes', 'rearBrakes', 'frontTyre', 'rearTyre',
            'frontTyrePressure', 'rearTyrePressure', 'dryWeight', 'wetWeight',
            'fuelCapacity', 'topSpeed', 'additionalInfo', 'sourceUrl'
        ];

        $trimmed = $this->trimArrayValues($specData, $stringFields);

        // Set all fields
        foreach ($trimmed as $field => $value) {
            $setter = 'set' . ucfirst($field);
            if (method_exists($spec, $setter)) {
                $spec->$setter($value);
            }
        }

        // Handle date fields
        if (!empty($specData['scrapedAt'])) {
            try {
                $spec->setScrapedAt(new \DateTime($specData['scrapedAt']));
            } catch (\Exception $e) {
                // Ignore invalid date
            }
        }

        $this->entityManager->persist($spec);
    }

    private function importStatusHistory(Vehicle $vehicle, array $historyData, User $user): void
    {
        foreach ($historyData as $h) {
            try {
                $history = new \App\Entity\VehicleStatusHistory();
                $history->setVehicle($vehicle);

                if (!empty($h['userEmail'])) {
                    $u = $this->entityManager->getRepository(User::class)
                        ->findOneBy(['email' => $h['userEmail']]);
                    if ($u) {
                        $history->setUser($u);
                    }
                }

                if (!empty($h['oldStatus'])) {
                    $history->setOldStatus($h['oldStatus']);
                }
                if (!empty($h['newStatus'])) {
                    $history->setNewStatus($h['newStatus']);
                }
                if (!empty($h['notes'])) {
                    $history->setNotes($h['notes']);
                }

                $dateFields = ['changeDate', 'createdAt'];
                $dates = $this->hydrateDates($h, $dateFields);
                
                if (isset($dates['changeDate'])) {
                    $history->setChangeDate($dates['changeDate']);
                }
                if (isset($dates['createdAt'])) {
                    $history->setCreatedAt($dates['createdAt']);
                }

                $this->entityManager->persist($history);
            } catch (\Exception $e) {
                $this->logger->error('[import] Failed to import status history', [
                    'vehicle' => $vehicle->getRegistrationNumber(),
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function importFuelRecords(
        Vehicle $vehicle,
        array $fuelData,
        User $user,
        ?string $zipExtractDir
    ): void {
        foreach ($fuelData as $fuelRecord) {
            $record = new FuelRecord();
            $record->setVehicle($vehicle);

            if (!empty($fuelRecord['date'])) {
                try {
                    $record->setDate(new \DateTime($fuelRecord['date']));
                } catch (\Exception $e) {
                    // Ignore
                }
            }

            $numericFields = ['litres', 'cost', 'mileage'];
            foreach ($numericFields as $field) {
                if (isset($fuelRecord[$field])) {
                    $value = $this->extractNumeric($fuelRecord, $field, false);
                    $setter = 'set' . ucfirst($field);
                    $record->$setter($value);
                }
            }

            $stringFields = ['fuelType', 'station', 'notes'];
            $trimmed = $this->trimArrayValues($fuelRecord, $stringFields);
            
            if (isset($trimmed['fuelType'])) $record->setFuelType($trimmed['fuelType']);
            if (isset($trimmed['station'])) $record->setStation($trimmed['station']);
            if (isset($trimmed['notes'])) $record->setNotes($trimmed['notes']);

            // Handle receipt attachment
            if (isset($fuelRecord['receiptAttachment']) && is_array($fuelRecord['receiptAttachment'])) {
                $att = $this->deserializeAttachment(
                    $fuelRecord['receiptAttachment'],
                    $zipExtractDir,
                    $user,
                    $vehicle->getRegistrationNumber()
                );
                if ($att) {
                    $att->setEntityType('fuel');
                    $att->setVehicle($vehicle);
                    $this->entityManager->persist($att);
                    $record->setReceiptAttachment($att);
                }
            }

            if (!empty($fuelRecord['createdAt'])) {
                try {
                    $record->setCreatedAt(new \DateTime($fuelRecord['createdAt']));
                } catch (\Exception $e) {
                    // Ignore
                }
            }

            $this->entityManager->persist($record);
        }
    }

    private function importParts(
        Vehicle $vehicle,
        array $partsData,
        User $user,
        ?string $zipExtractDir,
        array &$partImportMap
    ): void {
        foreach ($partsData as $partData) {
            $part = new Part();
            $part->setVehicle($vehicle);

            // Ensure non-nullable purchaseDate has a default
            if (empty($partData['purchaseDate'])) {
                $part->setPurchaseDate(new \DateTime());
            }

            // String fields
            $stringFields = [
                'name', 'description', 'partNumber', 'manufacturer', 
                'supplier', 'notes', 'productUrl', 'sku', 'imageUrl'
            ];
            $trimmed = $this->trimArrayValues($partData, $stringFields);
            
            if (isset($trimmed['name'])) $part->setName($trimmed['name']);
            if (isset($trimmed['description'])) $part->setDescription($trimmed['description']);
            if (isset($trimmed['partNumber'])) $part->setPartNumber($trimmed['partNumber']);
            if (isset($trimmed['manufacturer'])) $part->setManufacturer($trimmed['manufacturer']);
            if (isset($trimmed['supplier'])) $part->setSupplier($trimmed['supplier']);
            if (isset($trimmed['notes'])) $part->setNotes($trimmed['notes']);
            if (isset($trimmed['productUrl'])) $part->setProductUrl($trimmed['productUrl']);
            if (isset($trimmed['sku'])) $part->setSku($trimmed['sku']);
            if (isset($trimmed['imageUrl'])) $part->setImageUrl($trimmed['imageUrl']);

            // Numeric fields
            if (isset($partData['price'])) {
                $part->setPrice($this->extractNumeric($partData, 'price', false));
            }
            if (isset($partData['quantity'])) {
                $part->setQuantity($this->extractNumeric($partData, 'quantity', true));
            }
            if (isset($partData['warrantyMonths'])) {
                $part->setWarranty($this->extractNumeric($partData, 'warrantyMonths', true));
            }
            if (isset($partData['mileageAtInstallation'])) {
                $part->setMileageAtInstallation($this->extractNumeric($partData, 'mileageAtInstallation', true));
            }
            if (isset($partData['cost'])) {
                $part->setCost((string)$partData['cost']);
            }

            // Date fields
            $dateFields = ['purchaseDate', 'installationDate', 'createdAt'];
            $dates = $this->hydrateDates($partData, $dateFields);
            
            if (isset($dates['purchaseDate'])) $part->setPurchaseDate($dates['purchaseDate']);
            if (isset($dates['installationDate'])) $part->setInstallationDate($dates['installationDate']);
            if (isset($dates['createdAt'])) $part->setCreatedAt($dates['createdAt']);

            // Boolean fields
            if (isset($partData['includedInServiceCost'])) {
                $part->setIncludedInServiceCost($this->extractBoolean($partData, 'includedInServiceCost'));
            }

            // Resolve part category
            $this->resolvePartCategory($part, $partData, $vehicle);

            // Handle receipt attachment
            if (isset($partData['receiptAttachment']) && is_array($partData['receiptAttachment'])) {
                $att = $this->deserializeAttachment(
                    $partData['receiptAttachment'],
                    $zipExtractDir,
                    $user,
                    $vehicle->getRegistrationNumber()
                );
                if ($att) {
                    $att->setEntityType('part');
                    $att->setVehicle($vehicle);
                    $this->entityManager->persist($att);
                    $part->setReceiptAttachment($att);
                }
            }

            $this->entityManager->persist($part);

            // Store in map for later reference
            if (isset($partData['id']) && is_numeric($partData['id'])) {
                $partImportMap[(int)$partData['id']] = $part;
            }
        }
    }

    private function importConsumables(
        Vehicle $vehicle,
        array $consumablesData,
        User $user,
        ?string $zipExtractDir,
        array &$consumableImportMap
    ): void {
        foreach ($consumablesData as $consumableData) {
            if (empty($consumableData['consumableType'])) {
                continue;
            }

            // Resolve or create consumable type
            $consumableType = $this->resolveConsumableType(
                $consumableData['consumableType'],
                $vehicle->getVehicleType()
            );

            $consumable = new Consumable();
            $consumable->setVehicle($vehicle);
            $consumable->setConsumableType($consumableType);

            // String fields (note: 'name' maps to 'description' in entity)
            $stringFields = ['name', 'brand', 'partNumber', 'supplier', 'notes', 'productUrl'];
            $trimmed = $this->trimArrayValues($consumableData, $stringFields);
            
            if (isset($trimmed['name'])) {
                $consumable->setDescription($trimmed['name']);
            }
            if (isset($trimmed['brand'])) $consumable->setBrand($trimmed['brand']);
            if (isset($trimmed['partNumber'])) $consumable->setPartNumber($trimmed['partNumber']);
            if (isset($trimmed['supplier'])) $consumable->setSupplier($trimmed['supplier']);
            if (isset($trimmed['notes'])) $consumable->setNotes($trimmed['notes']);
            if (isset($trimmed['productUrl'])) $consumable->setProductUrl($trimmed['productUrl']);

            // Numeric fields
            if (isset($consumableData['replacementIntervalMiles'])) {
                $consumable->setReplacementInterval(
                    $this->extractNumeric($consumableData, 'replacementIntervalMiles', true)
                );
            }
            if (isset($consumableData['nextReplacementMileage'])) {
                $consumable->setNextReplacementMileage(
                    $this->extractNumeric($consumableData, 'nextReplacementMileage', true)
                );
            }
            if (isset($consumableData['mileageAtChange'])) {
                $consumable->setMileageAtChange(
                    $this->extractNumeric($consumableData, 'mileageAtChange', true)
                );
            }
            if (isset($consumableData['quantity'])) {
                $consumable->setQuantity($this->extractNumeric($consumableData, 'quantity', false));
            }
            if (isset($consumableData['cost'])) {
                $consumable->setCost($this->extractNumeric($consumableData, 'cost', false));
            }

            // Date fields
            $dateFields = ['lastChanged', 'createdAt', 'updatedAt'];
            $dates = $this->hydrateDates($consumableData, $dateFields);
            
            if (isset($dates['lastChanged'])) $consumable->setLastChanged($dates['lastChanged']);
            if (isset($dates['createdAt'])) $consumable->setCreatedAt($dates['createdAt']);
            if (isset($dates['updatedAt'])) $consumable->setUpdatedAt($dates['updatedAt']);

            // Boolean fields
            if (isset($consumableData['includedInServiceCost'])) {
                $consumable->setIncludedInServiceCost(
                    $this->extractBoolean($consumableData, 'includedInServiceCost')
                );
            }

            // Handle receipt attachment
            if (isset($consumableData['receiptAttachment']) && is_array($consumableData['receiptAttachment'])) {
                $att = $this->deserializeAttachment(
                    $consumableData['receiptAttachment'],
                    $zipExtractDir,
                    $user,
                    $vehicle->getRegistrationNumber()
                );
                if ($att) {
                    $att->setEntityType('consumable');
                    $att->setVehicle($vehicle);
                    $this->entityManager->persist($att);
                    $consumable->setReceiptAttachment($att);
                }
            }

            $this->entityManager->persist($consumable);

            // Store in map for later reference
            if (isset($consumableData['id']) && is_numeric($consumableData['id'])) {
                $consumableImportMap[(int)$consumableData['id']] = $consumable;
            }
        }
    }

    private function resolvePartCategory(Part $part, array $partData, Vehicle $vehicle): void
    {
        $partCategory = null;

        // Try to find by ID first
        if (!empty($partData['partCategoryId']) && is_numeric($partData['partCategoryId'])) {
            $partCategory = $this->entityManager->getRepository(PartCategory::class)
                ->find((int)$partData['partCategoryId']);
        }

        // Try to find by name
        if (!$partCategory && !empty($partData['partCategory'])) {
            $pcName = $this->trimString($partData['partCategory']);
            if ($pcName) {
                $vehicleType = $vehicle->getVehicleType();
                
                // Try with vehicle type first
                if ($vehicleType) {
                    $partCategory = $this->entityManager->getRepository(PartCategory::class)
                        ->findOneBy(['name' => $pcName, 'vehicleType' => $vehicleType]);
                }
                
                // Try without vehicle type
                if (!$partCategory) {
                    $partCategory = $this->entityManager->getRepository(PartCategory::class)
                        ->findOneBy(['name' => $pcName]);
                }
                
                // Create if not found
                if (!$partCategory) {
                    $partCategory = new PartCategory();
                    $partCategory->setName($pcName);
                    if ($vehicleType) {
                        $partCategory->setVehicleType($vehicleType);
                    }
                    $this->entityManager->persist($partCategory);
                    $this->entityManager->flush();
                }
            }
        }

        if ($partCategory) {
            $part->setPartCategory($partCategory);
        }
    }

    private function resolveConsumableType(string $typeName, ?VehicleType $vehicleType): ConsumableType
    {
        $typeName = $this->trimString($typeName);
        
        $consumableType = $this->entityManager->getRepository(ConsumableType::class)
            ->findOneBy(['name' => $typeName]);

        if (!$consumableType) {
            $consumableType = new ConsumableType();
            $consumableType->setName($typeName);
            if ($vehicleType) {
                $consumableType->setVehicleType($vehicleType);
            }
            $this->entityManager->persist($consumableType);
            $this->entityManager->flush();
        }

        return $consumableType;
    }

    private function importTodos(Vehicle $vehicle, array $todosData): void
    {
        foreach ($todosData as $todoData) {
            $todo = new Todo();
            $todo->setVehicle($vehicle);

            if (!empty($todoData['title'])) {
                $todo->setTitle($this->trimString($todoData['title']));
            }
            if (!empty($todoData['description'])) {
                $todo->setDescription($this->trimString($todoData['description']));
            }
            if (isset($todoData['isCompleted'])) {
                $todo->setIsCompleted($this->extractBoolean($todoData, 'isCompleted'));
            }
            if (isset($todoData['priority'])) {
                $todo->setPriority($this->extractNumeric($todoData, 'priority', true));
            }

            $dateFields = ['dueDate', 'completedAt', 'createdAt'];
            $dates = $this->hydrateDates($todoData, $dateFields);
            
            if (isset($dates['dueDate'])) $todo->setDueDate($dates['dueDate']);
            if (isset($dates['completedAt'])) $todo->setCompletedAt($dates['completedAt']);
            if (isset($dates['createdAt'])) $todo->setCreatedAt($dates['createdAt']);

            $this->entityManager->persist($todo);
        }
    }

    private function importAttachments(
        Vehicle $vehicle,
        array $attachmentsData,
        User $user,
        ?string $zipExtractDir
    ): void {
        foreach ($attachmentsData as $attachmentData) {
            $attachment = $this->deserializeAttachment($attachmentData, $zipExtractDir, $user, $vehicle->getRegistrationNumber());
            if ($attachment) {
                $attachment->setVehicle($vehicle);
                $attachment->setEntityType('vehicle');
                $this->entityManager->persist($attachment);
            }
        }
    }

    private function importVehicleImages(
        Vehicle $vehicle,
        array $imagesData,
        User $user,
        ?string $zipExtractDir
    ): void {
        foreach ($imagesData as $imageData) {
            $image = new \App\Entity\VehicleImage();
            $image->setVehicle($vehicle);

            if (!empty($imageData['caption'])) {
                $image->setCaption($this->trimString($imageData['caption']));
            }
            if (isset($imageData['displayOrder'])) {
                $image->setDisplayOrder($this->extractNumeric($imageData, 'displayOrder', true));
            }
            if (isset($imageData['isPrimary'])) {
                $image->setIsPrimary($this->extractBoolean($imageData, 'isPrimary'));
            }

            if (!empty($imageData['uploadedAt'])) {
                try {
                    $image->setUploadedAt(new \DateTime($imageData['uploadedAt']));
                } catch (\Exception $e) {
                    $image->setUploadedAt(new \DateTime());
                }
            } else {
                $image->setUploadedAt(new \DateTime());
            }

            // Handle file from ZIP
            if ($zipExtractDir && !empty($imageData['filename'])) {
                $sourcePath = $zipExtractDir . '/' . $imageData['filename'];
                if (file_exists($sourcePath)) {
                    $uploadsDir = $this->projectDir . '/uploads/vehicles';
                    if (!is_dir($uploadsDir)) {
                        mkdir($uploadsDir, 0777, true);
                    }
                    
                    $slugger = new AsciiSlugger();
                    $safeFilename = $slugger->slug(pathinfo($imageData['filename'], PATHINFO_FILENAME));
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . pathinfo($imageData['filename'], PATHINFO_EXTENSION);
                    
                    $destPath = $uploadsDir . '/' . $newFilename;
                    if (copy($sourcePath, $destPath)) {
                        $image->setFilename($newFilename);
                        if (!empty($imageData['mimeType'])) {
                            $image->setMimeType($imageData['mimeType']);
                        }
                        if (isset($imageData['fileSize'])) {
                            $image->setFileSize((int)$imageData['fileSize']);
                        }
                    }
                }
            }

            $this->entityManager->persist($image);
        }
    }

    private function importInsuranceRecords(Vehicle $vehicle, array $insuranceData, User $user): void
    {
        foreach ($insuranceData as $data) {
            $policy = new InsurancePolicy();
            $policy->setHolderId($user->getId());
            $policy->addVehicle($vehicle);

            // String fields
            $stringFields = ['provider', 'policyNumber', 'coverageType', 'excess', 'notes'];
            $trimmed = $this->trimArrayValues($data, $stringFields);
            
            if (isset($trimmed['provider'])) $policy->setProvider($trimmed['provider']);
            if (isset($trimmed['policyNumber'])) $policy->setPolicyNumber($trimmed['policyNumber']);
            if (isset($trimmed['coverageType'])) $policy->setCoverageType($trimmed['coverageType']);
            if (isset($trimmed['excess'])) $policy->setExcess($trimmed['excess']);
            if (isset($trimmed['notes'])) $policy->setNotes($trimmed['notes']);

            // Numeric fields
            if (isset($data['annualCost'])) {
                $policy->setAnnualCost($this->extractNumeric($data, 'annualCost', false));
            }
            if (isset($data['mileageLimit'])) {
                $policy->setMileageLimit($this->extractNumeric($data, 'mileageLimit', true));
            }
            if (isset($data['ncdYears'])) {
                $policy->setNcdYears($this->extractNumeric($data, 'ncdYears', true));
            }

            // Date fields
            $dateFields = ['startDate', 'expiryDate', 'createdAt'];
            $dates = $this->hydrateDates($data, $dateFields);
            
            if (isset($dates['startDate'])) $policy->setStartDate($dates['startDate']);
            if (isset($dates['expiryDate'])) $policy->setExpiryDate($dates['expiryDate']);
            if (isset($dates['createdAt'])) $policy->setCreatedAt($dates['createdAt']);

            // Boolean fields
            if (isset($data['autoRenewal'])) {
                $policy->setAutoRenewal($this->extractBoolean($data, 'autoRenewal'));
            }

            // Handle multiple vehicles if provided
            if (!empty($data['vehicleRegistrations']) && is_array($data['vehicleRegistrations'])) {
                foreach ($data['vehicleRegistrations'] as $reg) {
                    $v = $this->entityManager->getRepository(Vehicle::class)
                        ->findOneBy(['registrationNumber' => $reg, 'owner' => $user]);
                    if ($v && $v->getId() !== $vehicle->getId()) {
                        $policy->addVehicle($v);
                    }
                }
            }

            $this->entityManager->persist($policy);
        }
    }

    private function importRoadTaxRecords(Vehicle $vehicle, array $roadTaxData): void
    {
        foreach ($roadTaxData as $data) {
            $roadTax = new RoadTax();
            $roadTax->setVehicle($vehicle);

            // String fields
            $stringFields = ['frequency', 'notes'];
            $trimmed = $this->trimArrayValues($data, $stringFields);
            
            if (isset($trimmed['frequency'])) $roadTax->setFrequency($trimmed['frequency']);
            if (isset($trimmed['notes'])) $roadTax->setNotes($trimmed['notes']);

            // Numeric fields
            if (isset($data['amount'])) {
                $roadTax->setAmount($this->extractNumeric($data, 'amount', false));
            }

            // Date fields
            $dateFields = ['startDate', 'expiryDate', 'createdAt'];
            $dates = $this->hydrateDates($data, $dateFields);
            
            if (isset($dates['startDate'])) $roadTax->setStartDate($dates['startDate']);
            if (isset($dates['expiryDate'])) $roadTax->setExpiryDate($dates['expiryDate']);
            if (isset($dates['createdAt'])) $roadTax->setCreatedAt($dates['createdAt']);

            // Boolean fields
            if (isset($data['sorn'])) {
                $roadTax->setSorn($this->extractBoolean($data, 'sorn'));
            }

            $this->entityManager->persist($roadTax);
        }
    }

    private function importServiceRecords(
        Vehicle $vehicle,
        array $serviceRecordsData,
        User $user,
        ?string $zipExtractDir,
        array &$partImportMap,
        array &$consumableImportMap
    ): void {
        foreach ($serviceRecordsData as $serviceData) {
            $serviceRecord = new ServiceRecord();
            $serviceRecord->setVehicle($vehicle);
            $this->entityManager->persist($serviceRecord);

            // String fields
            $stringFields = ['serviceType', 'serviceProvider', 'workPerformed', 'notes', 'workshop'];
            $trimmed = $this->trimArrayValues($serviceData, $stringFields);
            
            if (isset($trimmed['serviceType'])) $serviceRecord->setServiceType($trimmed['serviceType']);
            if (isset($trimmed['serviceProvider'])) {
                $serviceRecord->setServiceProvider($trimmed['serviceProvider']);
            } elseif (isset($trimmed['workshop'])) {
                // Legacy: map workshop to serviceProvider
                $serviceRecord->setServiceProvider($trimmed['workshop']);
            }
            if (isset($trimmed['workPerformed'])) $serviceRecord->setWorkPerformed($trimmed['workPerformed']);
            if (isset($trimmed['notes'])) $serviceRecord->setNotes($trimmed['notes']);

            // Numeric fields
            if (isset($serviceData['laborCost'])) {
                $serviceRecord->setLaborCost($this->extractNumeric($serviceData, 'laborCost', false));
            }
            if (isset($serviceData['partsCost'])) {
                $serviceRecord->setPartsCost($this->extractNumeric($serviceData, 'partsCost', false));
            }
            if (isset($serviceData['consumablesCost'])) {
                $serviceRecord->setConsumablesCost($this->extractNumeric($serviceData, 'consumablesCost', false));
            }
            if (isset($serviceData['additionalCosts'])) {
                $serviceRecord->setAdditionalCosts($this->extractNumeric($serviceData, 'additionalCosts', false));
            }
            if (isset($serviceData['mileage'])) {
                $serviceRecord->setMileage($this->extractNumeric($serviceData, 'mileage', true));
            }
            if (isset($serviceData['nextServiceMileage'])) {
                $serviceRecord->setNextServiceMileage($this->extractNumeric($serviceData, 'nextServiceMileage', true));
            }

            // Date fields
            $dateFields = ['serviceDate', 'nextServiceDate', 'createdAt'];
            $dates = $this->hydrateDates($serviceData, $dateFields);
            
            if (isset($dates['serviceDate'])) $serviceRecord->setServiceDate($dates['serviceDate']);
            if (isset($dates['nextServiceDate'])) $serviceRecord->setNextServiceDate($dates['nextServiceDate']);
            if (isset($dates['createdAt'])) $serviceRecord->setCreatedAt($dates['createdAt']);

            // Handle receipt attachment
            if (isset($serviceData['receiptAttachment']) && is_array($serviceData['receiptAttachment'])) {
                $att = $this->deserializeAttachment(
                    $serviceData['receiptAttachment'],
                    $zipExtractDir,
                    $user,
                    $vehicle->getRegistrationNumber()
                );
                if ($att) {
                    $att->setEntityType('service');
                    $att->setVehicle($vehicle);
                    $this->entityManager->persist($att);
                    $serviceRecord->setReceiptAttachment($att);
                }
            }

            // Import service items
            if (!empty($serviceData['items']) && is_array($serviceData['items'])) {
                $this->importServiceItems(
                    $serviceRecord,
                    $serviceData['items'],
                    $vehicle,
                    $user,
                    $zipExtractDir,
                    $partImportMap,
                    $consumableImportMap
                );
            }

            $this->entityManager->persist($serviceRecord);
        }
    }

    private function importServiceItems(
        ServiceRecord $serviceRecord,
        array $items,
        Vehicle $vehicle,
        User $user,
        ?string $zipExtractDir,
        array &$partImportMap,
        array &$consumableImportMap
    ): void {
        foreach ($items as $itemData) {
            $item = new \App\Entity\ServiceItem();
            $serviceRecord->addItem($item);

            if (!empty($itemData['type'])) {
                $item->setType($itemData['type']);
            }
            if (!empty($itemData['description'])) {
                $item->setDescription($itemData['description']);
            }
            if (isset($itemData['cost'])) {
                $item->setCost($this->extractNumeric($itemData, 'cost', false));
            }
            if (isset($itemData['quantity'])) {
                $item->setQuantity($this->extractNumeric($itemData, 'quantity', true));
            }

            // Handle consumable (link existing or create new)
            if (!empty($itemData['consumable']) && is_array($itemData['consumable'])) {
                $shouldLinkExisting = isset($itemData['consumable']['includedInServiceCost']) 
                    && $itemData['consumable']['includedInServiceCost'] === false;

                if ($shouldLinkExisting && isset($itemData['consumableId']) && is_numeric($itemData['consumableId'])) {
                    $consumable = $consumableImportMap[(int)$itemData['consumableId']] ?? null;
                    if ($consumable) {
                        $item->setConsumable($consumable);
                    }
                }

                if (!$item->getConsumable()) {
                    $consumable = $this->createConsumableFromData(
                        $itemData['consumable'],
                        $vehicle,
                        $user,
                        $zipExtractDir
                    );
                    $consumable->setServiceRecord($serviceRecord);
                    $this->entityManager->persist($consumable);
                    $item->setConsumable($consumable);

                    if (isset($itemData['consumable']['id']) && is_numeric($itemData['consumable']['id'])) {
                        $consumableImportMap[(int)$itemData['consumable']['id']] = $consumable;
                    }
                }
            }

            // Handle part (link existing or create new)
            if (!empty($itemData['part']) && is_array($itemData['part'])) {
                $shouldLinkExisting = isset($itemData['part']['includedInServiceCost']) 
                    && $itemData['part']['includedInServiceCost'] === false;

                if ($shouldLinkExisting && isset($itemData['partId']) && is_numeric($itemData['partId'])) {
                    $part = $partImportMap[(int)$itemData['partId']] ?? null;
                    if ($part) {
                        $item->setPart($part);
                    }
                }

                if (!$item->getPart()) {
                    $part = $this->createPartFromData(
                        $itemData['part'],
                        $vehicle,
                        $user,
                        $zipExtractDir
                    );
                    $part->setServiceRecord($serviceRecord);
                    $this->entityManager->persist($part);
                    $item->setPart($part);

                    if (isset($itemData['part']['id']) && is_numeric($itemData['part']['id'])) {
                        $partImportMap[(int)$itemData['part']['id']] = $part;
                    }
                }
            }
        }
    }

    private function importMotRecords(
        Vehicle $vehicle,
        array $motRecordsData,
        User $user,
        ?string $zipExtractDir,
        array &$partImportMap,
        array &$consumableImportMap
    ): void {
        foreach ($motRecordsData as $motData) {
            $motRecord = new MotRecord();
            $motRecord->setVehicle($vehicle);

            // String fields
            $stringFields = ['result', 'testCenter', 'advisories', 'failures', 
                            'repairDetails', 'notes', 'motTestNumber', 'testerName'];
            $trimmed = $this->trimArrayValues($motData, $stringFields);
            
            foreach (['result', 'testCenter', 'advisories', 'failures', 'repairDetails', 
                     'notes', 'motTestNumber', 'testerName'] as $field) {
                if (isset($trimmed[$field])) {
                    $setter = 'set' . ucfirst($field);
                    $motRecord->$setter($trimmed[$field]);
                }
            }

            // Numeric fields
            if (isset($motData['testCost'])) {
                $motRecord->setTestCost($this->extractNumeric($motData, 'testCost', false));
            }
            if (isset($motData['repairCost'])) {
                $motRecord->setRepairCost($this->extractNumeric($motData, 'repairCost', false));
            }
            if (isset($motData['mileage'])) {
                $motRecord->setMileage($this->extractNumeric($motData, 'mileage', true));
            }

            // Date fields
            $dateFields = ['testDate', 'expiryDate', 'createdAt'];
            $dates = $this->hydrateDates($motData, $dateFields);
            
            if (isset($dates['testDate'])) $motRecord->setTestDate($dates['testDate']);
            if (isset($dates['expiryDate'])) $motRecord->setExpiryDate($dates['expiryDate']);
            if (isset($dates['createdAt'])) $motRecord->setCreatedAt($dates['createdAt']);

            // Boolean fields
            if (isset($motData['isRetest'])) {
                $motRecord->setIsRetest($this->extractBoolean($motData, 'isRetest'));
            }

            // Handle receipt attachment
            if (isset($motData['receiptAttachment']) && is_array($motData['receiptAttachment'])) {
                $att = $this->deserializeAttachment(
                    $motData['receiptAttachment'],
                    $zipExtractDir,
                    $user,
                    $vehicle->getRegistrationNumber()
                );
                if ($att) {
                    $att->setEntityType('mot');
                    $att->setVehicle($vehicle);
                    $this->entityManager->persist($att);
                    $motRecord->setReceiptAttachment($att);
                }
            }

            $this->entityManager->persist($motRecord);

            // Import MOT parts
            if (!empty($motData['parts']) && is_array($motData['parts'])) {
                $this->importMotParts($motRecord, $motData['parts'], $vehicle, $user, $zipExtractDir);
            }

            // Import MOT consumables
            if (!empty($motData['consumables']) && is_array($motData['consumables'])) {
                $this->importMotConsumables($motRecord, $motData['consumables'], $vehicle, $user, $zipExtractDir);
            }

            // Import MOT service records
            if (!empty($motData['serviceRecords']) && is_array($motData['serviceRecords'])) {
                $this->importMotServiceRecords($motRecord, $motData['serviceRecords'], $vehicle, $user, $zipExtractDir, $partImportMap, $consumableImportMap);
            }
        }
    }

    private function importMotParts(
        MotRecord $motRecord,
        array $partsData,
        Vehicle $vehicle,
        User $user,
        ?string $zipExtractDir
    ): void {
        foreach ($partsData as $partData) {
            $part = $this->createPartFromData($partData, $vehicle, $user, $zipExtractDir);
            $part->setMotRecord($motRecord);
            $this->entityManager->persist($part);
        }
    }

    private function importMotConsumables(
        MotRecord $motRecord,
        array $consumablesData,
        Vehicle $vehicle,
        User $user,
        ?string $zipExtractDir
    ): void {
        foreach ($consumablesData as $consumableData) {
            if (empty($consumableData['consumableType'])) {
                continue;
            }

            $consumable = $this->createConsumableFromData($consumableData, $vehicle, $user, $zipExtractDir);
            $consumable->setMotRecord($motRecord);
            $this->entityManager->persist($consumable);
        }
    }

    private function importMotServiceRecords(
        MotRecord $motRecord,
        array $serviceRecordsData,
        Vehicle $vehicle,
        User $user,
        ?string $zipExtractDir,
        array &$partImportMap,
        array &$consumableImportMap
    ): void {
        foreach ($serviceRecordsData as $serviceData) {
            $serviceRecord = new ServiceRecord();
            $serviceRecord->setVehicle($vehicle);
            $serviceRecord->setMotRecord($motRecord);
            $this->entityManager->persist($serviceRecord);

            // Use same logic as regular service records
            $stringFields = ['serviceType', 'serviceProvider', 'workPerformed', 'notes'];
            $trimmed = $this->trimArrayValues($serviceData, $stringFields);
            
            if (isset($trimmed['serviceType'])) $serviceRecord->setServiceType($trimmed['serviceType']);
            if (isset($trimmed['serviceProvider'])) $serviceRecord->setServiceProvider($trimmed['serviceProvider']);
            if (isset($trimmed['workPerformed'])) $serviceRecord->setWorkPerformed($trimmed['workPerformed']);
            if (isset($trimmed['notes'])) $serviceRecord->setNotes($trimmed['notes']);

            if (isset($serviceData['laborCost'])) {
                $serviceRecord->setLaborCost($this->extractNumeric($serviceData, 'laborCost', false));
            }
            if (isset($serviceData['mileage'])) {
                $serviceRecord->setMileage($this->extractNumeric($serviceData, 'mileage', true));
            }

            $dateFields = ['serviceDate', 'createdAt'];
            $dates = $this->hydrateDates($serviceData, $dateFields);
            if (isset($dates['serviceDate'])) $serviceRecord->setServiceDate($dates['serviceDate']);
            if (isset($dates['createdAt'])) $serviceRecord->setCreatedAt($dates['createdAt']);

            // Import service items
            if (!empty($serviceData['items']) && is_array($serviceData['items'])) {
                $this->importServiceItems(
                    $serviceRecord,
                    $serviceData['items'],
                    $vehicle,
                    $user,
                    $zipExtractDir,
                    $partImportMap,
                    $consumableImportMap
                );
            }

            $this->entityManager->persist($serviceRecord);
        }
    }

    private function createPartFromData(
        array $partData,
        Vehicle $vehicle,
        User $user,
        ?string $zipExtractDir
    ): Part {
        $part = new Part();
        $part->setVehicle($vehicle);

        // Ensure non-nullable purchaseDate
        if (empty($partData['purchaseDate'])) {
            $part->setPurchaseDate(new \DateTime());
        }

        // String fields
        $stringFields = ['name', 'description', 'partNumber', 'manufacturer', 
                        'supplier', 'notes', 'productUrl'];
        $trimmed = $this->trimArrayValues($partData, $stringFields);
        
        foreach (['name', 'description', 'partNumber', 'manufacturer', 
                 'supplier', 'notes', 'productUrl'] as $field) {
            if (isset($trimmed[$field])) {
                $setter = 'set' . ucfirst($field);
                $part->$setter($trimmed[$field]);
            }
        }

        // Numeric fields
        if (isset($partData['cost'])) $part->setCost((string)$partData['cost']);
        if (isset($partData['price'])) $part->setPrice($this->extractNumeric($partData, 'price', false));
        if (isset($partData['quantity'])) $part->setQuantity($this->extractNumeric($partData, 'quantity', true));
        if (isset($partData['mileageAtInstallation'])) {
            $part->setMileageAtInstallation($this->extractNumeric($partData, 'mileageAtInstallation', true));
        }

        // Date fields
        $dateFields = ['purchaseDate', 'installationDate', 'createdAt'];
        $dates = $this->hydrateDates($partData, $dateFields);
        if (isset($dates['purchaseDate'])) $part->setPurchaseDate($dates['purchaseDate']);
        if (isset($dates['installationDate'])) $part->setInstallationDate($dates['installationDate']);
        if (isset($dates['createdAt'])) $part->setCreatedAt($dates['createdAt']);

        // Boolean fields
        if (isset($partData['includedInServiceCost'])) {
            $part->setIncludedInServiceCost($this->extractBoolean($partData, 'includedInServiceCost'));
        }

        // Category resolution
        $this->resolvePartCategory($part, $partData, $vehicle);

        // Receipt attachment
        if (isset($partData['receiptAttachment']) && is_array($partData['receiptAttachment'])) {
            $att = $this->deserializeAttachment(
                $partData['receiptAttachment'],
                $zipExtractDir,
                $user,
                $vehicle->getRegistrationNumber()
            );
            if ($att) {
                $att->setEntityType('part');
                $att->setVehicle($vehicle);
                $this->entityManager->persist($att);
                $part->setReceiptAttachment($att);
            }
        }

        return $part;
    }

    private function createConsumableFromData(
        array $consumableData,
        Vehicle $vehicle,
        User $user,
        ?string $zipExtractDir
    ): Consumable {
        if (empty($consumableData['consumableType'])) {
            throw new ImportException('Consumable type is required');
        }

        $consumableType = $this->resolveConsumableType(
            $consumableData['consumableType'],
            $vehicle->getVehicleType()
        );

        $consumable = new Consumable();
        $consumable->setVehicle($vehicle);
        $consumable->setConsumableType($consumableType);

        // String fields (note: 'name' or 'description' map to description)
        $stringFields = ['name', 'description', 'brand', 'partNumber', 'supplier', 'notes', 'productUrl'];
        $trimmed = $this->trimArrayValues($consumableData, $stringFields);
        
        if (isset($trimmed['description'])) {
            $consumable->setDescription($trimmed['description']);
        } elseif (isset($trimmed['name'])) {
            $consumable->setDescription($trimmed['name']);
        }
        if (isset($trimmed['brand'])) $consumable->setBrand($trimmed['brand']);
        if (isset($trimmed['partNumber'])) $consumable->setPartNumber($trimmed['partNumber']);
        if (isset($trimmed['supplier'])) $consumable->setSupplier($trimmed['supplier']);
        if (isset($trimmed['notes'])) $consumable->setNotes($trimmed['notes']);
        if (isset($trimmed['productUrl'])) $consumable->setProductUrl($trimmed['productUrl']);

        // Numeric fields
        if (isset($consumableData['replacementIntervalMiles'])) {
            $consumable->setReplacementInterval(
                $this->extractNumeric($consumableData, 'replacementIntervalMiles', true)
            );
        }
        if (isset($consumableData['nextReplacementMileage'])) {
            $consumable->setNextReplacementMileage(
                $this->extractNumeric($consumableData, 'nextReplacementMileage', true)
            );
        }
        if (isset($consumableData['mileageAtChange'])) {
            $consumable->setMileageAtChange(
                $this->extractNumeric($consumableData, 'mileageAtChange', true)
            );
        }
        if (isset($consumableData['quantity'])) {
            $consumable->setQuantity($this->extractNumeric($consumableData, 'quantity', false));
        }
        if (isset($consumableData['cost'])) {
            $consumable->setCost($this->extractNumeric($consumableData, 'cost', false));
        }

        // Date fields
        $dateFields = ['lastChanged', 'createdAt', 'updatedAt'];
        $dates = $this->hydrateDates($consumableData, $dateFields);
        if (isset($dates['lastChanged'])) $consumable->setLastChanged($dates['lastChanged']);
        if (isset($dates['createdAt'])) $consumable->setCreatedAt($dates['createdAt']);
        if (isset($dates['updatedAt'])) $consumable->setUpdatedAt($dates['updatedAt']);

        // Boolean fields
        if (isset($consumableData['includedInServiceCost'])) {
            $consumable->setIncludedInServiceCost(
                $this->extractBoolean($consumableData, 'includedInServiceCost')
            );
        }

        // Receipt attachment
        if (isset($consumableData['receiptAttachment']) && is_array($consumableData['receiptAttachment'])) {
            $att = $this->deserializeAttachment(
                $consumableData['receiptAttachment'],
                $zipExtractDir,
                $user,
                $vehicle->getRegistrationNumber()
            );
            if ($att) {
                $att->setEntityType('consumable');
                $att->setVehicle($vehicle);
                $this->entityManager->persist($att);
                $consumable->setReceiptAttachment($att);
            }
        }

        return $consumable;
    }

    // Continue with helper methods...
    // Will add comprehensive import logic in following updates
}
