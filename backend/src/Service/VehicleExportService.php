<?php

declare(strict_types=1);

namespace App\Service;

use App\Config\ImportExportConfig;
use App\DTO\ExportResult;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\Attachment;
use App\Exception\ExportException;
use App\Service\Trait\EntityHydratorTrait;
use App\Controller\Trait\AttachmentFileOrganizerTrait;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Service for exporting vehicle data
 */
class VehicleExportService
{
    use EntityHydratorTrait;
    use AttachmentFileOrganizerTrait;

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
        
        // Export attachments
        $attachmentsData = $this->exportAttachments($vehicle, $includeAttachmentRefs, $zipDir);
        
        // Export vehicle images
        $vehicleImages = $this->exportVehicleImages($vehicle);

        return [
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
            'fuelRecords' => $fuelRecords,
            'parts' => $parts,
            'consumables' => $consumables,
            'serviceRecords' => $serviceRecordsData,
            'motRecords' => $motRecordsData,
            'specification' => $specData,
            'insuranceRecords' => $insuranceRecordsData,
            'roadTaxRecords' => $roadTaxRecordsData,
            'attachments' => $attachmentsData,
            'todos' => $todosData,
            'vehicleImages' => $vehicleImages,
        ];
    }

    /**
     * Serialize attachment data for export
     */
    private function serializeAttachment(?Attachment $attachment, string $zipDir): ?array
    {
        if (!$attachment || !$zipDir) {
            return null;
        }

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
        return [
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
            'items' => $this->exportServiceItems($serviceRecord, $includeAttachmentRefs, $zipDir),
            'createdAt' => $serviceRecord->getCreatedAt()?->format('c'),
        ];
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
        }, $serviceRecord->getItems()->toArray());
    }

    /**
     * Export MOT records
     */
    private function exportMotRecords(Vehicle $vehicle, bool $includeAttachmentRefs, ?string $zipDir): array
    {
        $motRecordsData = [];
        foreach ($vehicle->getMotRecords() as $motRecord) {
            $motData = [
                'motDate' => $motRecord->getMotDate()?->format('Y-m-d'),
                'expiryDate' => $motRecord->getExpiryDate()?->format('Y-m-d'),
                'result' => $motRecord->getResult(),
                'mileage' => $motRecord->getMileage(),
                'testNumber' => $motRecord->getTestNumber(),
                'testingStation' => $motRecord->getTestingStation(),
                'advisories' => $motRecord->getAdvisories(),
                'failures' => $motRecord->getFailures(),
                'notes' => $motRecord->getNotes(),
                'cost' => $motRecord->getCost(),
                'createdAt' => $motRecord->getCreatedAt()?->format('c'),
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
                'insuranceProvider' => $policy->getInsuranceProvider(),
                'policyNumber' => $policy->getPolicyNumber(),
                'coverType' => $policy->getCoverType(),
                'startDate' => $policy->getStartDate()?->format('Y-m-d'),
                'endDate' => $policy->getEndDate()?->format('Y-m-d'),
                'premium' => $policy->getPremium(),
                'excess' => $policy->getExcess(),
                'renewalDate' => $policy->getRenewalDate()?->format('Y-m-d'),
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
                'taxType' => $tax->getTaxType(),
                'cost' => $tax->getCost(),
                'startDate' => $tax->getStartDate()?->format('Y-m-d'),
                'endDate' => $tax->getEndDate()?->format('Y-m-d'),
                'paymentDate' => $tax->getPaymentDate()?->format('Y-m-d'),
                'paymentMethod' => $tax->getPaymentMethod(),
                'reference' => $tax->getReference(),
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
        $spec = $vehicle->getSpecification();
        if (!$spec) {
            return null;
        }

        return [
            'engineType' => $spec->getEngineType(),
            'displacement' => $spec->getDisplacement(),
            'bore' => $spec->getBore(),
            'stroke' => $spec->getStroke(),
            'compressionRatio' => $spec->getCompressionRatio(),
            'valvesPerCylinder' => $spec->getValvesPerCylinder(),
            'maxPower' => $spec->getMaxPower(),
            'maxTorque' => $spec->getMaxTorque(),
            'fuelSystem' => $spec->getFuelSystem(),
            'ignition' => $spec->getIgnition(),
            'starting' => $spec->getStarting(),
            'lubrication' => $spec->getLubrication(),
            'cooling' => $spec->getCooling(),
            'sparkplugType' => $spec->getSparkplugType(),
            'coolantType' => $spec->getCoolantType(),
            'coolantCapacity' => $spec->getCoolantCapacity(),
            'gearbox' => $spec->getGearbox(),
            'transmission' => $spec->getTransmission(),
            'finalDrive' => $spec->getFinalDrive(),
            'clutch' => $spec->getClutch(),
            'engineOilType' => $spec->getEngineOilType(),
            'engineOilCapacity' => $spec->getEngineOilCapacity(),
            'transmissionOilType' => $spec->getTransmissionOilType(),
            'transmissionOilCapacity' => $spec->getTransmissionOilCapacity(),
            'middleDriveOilType' => $spec->getMiddleDriveOilType(),
            'middleDriveOilCapacity' => $spec->getMiddleDriveOilCapacity(),
            'frame' => $spec->getFrame(),
            'frontSuspension' => $spec->getFrontSuspension(),
            'rearSuspension' => $spec->getRearSuspension(),
            'staticSagFront' => $spec->getStaticSagFront(),
            'staticSagRear' => $spec->getStaticSagRear(),
            'frontBrakes' => $spec->getFrontBrakes(),
            'rearBrakes' => $spec->getRearBrakes(),
            'frontTyre' => $spec->getFrontTyre(),
            'rearTyre' => $spec->getRearTyre(),
            'frontTyrePressure' => $spec->getFrontTyrePressure(),
            'rearTyrePressure' => $spec->getRearTyrePressure(),
            'frontWheelTravel' => $spec->getFrontWheelTravel(),
            'rearWheelTravel' => $spec->getRearWheelTravel(),
            'wheelbase' => $spec->getWheelbase(),
            'seatHeight' => $spec->getSeatHeight(),
            'groundClearance' => $spec->getGroundClearance(),
            'dryWeight' => $spec->getDryWeight(),
            'wetWeight' => $spec->getWetWeight(),
            'fuelCapacity' => $spec->getFuelCapacity(),
            'topSpeed' => $spec->getTopSpeed(),
            'additionalInfo' => $spec->getAdditionalInfo(),
            'scrapedAt' => $spec->getScrapedAt()?->format('c'),
            'sourceUrl' => $spec->getSourceUrl(),
        ];
    }

    /**
     * Export todos
     */
    private function exportTodos(Vehicle $vehicle): array
    {
        $todosData = [];
        foreach ($vehicle->getTodos() as $todo) {
            $todosData[] = [
                'description' => $todo->getDescription(),
                'dueDate' => $todo->getDueDate()?->format('Y-m-d'),
                'completed' => $todo->isCompleted(),
                'priority' => $todo->getPriority(),
                'notes' => $todo->getNotes(),
                'createdAt' => $todo->getCreatedAt()?->format('c'),
            ];
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

        $attachments = $this->entityManager->getRepository(Attachment::class)
            ->findBy(['vehicle' => $vehicle]);
        
        $attachmentsData = [];
        foreach ($attachments as $att) {
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
    private function exportVehicleImages(Vehicle $vehicle): array
    {
        return array_map(fn($img) => [
            'imagePath' => $img->getImagePath(),
            'isMain' => $img->isMain(),
            'caption' => $img->getCaption(),
            'displayOrder' => $img->getDisplayOrder(),
            'createdAt' => $img->getCreatedAt()?->format('c'),
        ], $vehicle->getVehicleImages()->toArray());
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
