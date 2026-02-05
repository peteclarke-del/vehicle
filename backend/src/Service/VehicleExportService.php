<?php

declare(strict_types=1);

namespace App\Service;

use App\Config\ImportExportConfig;
use App\DTO\ExportResult;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\Specification;
use App\Entity\Attachment;
use App\Exception\ExportException;
use App\Service\Trait\EntityHydratorTrait;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Service for exporting vehicle data
 */
class VehicleExportService
{
    use EntityHydratorTrait;

    /**
     * Tracks attachment IDs that have been serialized as receiptAttachment on entities.
     * Used to prevent duplicates in the vehicle-level attachments array.
     * Reset per vehicle during export.
     */
    private array $exportedAttachmentIds = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private SluggerInterface $slugger,
        private ImportExportConfig $config,
        private string $projectDir
    ) {}

    /**
     * Export vehicles for a user
     */
    public function exportVehicles(
        User $user,
        bool $isAdmin = false,
        bool $includeAttachmentRefs = false,
        ?string $zipDir = null
    ): ExportResult {
        try {
            $startTime = microtime(true);
            $this->setupEnvironment();

            $this->logger->info('[export] JSON started', [
                'userId' => $user->getId(),
                'includeAttachmentRefs' => $includeAttachmentRefs,
                'zipDir' => $zipDir
            ]);

            // Fetch vehicle IDs with sorting
            $vehicleIds = $this->fetchVehicleIds($user, $isAdmin);
            
            $this->logger->info('[export] JSON vehicle ids loaded', [
                'count' => count($vehicleIds),
                'elapsedMs' => (int)((microtime(true) - $startTime) * 1000)
            ]);

            // Process vehicles in batches
            $result = $this->processVehicleBatches(
                $vehicleIds,
                $includeAttachmentRefs,
                $zipDir,
                $startTime
            );

            $statistics = [
                'vehicleCount' => count($result['vehicles']),
                'processingTimeSeconds' => round(microtime(true) - $startTime, 2),
                'memoryPeakMB' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ];

            $this->logger->info('[export] JSON completed', $statistics);

            return ExportResult::createSuccess(
                $result['vehicles'],
                $statistics,
                'Export completed successfully'
            );

        } catch (\Exception $e) {
            $this->logger->error('[export] Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new ExportException(
                'Export failed: ' . $e->getMessage(),
                'export',
                0,
                $e
            );
        }
    }

    /**
     * Setup environment for export
     */
    private function setupEnvironment(): void
    {
        @ini_set('max_execution_time', (string)$this->config->getMaxExecutionTime());
        @ini_set('memory_limit', $this->config->getMemoryLimitMB() . 'M');
        @set_time_limit($this->config->getMaxExecutionTime());
    }

    /**
     * Fetch sorted vehicle IDs
     */
    private function fetchVehicleIds(User $user, bool $isAdmin): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('v.id')
            ->from(Vehicle::class, 'v')
            ->leftJoin('v.vehicleType', 'vt')
            ->orderBy('vt.name', 'ASC')
            ->addOrderBy('v.name', 'ASC');

        if (!$isAdmin) {
            $qb->where('v.owner = :user')
                ->setParameter('user', $user);
        }

        return array_map(
            static fn($row) => (int)$row['id'],
            $qb->getQuery()->getScalarResult()
        );
    }

    /**
     * Process vehicles in batches
     */
    private function processVehicleBatches(
        array $vehicleIds,
        bool $includeAttachmentRefs,
        ?string $zipDir,
        float $startTime
    ): array {
        $data = [];
        $vehicleCount = 0;
        $batchSize = $this->config->getBatchSize();
        $total = count($vehicleIds);

        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $batchIds = array_slice($vehicleIds, $offset, $batchSize);
            $vehicles = $this->loadVehicleBatch($batchIds);

            $this->logger->info('[export] JSON batch loaded', [
                'offset' => $offset,
                'count' => count($vehicles)
            ]);

            foreach ($vehicles as $vehicle) {
                $vehicleCount++;

                if ($vehicleCount === 1 || ($vehicleCount % 10) === 0) {
                    $this->logger->info('[export] JSON progress', [
                        'vehicleCount' => $vehicleCount,
                        'elapsedMs' => (int)((microtime(true) - $startTime) * 1000)
                    ]);
                }

                $data[] = $this->exportVehicleData($vehicle, $includeAttachmentRefs, $zipDir);
            }

            // Memory cleanup
            if ($this->config->isMemoryCleanupEnabled() && 
                ($offset + $batchSize) % $this->config->getCleanupInterval() === 0) {
                $this->performMemoryCleanup();
            }
        }

        return ['vehicles' => $data];
    }

    /**
     * Load a batch of vehicles
     */
    private function loadVehicleBatch(array $batchIds): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('v')
            ->from(Vehicle::class, 'v')
            ->leftJoin('v.vehicleType', 'vt')
            ->where($qb->expr()->in('v.id', ':ids'))
            ->setParameter('ids', $batchIds)
            ->orderBy('vt.name', 'ASC')
            ->addOrderBy('v.name', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Export single vehicle data
     */
    private function exportVehicleData(
        Vehicle $vehicle,
        bool $includeAttachmentRefs,
        ?string $zipDir
    ): array {
        // Reset exported attachment tracking for this vehicle
        $this->exportedAttachmentIds = [];

        // Export fuel records
        $fuelRecords = $this->exportFuelRecords($vehicle, $includeAttachmentRefs, $zipDir);
        
        // Export parts
        $parts = $this->exportParts($vehicle, $includeAttachmentRefs, $zipDir);
        
        // Export consumables
        $consumables = $this->exportConsumables($vehicle, $includeAttachmentRefs, $zipDir);
        
        // Export service records
        $serviceRecordsData = $this->exportServiceRecords($vehicle, $includeAttachmentRefs, $zipDir);
        
        // Export MOT records
        $motRecordsData = $this->exportMotRecords($vehicle, $includeAttachmentRefs, $zipDir);
        
        // Export insurance records
        $insuranceRecordsData = $this->exportInsuranceRecords($vehicle);
        
        // Export road tax records
        $roadTaxRecordsData = $this->exportRoadTaxRecords($vehicle);
        
        // Export specification
        $specData = $this->exportSpecification($vehicle);
        
        // Export todos
        $todosData = $this->exportTodos($vehicle);
        
        $includeMedia = $zipDir !== null;

        // Export attachments (ZIP export only)
        $attachmentsData = $includeMedia
            ? $this->exportAttachments($vehicle, $includeAttachmentRefs, $zipDir)
            : null;
        
        // Export vehicle images (ZIP export only)
        $vehicleImages = $includeMedia
            ? $this->exportVehicleImages($vehicle, $zipDir)
            : null;

        $data = [
            'originalId' => $vehicle->getId(),
            'name' => $this->trimString($vehicle->getName()),
            'vehicleType' => $this->trimString($vehicle->getVehicleType()->getName()),
            'make' => $this->trimString($vehicle->getMake()),
            'model' => $this->trimString($vehicle->getModel()),
            'year' => $vehicle->getYear(),
            'vin' => $this->trimString($vehicle->getVin()),
            'vinDecodedData' => $vehicle->getVinDecodedData(),
            'vinDecodedAt' => $vehicle->getVinDecodedAt()?->format('c'),
            'registrationNumber' => $this->trimString($vehicle->getRegistrationNumber()),
            'engineNumber' => $this->trimString($vehicle->getEngineNumber()),
            'v5DocumentNumber' => $this->trimString($vehicle->getV5DocumentNumber()),
            'createdAt' => $vehicle->getCreatedAt()?->format('c'),
            'purchaseCost' => $vehicle->getPurchaseCost(),
            'purchaseDate' => $vehicle->getPurchaseDate()?->format('Y-m-d'),
            'purchaseMileage' => $vehicle->getPurchaseMileage(),
            'status' => $vehicle->getStatus(),
            'statusHistory' => $this->exportStatusHistory($vehicle),
            'roadTaxExempt' => $vehicle->getRoadTaxExempt() ?? false,
            'motExempt' => $vehicle->getMotExempt() ?? false,
            'securityFeatures' => $this->trimString($vehicle->getSecurityFeatures()),
            'vehicleColor' => $this->trimString($vehicle->getVehicleColor()),
            'serviceIntervalMonths' => $vehicle->getServiceIntervalMonths(),
            'serviceIntervalMiles' => $vehicle->getServiceIntervalMiles(),
            'depreciationMethod' => $vehicle->getDepreciationMethod(),
            'depreciationYears' => $vehicle->getDepreciationYears(),
            'depreciationRate' => $vehicle->getDepreciationRate(),
            'fuelRecords' => $fuelRecords,
            'parts' => $parts,
            'consumables' => $consumables,
            'serviceRecords' => $serviceRecordsData,
            'motRecords' => $motRecordsData,
            'specification' => $specData,
            'insuranceRecords' => $insuranceRecordsData,
            'roadTaxRecords' => $roadTaxRecordsData,
            'todos' => $todosData,
        ];

        if ($includeMedia) {
            if ($includeAttachmentRefs) {
                $data['attachments'] = $attachmentsData ?? [];
            }
            $data['vehicleImages'] = $vehicleImages ?? [];
        }

        return $data;
    }

    /**
     * Serialize attachment data for export
     */
    private function serializeAttachment(?Attachment $attachment, ?string $zipDir): ?array
    {
        if (!$attachment) {
            return null;
        }

        if (!$attachment->getFilename()) {
            $this->logger->warning('[export] Attachment has no filename', ['id' => $attachment->getId()]);
            return null;
        }

        // Track this attachment as exported (for deduplication)
        if ($attachment->getId()) {
            $this->exportedAttachmentIds[$attachment->getId()] = true;
        }

        $attachmentData = [
            'filename' => $attachment->getFilename(),
            'originalFilename' => $attachment->getOriginalName(),
            'storagePath' => $attachment->getStoragePath(),
            'mimetype' => $attachment->getMimeType(),
            'filesize' => $attachment->getFileSize(),
            'uploadedAt' => $attachment->getUploadedAt()?->format('c'),
            'category' => $attachment->getCategory(),
            'description' => $attachment->getDescription(),
            'entityType' => $attachment->getEntityType(),
            'entityId' => $attachment->getEntityId(),
            'originalId' => $attachment->getId(),
        ];

        // Copy the physical file to ZIP directory (only if zipDir provided)
        if ($zipDir) {
            $storagePath = $attachment->getStoragePath() ?: ('attachments/' . $attachment->getFilename());
            $sourcePath = $this->projectDir . '/uploads/' . ltrim($storagePath, '/');
            
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
                    
                    $attachmentData['exportFilename'] = $safeName;
                    $this->logger->debug('[export] Copied attachment file', [
                        'source' => $sourcePath,
                        'target' => $targetPath
                ]);
            } catch (\Exception $e) {
                $this->logger->error('[export] Failed to copy attachment', [
                    'source' => $sourcePath,
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            $this->logger->warning('[export] Attachment file not found', ['path' => $sourcePath]);
        }
        }

        return $attachmentData;
    }

    /**
     * Export fuel records
     */
    private function exportFuelRecords(Vehicle $vehicle, bool $includeAttachmentRefs, ?string $zipDir): array
    {
        $fuelRecords = [];
        foreach ($vehicle->getFuelRecords() as $fuelRecord) {
            $record = [
                'date' => $fuelRecord->getDate()?->format('Y-m-d'),
                'litres' => $fuelRecord->getLitres(),
                'cost' => $fuelRecord->getCost(),
                'mileage' => $fuelRecord->getMileage(),
                'fuelType' => $fuelRecord->getFuelType(),
                'station' => $fuelRecord->getStation(),
                'notes' => $fuelRecord->getNotes(),
                'createdAt' => $fuelRecord->getCreatedAt()?->format('c'),
            ];
            if ($includeAttachmentRefs) {
                $record['receiptAttachment'] = $this->serializeAttachment($fuelRecord->getReceiptAttachment(), $zipDir);
            }
            $fuelRecords[] = $record;
        }
        return $fuelRecords;
    }

    /**
     * Export standalone parts
     */
    private function exportParts(Vehicle $vehicle, bool $includeAttachmentRefs, ?string $zipDir): array
    {
        $parts = [];
        foreach ($vehicle->getParts() as $part) {
            // Skip parts already linked to an MOT or ServiceRecord
            if ($part->getMotRecord() || $part->getServiceRecord()) {
                continue;
            }
            $partData = [
                'id' => $part->getId(),
                'name' => $part->getName() ?: $part->getDescription(),
                'price' => $part->getPrice(),
                'quantity' => $part->getQuantity(),
                'sku' => $part->getSku(),
                'warrantyMonths' => $part->getWarranty(),
                'imageUrl' => $part->getImageUrl(),
                'purchaseDate' => $part->getPurchaseDate()?->format('Y-m-d'),
                'description' => $part->getDescription(),
                'partNumber' => $part->getPartNumber(),
                'manufacturer' => $part->getManufacturer(),
                'supplier' => $part->getSupplier(),
                'cost' => $part->getCost(),
                'partCategory' => $part->getPartCategory()?->getName(),
                'partCategoryId' => $part->getPartCategory()?->getId(),
                'installationDate' => $part->getInstallationDate()?->format('Y-m-d'),
                'mileageAtInstallation' => $part->getMileageAtInstallation(),
                'notes' => $part->getNotes(),
                'productUrl' => $part->getProductUrl(),
                'includedInServiceCost' => $part->isIncludedInServiceCost(),
                'createdAt' => $part->getCreatedAt()?->format('c'),
            ];
            if ($includeAttachmentRefs) {
                $partData['receiptAttachment'] = $this->serializeAttachment($part->getReceiptAttachment(), $zipDir);
            }
            $parts[] = $partData;
        }
        return $parts;
    }

    /**
     * Export standalone consumables
     */
    private function exportConsumables(Vehicle $vehicle, bool $includeAttachmentRefs, ?string $zipDir): array
    {
        $consumables = [];
        foreach ($vehicle->getConsumables() as $consumable) {
            // Skip consumables already linked to an MOT or ServiceRecord
            if ($consumable->getMotRecord() || $consumable->getServiceRecord()) {
                continue;
            }
            $consumableData = [
                'id' => $consumable->getId(),
                'name' => $consumable->getDescription(),
                'description' => $consumable->getDescription(),
                'brand' => $consumable->getBrand(),
                'partNumber' => $consumable->getPartNumber(),
                'supplier' => $consumable->getSupplier(),
                'replacementIntervalMiles' => $consumable->getReplacementIntervalMiles(),
                'nextReplacementMileage' => $consumable->getNextReplacementMileage(),
                'consumableType' => $consumable->getConsumableType()->getName(),
                'quantity' => $consumable->getQuantity(),
                'lastChanged' => $consumable->getLastChanged()?->format('Y-m-d'),
                'mileageAtChange' => $consumable->getMileageAtChange(),
                'cost' => $consumable->getCost(),
                'notes' => $consumable->getNotes(),
                'productUrl' => $consumable->getProductUrl(),
                'createdAt' => $consumable->getCreatedAt()?->format('c'),
                'updatedAt' => $consumable->getUpdatedAt()?->format('c'),
                'includedInServiceCost' => $consumable->isIncludedInServiceCost(),
            ];
            if ($includeAttachmentRefs) {
                $consumableData['receiptAttachment'] = $this->serializeAttachment($consumable->getReceiptAttachment(), $zipDir);
            }
            $consumables[] = $consumableData;
        }
        return $consumables;
    }

    /**
     * Export service records
     */
    private function exportServiceRecords(Vehicle $vehicle, bool $includeAttachmentRefs, ?string $zipDir): array
    {
        $serviceRecordsData = [];
        foreach ($vehicle->getServiceRecords() as $serviceRecord) {
            // Skip service records linked to an MOT
            if ($serviceRecord->getMotRecord()) {
                continue;
            }
            $serviceData = $this->exportServiceRecordData($serviceRecord, $includeAttachmentRefs, $zipDir);
            $serviceRecordsData[] = $serviceData;
        }
        return $serviceRecordsData;
    }

    /**
     * Export a single service record
     */
    private function exportServiceRecordData($serviceRecord, bool $includeAttachmentRefs, ?string $zipDir): array
    {
        $data = [
            'serviceDate' => $serviceRecord->getServiceDate()?->format('Y-m-d'),
            'serviceType' => $serviceRecord->getServiceType(),
            'laborCost' => $serviceRecord->getLaborCost(),
            'partsCost' => $serviceRecord->getPartsCost(),
            'consumablesCost' => $serviceRecord->getConsumablesCost(),
            'mileage' => $serviceRecord->getMileage(),
            'serviceProvider' => $serviceRecord->getServiceProvider(),
            'additionalCosts' => $serviceRecord->getAdditionalCosts(),
            'nextServiceDate' => $serviceRecord->getNextServiceDate()?->format('Y-m-d'),
            'nextServiceMileage' => $serviceRecord->getNextServiceMileage(),
            'workPerformed' => $serviceRecord->getWorkPerformed(),
            'notes' => $serviceRecord->getNotes(),
            'includedInMotCost' => $serviceRecord->isIncludedInMotCost(),
            'includesMotTestCost' => $serviceRecord->includesMotTestCost(),
            'items' => $this->exportServiceItems($serviceRecord, $includeAttachmentRefs, $zipDir),
            'createdAt' => $serviceRecord->getCreatedAt()?->format('c'),
        ];
        
        if ($includeAttachmentRefs) {
            $data['receiptAttachment'] = $this->serializeAttachment($serviceRecord->getReceiptAttachment(), $zipDir);
        }
        
        return $data;
    }

    /**
     * Export service items
     */
    private function exportServiceItems($serviceRecord, bool $includeAttachmentRefs, ?string $zipDir): array
    {
        return array_map(function ($it) use ($includeAttachmentRefs, $zipDir) {
            $part = $it->getPart();
            $consumable = $it->getConsumable();

            $partData = null;
            if ($part) {
                $partData = [
                    'name' => $part->getName() ?: $part->getDescription(),
                    'price' => $part->getPrice(),
                    'quantity' => $part->getQuantity(),
                    'id' => $part->getId(),
                    'description' => $part->getDescription(),
                    'partNumber' => $part->getPartNumber(),
                    'manufacturer' => $part->getManufacturer(),
                    'supplier' => $part->getSupplier(),
                    'cost' => $part->getCost(),
                    'partCategory' => $part->getPartCategory()?->getName(),
                    'partCategoryId' => $part->getPartCategory()?->getId(),
                    'purchaseDate' => $part->getPurchaseDate()?->format('Y-m-d'),
                    'installationDate' => $part->getInstallationDate()?->format('Y-m-d'),
                    'mileageAtInstallation' => $part->getMileageAtInstallation(),
                    'notes' => $part->getNotes(),
                    'productUrl' => $part->getProductUrl(),
                    'includedInServiceCost' => $part->isIncludedInServiceCost(),
                    'createdAt' => $part->getCreatedAt()?->format('c'),
                ];
                if ($includeAttachmentRefs) {
                    $partData['receiptAttachment'] = $this->serializeAttachment($part->getReceiptAttachment(), $zipDir);
                }
            }

            $consumableData = null;
            if ($consumable) {
                $consumableData = [
                    'id' => $consumable->getId(),
                    'name' => $consumable->getDescription(),
                    'consumableType' => $consumable->getConsumableType()->getName(),
                    'description' => $consumable->getDescription(),
                    'brand' => $consumable->getBrand(),
                    'partNumber' => $consumable->getPartNumber(),
                    'supplier' => $consumable->getSupplier(),
                    'replacementIntervalMiles' => $consumable->getReplacementIntervalMiles(),
                    'nextReplacementMileage' => $consumable->getNextReplacementMileage(),
                    'quantity' => $consumable->getQuantity(),
                    'lastChanged' => $consumable->getLastChanged()?->format('Y-m-d'),
                    'mileageAtChange' => $consumable->getMileageAtChange(),
                    'cost' => $consumable->getCost(),
                    'notes' => $consumable->getNotes(),
                    'productUrl' => $consumable->getProductUrl(),
                    'includedInServiceCost' => $consumable->isIncludedInServiceCost(),
                    'createdAt' => $consumable->getCreatedAt()?->format('c'),
                    'updatedAt' => $consumable->getUpdatedAt()?->format('c'),
                ];
                if ($includeAttachmentRefs) {
                    $consumableData['receiptAttachment'] = $this->serializeAttachment($consumable->getReceiptAttachment(), $zipDir);
                }
            }

            return [
                'type' => $it->getType(),
                'description' => $it->getDescription(),
                'cost' => $it->getCost(),
                'quantity' => $it->getQuantity(),
                'consumableId' => $consumable?->getId(),
                'partId' => $part?->getId(),
                'part' => $partData,
                'consumable' => $consumableData,
            ];
        }, is_array($items = $serviceRecord->getItems()) ? $items : $items->toArray());
    }

    /**
     * Export MOT records
     */
    private function exportMotRecords(Vehicle $vehicle, bool $includeAttachmentRefs, ?string $zipDir): array
    {
        $motRecordsData = [];
        foreach ($vehicle->getMotRecords() as $motRecord) {
            $motData = [
                'motDate' => $motRecord->getTestDate()?->format('Y-m-d'),
                'testDate' => $motRecord->getTestDate()?->format('Y-m-d'),
                'expiryDate' => $motRecord->getExpiryDate()?->format('Y-m-d'),
                'result' => $motRecord->getResult(),
                'mileage' => $motRecord->getMileage(),
                'testNumber' => $motRecord->getMotTestNumber(),
                'motTestNumber' => $motRecord->getMotTestNumber(),
                'testingStation' => $motRecord->getTestCenter(),
                'testCenter' => $motRecord->getTestCenter(),
                'testerName' => $motRecord->getTesterName(),
                'advisories' => $motRecord->getAdvisories(),
                'failures' => $motRecord->getFailures(),
                'repairDetails' => $motRecord->getRepairDetails(),
                'notes' => $motRecord->getNotes(),
                'cost' => $motRecord->getCost(),
                'testCost' => $motRecord->getTestCost(),
                'repairCost' => $motRecord->getRepairCost(),
                'isRetest' => $motRecord->getIsRetest(),
                'createdAt' => $motRecord->getCreatedAt()?->format('c'),
                'receiptAttachment' => $includeAttachmentRefs
                    ? $this->serializeAttachment($motRecord->getReceiptAttachment(), $zipDir)
                    : null,
                'parts' => $this->exportMotParts($vehicle, $motRecord, $includeAttachmentRefs, $zipDir),
                'consumables' => $this->exportMotConsumables($vehicle, $motRecord, $includeAttachmentRefs, $zipDir),
                'serviceRecords' => $this->exportMotServiceRecords($vehicle, $motRecord, $includeAttachmentRefs, $zipDir),
            ];
            $motRecordsData[] = $motData;
        }
        return $motRecordsData;
    }

    /**
     * Export MOT parts
     */
    private function exportMotParts(Vehicle $vehicle, $motRecord, bool $includeAttachmentRefs, ?string $zipDir): array
    {
        $motParts = [];
        foreach ($vehicle->getParts() as $part) {
            if ($part->getMotRecord() && $part->getMotRecord()->getId() === $motRecord->getId()) {
                $motPartData = [
                    'name' => $part->getName() ?: $part->getDescription(),
                    'price' => $part->getPrice(),
                    'quantity' => $part->getQuantity(),
                    'purchaseDate' => $part->getPurchaseDate()?->format('Y-m-d'),
                    'description' => $part->getDescription(),
                    'partNumber' => $part->getPartNumber(),
                    'manufacturer' => $part->getManufacturer(),
                    'supplier' => $part->getSupplier(),
                    'cost' => $part->getCost(),
                    'partCategory' => $part->getPartCategory()?->getName(),
                    'partCategoryId' => $part->getPartCategory()?->getId(),
                    'installationDate' => $part->getInstallationDate()?->format('Y-m-d'),
                    'mileageAtInstallation' => $part->getMileageAtInstallation(),
                    'notes' => $part->getNotes(),
                    'productUrl' => $part->getProductUrl(),
                    'includedInServiceCost' => $part->isIncludedInServiceCost(),
                    'createdAt' => $part->getCreatedAt()?->format('c'),
                ];
                if ($includeAttachmentRefs) {
                    $motPartData['receiptAttachment'] = $this->serializeAttachment($part->getReceiptAttachment(), $zipDir);
                }
                $motParts[] = $motPartData;
            }
        }
        return $motParts;
    }

    /**
     * Export MOT consumables
     */
    private function exportMotConsumables(Vehicle $vehicle, $motRecord, bool $includeAttachmentRefs, ?string $zipDir): array
    {
        $motConsumables = [];
        foreach ($vehicle->getConsumables() as $consumable) {
            if ($consumable->getMotRecord() && $consumable->getMotRecord()->getId() === $motRecord->getId()) {
                $motConsumableData = [
                    'name' => $consumable->getDescription(),
                    'consumableType' => $consumable->getConsumableType()->getName(),
                    'description' => $consumable->getDescription(),
                    'brand' => $consumable->getBrand(),
                    'partNumber' => $consumable->getPartNumber(),
                    'supplier' => $consumable->getSupplier(),
                    'quantity' => $consumable->getQuantity(),
                    'lastChanged' => $consumable->getLastChanged()?->format('Y-m-d'),
                    'mileageAtChange' => $consumable->getMileageAtChange(),
                    'cost' => $consumable->getCost(),
                    'notes' => $consumable->getNotes(),
                    'productUrl' => $consumable->getProductUrl(),
                    'includedInServiceCost' => $consumable->isIncludedInServiceCost(),
                    'createdAt' => $consumable->getCreatedAt()?->format('c'),
                ];
                if ($includeAttachmentRefs) {
                    $motConsumableData['receiptAttachment'] = $this->serializeAttachment($consumable->getReceiptAttachment(), $zipDir);
                }
                $motConsumables[] = $motConsumableData;
            }
        }
        return $motConsumables;
    }

    /**
     * Export MOT service records
     */
    private function exportMotServiceRecords(Vehicle $vehicle, $motRecord, bool $includeAttachmentRefs, ?string $zipDir): array
    {
        $motServiceRecords = [];
        foreach ($vehicle->getServiceRecords() as $svc) {
            if ($svc->getMotRecord() && $svc->getMotRecord()->getId() === $motRecord->getId()) {
                $motSvcData = $this->exportServiceRecordData($svc, $includeAttachmentRefs, $zipDir);
                $motServiceRecords[] = $motSvcData;
            }
        }
        return $motServiceRecords;
    }

    /**
     * Export insurance records
     */
    private function exportInsuranceRecords(Vehicle $vehicle): array
    {
        $insuranceRecordsData = [];
        foreach ($vehicle->getInsurancePolicies() as $policy) {
            $insuranceRecordsData[] = [
                'insuranceProvider' => $policy->getProvider(),
                'policyNumber' => $policy->getPolicyNumber(),
                'coverType' => $policy->getCoverageType(),
                'startDate' => $policy->getStartDate()?->format('Y-m-d'),
                'endDate' => $policy->getExpiryDate()?->format('Y-m-d'),
                'premium' => $policy->getAnnualCost(),
                'excess' => $policy->getExcess(),
                'ncdYears' => $policy->getNcdYears(),
                'mileageLimit' => $policy->getMileageLimit(),
                'autoRenewal' => $policy->getAutoRenewal(),
                'notes' => $policy->getNotes(),
                'createdAt' => $policy->getCreatedAt()?->format('c'),
            ];
        }
        return $insuranceRecordsData;
    }

    /**
     * Export road tax records
     */
    private function exportRoadTaxRecords(Vehicle $vehicle): array
    {
        $roadTaxRecordsData = [];
        foreach ($vehicle->getRoadTaxRecords() as $tax) {
            $roadTaxRecordsData[] = [
                'frequency' => $tax->getFrequency(),
                'amount' => $tax->getAmount(),
                'startDate' => $tax->getStartDate()?->format('Y-m-d'),
                'expiryDate' => $tax->getExpiryDate()?->format('Y-m-d'),
                'sorn' => $tax->getSorn(),
                'notes' => $tax->getNotes(),
                'createdAt' => $tax->getCreatedAt()?->format('c'),
            ];
        }
        return $roadTaxRecordsData;
    }

    /**
     * Export specification
     */
    private function exportSpecification(Vehicle $vehicle): ?array
    {
        // Fetch specification via repository (OneToOne relationship from Specification to Vehicle)
        $specification = $this->entityManager
            ->getRepository(Specification::class)
            ->findOneBy(['vehicle' => $vehicle]);
        
        if (!$specification) {
            return null;
        }

        return [
            'engineType' => $specification->getEngineType(),
            'displacement' => $specification->getDisplacement(),
            'power' => $specification->getPower(),
            'torque' => $specification->getTorque(),
            'compression' => $specification->getCompression(),
            'bore' => $specification->getBore(),
            'stroke' => $specification->getStroke(),
            'fuelSystem' => $specification->getFuelSystem(),
            'cooling' => $specification->getCooling(),
            'sparkplugType' => $specification->getSparkplugType(),
            'coolantType' => $specification->getCoolantType(),
            'coolantCapacity' => $specification->getCoolantCapacity(),
            'gearbox' => $specification->getGearbox(),
            'transmission' => $specification->getTransmission(),
            'finalDrive' => $specification->getFinalDrive(),
            'clutch' => $specification->getClutch(),
            'engineOilType' => $specification->getEngineOilType(),
            'engineOilCapacity' => $specification->getEngineOilCapacity(),
            'transmissionOilType' => $specification->getTransmissionOilType(),
            'transmissionOilCapacity' => $specification->getTransmissionOilCapacity(),
            'middleDriveOilType' => $specification->getMiddleDriveOilType(),
            'middleDriveOilCapacity' => $specification->getMiddleDriveOilCapacity(),
            'frame' => $specification->getFrame(),
            'frontSuspension' => $specification->getFrontSuspension(),
            'rearSuspension' => $specification->getRearSuspension(),
            'staticSagFront' => $specification->getStaticSagFront(),
            'staticSagRear' => $specification->getStaticSagRear(),
            'frontBrakes' => $specification->getFrontBrakes(),
            'rearBrakes' => $specification->getRearBrakes(),
            'frontTyre' => $specification->getFrontTyre(),
            'rearTyre' => $specification->getRearTyre(),
            'frontTyrePressure' => $specification->getFrontTyrePressure(),
            'rearTyrePressure' => $specification->getRearTyrePressure(),
            'frontWheelTravel' => $specification->getFrontWheelTravel(),
            'rearWheelTravel' => $specification->getRearWheelTravel(),
            'wheelbase' => $specification->getWheelbase(),
            'seatHeight' => $specification->getSeatHeight(),
            'groundClearance' => $specification->getGroundClearance(),
            'dryWeight' => $specification->getDryWeight(),
            'wetWeight' => $specification->getWetWeight(),
            'fuelCapacity' => $specification->getFuelCapacity(),
            'topSpeed' => $specification->getTopSpeed(),
            'additionalInfo' => $specification->getAdditionalInfo(),
            'scrapedAt' => $specification->getScrapedAt()?->format('c'),
            'sourceUrl' => $specification->getSourceUrl(),
        ];
    }

    /**
     * Export todos
     */
    private function exportTodos(Vehicle $vehicle): array
    {
        $todosData = [];
        // Check if vehicle has todos (using reflection since no public getter exists)
        try {
            $reflection = new \ReflectionClass($vehicle);
            $property = $reflection->getProperty('todos');
            $property->setAccessible(true);
            $todos = $property->getValue($vehicle);
            
            if ($todos) {
                foreach ($todos as $todo) {
                    $todosData[] = [
                        'title' => $todo->getTitle(),
                        'description' => $todo->getDescription(),
                        'dueDate' => $todo->getDueDate()?->format('Y-m-d'),
                        'completedBy' => $todo->getCompletedBy()?->format('Y-m-d H:i:s'),
                        'parts' => $todo->getParts(),
                        'consumables' => $todo->getConsumables(),
                        'createdAt' => $todo->getCreatedAt()?->format('c'),
                    ];
                }
            }
        } catch (\ReflectionException $e) {
            // Todos not available on this entity
        }
        return $todosData;
    }

    /**
     * Export attachments
     */
    private function exportAttachments(Vehicle $vehicle, bool $includeAttachmentRefs, ?string $zipDir): array
    {
        if (!$includeAttachmentRefs) {
            return [];
        }

        // Only export attachments that are directly linked to the vehicle (entity_type='vehicle')
        // and NOT already exported as receiptAttachment on service/MOT/fuel/part/consumable records.
        // This prevents duplicates in the export.
        
        $attachmentRepo = $this->entityManager->getRepository(Attachment::class);
        
        // Get only vehicle-level attachments (documents, manuals, etc. directly attached to vehicle)
        $vehicleAttachments = $attachmentRepo->findBy([
            'vehicle' => $vehicle,
            'entityType' => 'vehicle'
        ]);
        
        $attachmentsData = [];
        foreach ($vehicleAttachments as $att) {
            // Skip if already exported as receiptAttachment on an entity
            if ($att->getId() && isset($this->exportedAttachmentIds[$att->getId()])) {
                $this->logger->debug('[export] Skipping duplicate attachment', [
                    'attachmentId' => $att->getId(),
                    'filename' => $att->getFilename()
                ]);
                continue;
            }
            
            $attData = $this->serializeAttachment($att, $zipDir);
            if ($attData) {
                $attData['originalId'] = $att->getId();
                $attachmentsData[] = $attData;
            }
        }
        
        return $attachmentsData;
    }

    /**
     * Export vehicle images
     */
    private function exportVehicleImages(Vehicle $vehicle, ?string $zipDir): array
    {
        $images = [];
        $imageCollection = $vehicle->getImages()->toArray();
        $this->logger->info('[export] Exporting vehicle images', [
            'vehicleId' => $vehicle->getId(),
            'imageCount' => count($imageCollection),
            'zipDir' => $zipDir
        ]);
        
        foreach ($imageCollection as $img) {
            $imageData = [
                'imagePath' => $img->getPath(),
                'isMain' => $img->getIsPrimary(),
                'caption' => $img->getCaption(),
                'displayOrder' => $img->getDisplayOrder(),
                'createdAt' => $img->getUploadedAt()?->format('c'),
            ];

            // Copy the physical image file to ZIP directory
            if ($zipDir && $img->getPath()) {
                // Path already includes /uploads prefix, so just prepend project dir
                $sourcePath = $this->projectDir . $img->getPath();
                
                $this->logger->info('[export] Checking image file', [
                    'imagePath' => $img->getPath(),
                    'sourcePath' => $sourcePath,
                    'exists' => file_exists($sourcePath)
                ]);
                
                if (file_exists($sourcePath)) {
                    // Create a safe filename with image ID to avoid collisions
                    $safeName = 'image_' . $img->getId() . '_' . basename($img->getPath());
                    $targetPath = $zipDir . '/images/' . $safeName;
                    $destDir = dirname($targetPath);
                    
                    try {
                        if (!is_dir($destDir)) {
                            mkdir($destDir, 0755, true);
                        }
                        
                        if (copy($sourcePath, $targetPath)) {
                            $imageData['exportFilename'] = $safeName;
                            $this->logger->debug('[export] Copied vehicle image', [
                                'source' => $sourcePath,
                                'target' => $targetPath
                            ]);
                        } else {
                            $this->logger->error('[export] Failed to copy vehicle image', [
                                'source' => $sourcePath
                            ]);
                        }
                    } catch (\Exception $e) {
                        $this->logger->error('[export] Exception copying vehicle image', [
                            'source' => $sourcePath,
                            'error' => $e->getMessage()
                        ]);
                    }
                } else {
                    $this->logger->warning('[export] Vehicle image file not found', ['path' => $sourcePath]);
                }
            }
            
            $images[] = $imageData;
        }
        
        return $images;
    }

    /**
     * Export status history
     */
    private function exportStatusHistory(Vehicle $vehicle): array
    {
        return array_map(fn($h) => [
            'oldStatus' => $h->getOldStatus(),
            'newStatus' => $h->getNewStatus(),
            'changeDate' => $h->getChangeDate()?->format('Y-m-d'),
            'notes' => $h->getNotes(),
            'userEmail' => $h->getUser()?->getEmail(),
            'createdAt' => $h->getCreatedAt()?->format('c'),
        ], $vehicle->getStatusHistory()->toArray());
    }

    /**
     * Perform memory cleanup
     */
    private function performMemoryCleanup(): void
    {
        $this->entityManager->clear();
        gc_collect_cycles();
        $this->logger->debug('[export] Memory cleanup performed', [
            'memoryUsageMB' => round(memory_get_usage(true) / 1024 / 1024, 2)
        ]);
    }
}
