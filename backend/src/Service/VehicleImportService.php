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

        // Note: Service records and MOT records will be added in next iteration
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
        // Implementation will be added
        // This handles standalone parts (not linked to service or MOT)
    }

    private function importConsumables(
        Vehicle $vehicle,
        array $consumablesData,
        User $user,
        ?string $zipExtractDir,
        array &$consumableImportMap
    ): void {
        // Implementation will be added
        // This handles standalone consumables (not linked to service or MOT)
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

    // Continue with helper methods...
    // Will add comprehensive import logic in following updates
}
