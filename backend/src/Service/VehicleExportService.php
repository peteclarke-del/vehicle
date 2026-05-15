<?php

declare(strict_types=1);

namespace App\Service;

use App\Config\ImportExportConfig;
use App\DTO\ExportResult;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\Specification;
use App\Entity\Attachment;
use App\Entity\StockItem;
use App\Entity\VehicleType;
use App\Entity\VehicleMake;
use App\Entity\VehicleModel;
use App\Entity\PartCategory;
use App\Entity\ConsumableType;
use App\Entity\SecurityFeature;
use App\Entity\FeatureFlag;
use App\Entity\MotRecord;
use App\Entity\ServiceItem;
use App\Entity\ServiceRecord;
use App\Entity\UserFeatureOverride;
use App\Entity\UserPreference;
use App\Entity\Report;
use App\Exception\ExportException;
use App\Service\Trait\EntityHydratorTrait;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

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
        *
        * @var array<int, true>
     */
    private array $exportedAttachmentIds = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private ImportExportConfig $config,
        private string $projectDir
    ) {
    }

    /**
     * Export vehicles for a user
     */
    public function exportVehicles(
        User $user,
        bool $isAdmin = false,
        bool $includeAttachmentRefs = false,
        ?string $zipDir = null,
        bool $includeGlobalState = false,
        bool $includeImages = false
    ): ExportResult {
        try {
            $startTime = microtime(true);
            $this->setupEnvironment();

            $this->logger->info(
                '[export] JSON started', [
                'userId' => $user->getId(),
                'includeAttachmentRefs' => $includeAttachmentRefs,
                'includeGlobalState' => $includeGlobalState,
                'includeImages' => $includeImages,
                'zipDir' => $zipDir
                ]
            );

            // Fetch vehicle IDs with sorting
            $vehicleIds = $this->fetchVehicleIds($user, $isAdmin);
            
            $this->logger->info(
                '[export] JSON vehicle ids loaded', [
                'count' => count($vehicleIds),
                'elapsedMs' => (int)((microtime(true) - $startTime) * 1000)
                ]
            );

            // Process vehicles in batches
            $result = $this->processVehicleBatches(
                $vehicleIds,
                $includeAttachmentRefs,
                $zipDir,
                $includeImages,
                $startTime
            );
            $stockItems = $this->exportStockItems(
                $user,
                $isAdmin,
                $includeAttachmentRefs,
                $zipDir
            );
            
            // Only export global state if explicitly requested
            $globalState = $includeGlobalState ? $this->exportGlobalState($user, $isAdmin) : [];
            $manifest = $this->buildBackupManifest($result['vehicles'], $stockItems, $globalState);

            $statistics = [
                'vehicleCount' => count($result['vehicles']),
                'stockItemCount' => count($stockItems),
                'globalEntityGroups' => count($globalState),
                'globalStateIncluded' => $includeGlobalState,
                'processingTimeSeconds' => round(microtime(true) - $startTime, 2),
                'memoryPeakMB' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ];

            $this->logger->info('[export] JSON completed', $statistics);

            return ExportResult::createSuccess(
                [
                    'vehicles' => $result['vehicles'],
                    'stockItems' => $stockItems,
                    'globalState' => $globalState,
                    'manifest' => $manifest,
                ],
                $statistics,
                'Export completed successfully'
            );

        } catch (\Exception $e) {
            $this->logger->error(
                '[export] Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
                ]
            );
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
        *
        * @return list<int>
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

        return array_values(array_map(
            static fn($row) => (int)$row['id'],
            $qb->getQuery()->getScalarResult()
        ));
    }

    /**
     * Process vehicles in batches
     *
     * @param list<int> $vehicleIds
     *
     * @return array{vehicles: list<array<string, mixed>>}
     */
    private function processVehicleBatches(
        array $vehicleIds,
        bool $includeAttachmentRefs,
        ?string $zipDir,
        bool $includeImages,
        float $startTime
    ): array {
        $data = [];
        $vehicleCount = 0;
        $batchSize = $this->config->getBatchSize();
        $total = count($vehicleIds);

        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $batchIds = array_slice($vehicleIds, $offset, $batchSize);
            $vehicles = $this->loadVehicleBatch($batchIds);
            $specificationsByVehicleId = $this->loadSpecificationsForVehicles($batchIds);

            $this->logger->info(
                '[export] JSON batch loaded', [
                'offset' => $offset,
                'count' => count($vehicles)
                ]
            );

            foreach ($vehicles as $vehicle) {
                $vehicleCount++;

                if ($vehicleCount === 1 || ($vehicleCount % 10) === 0) {
                    $this->logger->info(
                        '[export] JSON progress', [
                        'vehicleCount' => $vehicleCount,
                        'elapsedMs' => (int)((microtime(true) - $startTime) * 1000)
                        ]
                    );
                }

                $specification = $specificationsByVehicleId[$vehicle->getId() ?? 0] ?? null;
                $data[] = $this->exportVehicleData(
                    $vehicle,
                    $specification,
                    $includeAttachmentRefs,
                    $zipDir,
                    $includeImages
                );
            }

            // Memory cleanup
            if ($this->config->isMemoryCleanupEnabled()  
                && ($offset + $batchSize) % $this->config->getCleanupInterval() === 0
            ) {
                $this->performMemoryCleanup();
            }
        }

        return ['vehicles' => $data];
    }

    /**
     * Load specifications for a batch of vehicles from the specifications table.
     *
        * @param list<int> $batchIds
        *
     * @return array<int, Specification>
     */
    private function loadSpecificationsForVehicles(array $batchIds): array
    {
        if ($batchIds === []) {
            return [];
        }

        $qb = $this->entityManager->createQueryBuilder();
        $specifications = $qb
            ->select('spec, v')
            ->from(Specification::class, 'spec')
            ->join('spec.vehicle', 'v')
            ->where($qb->expr()->in('v.id', ':ids'))
            ->setParameter('ids', $batchIds)
            ->getQuery()
            ->getResult();

        $specificationsByVehicleId = [];
        foreach ($specifications as $specification) {
            if (!$specification instanceof Specification) {
                continue;
            }

            $vehicleId = $specification->getVehicle()?->getId();
            if ($vehicleId !== null) {
                $specificationsByVehicleId[$vehicleId] = $specification;
            }
        }

        return $specificationsByVehicleId;
    }

    /**
     * Load a batch of vehicles with eager loading to prevent N+1 queries
        *
        * @param list<int> $batchIds
        *
        * @return list<Vehicle>
     */
    private function loadVehicleBatch(array $batchIds): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('v, vt, fr, p, pc, c, ct, sr, sm, mr, ip, rt, t, sh, img')
            ->from(Vehicle::class, 'v')
            ->leftJoin('v.vehicleType', 'vt')
            ->leftJoin('v.fuelRecords', 'fr')
            ->leftJoin('fr.receiptAttachment', 'fra')
            ->leftJoin('v.parts', 'p')
            ->leftJoin('p.partCategory', 'pc')
            ->leftJoin('p.receiptAttachment', 'pra')
            ->leftJoin('v.consumables', 'c')
            ->leftJoin('c.consumableType', 'ct')
            ->leftJoin('c.receiptAttachment', 'cra')
            ->leftJoin('v.serviceRecords', 'sr')
            ->leftJoin('sr.receiptAttachment', 'sra')
            ->leftJoin('sr.items', 'sm')
            ->leftJoin('v.motRecords', 'mr')
            ->leftJoin('mr.receiptAttachment', 'mra')
            ->leftJoin('v.insurancePolicies', 'ip')
            ->leftJoin('v.roadTaxRecords', 'rt')
            ->leftJoin('v.todos', 't')
            ->leftJoin('v.statusHistory', 'sh')
            ->leftJoin('v.images', 'img')
            ->where($qb->expr()->in('v.id', ':ids'))
            ->setParameter('ids', $batchIds)
            ->orderBy('vt.name', 'ASC')
            ->addOrderBy('v.name', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Export single vehicle data
     *
     * @return array<string, mixed>
     */
    private function exportVehicleData(
        Vehicle $vehicle,
        ?Specification $specification,
        bool $includeAttachmentRefs,
        ?string $zipDir,
        bool $includeImages
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
        $specData = $this->exportSpecification($specification);
        
        // Export todos
        $todosData = $this->exportTodos($vehicle);
        
        $includeMedia = $zipDir !== null;
        $includeVehicleImages = $includeMedia && $includeImages;

        // Export attachments (ZIP export only)
        $attachmentsData = $includeMedia
            ? $this->exportAttachments($vehicle, $includeAttachmentRefs, $zipDir)
            : null;
        
        // Export vehicle images (ZIP export only)
        $vehicleImages = $includeVehicleImages
            ? $this->exportVehicleImages($vehicle, $zipDir)
            : null;

        $data = [
            'originalId' => $vehicle->getId(),
            'name' => $this->trimString($vehicle->getName()),
            'vehicleType' => $this->trimString($vehicle->getVehicleType()?->getName()),
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
            'suppressNotifications' => $vehicle->isSuppressNotifications(),
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
        *
        * @return array<string, mixed>|null
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
            'uploadedAt' => $attachment->getUploadedAt()->format('c'),
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
                    $this->logger->debug(
                        '[export] Copied attachment file', [
                        'source' => $sourcePath,
                        'target' => $targetPath
                        ]
                    );
                } catch (\Exception $e) {
                    $this->logger->error(
                        '[export] Failed to copy attachment', [
                        'source' => $sourcePath,
                        'error' => $e->getMessage()
                        ]
                    );
                }
            } else {
                $this->logger->warning('[export] Attachment file not found', ['path' => $sourcePath]);
            }
        }

        return $attachmentData;
    }

    /**
     * Export fuel records
        *
        * @return list<array<string, mixed>>
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
        *
        * @return list<array<string, mixed>>
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
        *
        * @return list<array<string, mixed>>
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
     * Export stock items
        *
        * @return list<array<string, mixed>>
     */
    public function exportStockItems(
        User $user,
        bool $isAdmin,
        bool $includeAttachmentRefs = false,
        ?string $zipDir = null
    ): array {
        $repo = $this->entityManager->getRepository(StockItem::class);
        $items = $isAdmin
            ? $repo->findBy([], ['updatedAt' => 'DESC'])
            : $repo->findBy(['user' => $user], ['updatedAt' => 'DESC']);

        return array_map(
            function (StockItem $item) use ($includeAttachmentRefs, $zipDir): array {
                $data = [
                'id' => $item->getId(),
                'itemType' => $item->getItemType(),
                'category' => $item->getCategory(),
                'vehicleType' => $item->getVehicleType()?->getName(),
                'vehicleTypeId' => $item->getVehicleType()?->getId(),
                'quantity' => $item->getQuantity(),
                'supplier' => $item->getSupplier(),
                'description' => $item->getDescription(),
                'price' => $item->getPrice(),
                'notes' => $item->getNotes(),
                'purchaseDate' => $item->getPurchaseDate()?->format('Y-m-d'),
                'partNumber' => $item->getPartNumber(),
                'manufacturer' => $item->getManufacturer(),
                'warranty' => $item->getWarranty(),
                'createdAt' => $item->getCreatedAt()?->format('c'),
                'updatedAt' => $item->getUpdatedAt()?->format('c'),
                ];

                if ($includeAttachmentRefs) {
                    $data['receiptAttachment'] = $this->serializeAttachment(
                        $item->getReceiptAttachment(),
                        $zipDir
                    );
                }

                return $data;
            }, $items
        );
    }

    /**
     * Export service records
        *
        * @return list<array<string, mixed>>
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
        *
        * @return array<string, mixed>
     */
    private function exportServiceRecordData(ServiceRecord $serviceRecord, bool $includeAttachmentRefs, ?string $zipDir): array
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
        *
        * @return list<array<string, mixed>>
     */
    private function exportServiceItems(ServiceRecord $serviceRecord, bool $includeAttachmentRefs, ?string $zipDir): array
    {
        return array_map(
            function (ServiceItem $it) use ($includeAttachmentRefs, $zipDir) {
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
                    'consumableType' => $consumable->getConsumableType()?->getName(),
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
            }, array_values($serviceRecord->getItems())
        );
    }

    /**
     * Export MOT records
        *
        * @return list<array<string, mixed>>
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
        *
        * @return list<array<string, mixed>>
     */
    private function exportMotParts(Vehicle $vehicle, MotRecord $motRecord, bool $includeAttachmentRefs, ?string $zipDir): array
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
        *
        * @return list<array<string, mixed>>
     */
    private function exportMotConsumables(Vehicle $vehicle, MotRecord $motRecord, bool $includeAttachmentRefs, ?string $zipDir): array
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
        *
        * @return list<array<string, mixed>>
     */
    private function exportMotServiceRecords(Vehicle $vehicle, MotRecord $motRecord, bool $includeAttachmentRefs, ?string $zipDir): array
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
        *
        * @return list<array<string, mixed>>
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
        *
        * @return list<array<string, mixed>>
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
        *
        * @return array<string, mixed>|null
     */
    private function exportSpecification(?Specification $specification): ?array
    {
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
        *
        * @return list<array<string, mixed>>
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
        *
        * @return list<array<string, mixed>>
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
        $vehicleAttachments = $attachmentRepo->findBy(
            [
            'vehicle' => $vehicle,
            'entityType' => 'vehicle'
            ]
        );
        
        $attachmentsData = [];
        foreach ($vehicleAttachments as $att) {
            // Skip if already exported as receiptAttachment on an entity
            if ($att->getId() && isset($this->exportedAttachmentIds[$att->getId()])) {
                $this->logger->debug(
                    '[export] Skipping duplicate attachment', [
                    'attachmentId' => $att->getId(),
                    'filename' => $att->getFilename()
                    ]
                );
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
        *
        * @return list<array<string, mixed>>
     */
    private function exportVehicleImages(Vehicle $vehicle, ?string $zipDir): array
    {
        $images = [];
        $imageCollection = $vehicle->getImages()->toArray();
        $this->logger->info(
            '[export] Exporting vehicle images', [
            'vehicleId' => $vehicle->getId(),
            'imageCount' => count($imageCollection),
            'zipDir' => $zipDir
            ]
        );
        
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
                
                $this->logger->info(
                    '[export] Checking image file', [
                    'imagePath' => $img->getPath(),
                    'sourcePath' => $sourcePath,
                    'exists' => file_exists($sourcePath)
                    ]
                );
                
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
                            $this->logger->debug(
                                '[export] Copied vehicle image', [
                                'source' => $sourcePath,
                                'target' => $targetPath
                                ]
                            );
                        } else {
                            $this->logger->error(
                                '[export] Failed to copy vehicle image', [
                                'source' => $sourcePath
                                ]
                            );
                        }
                    } catch (\Exception $e) {
                        $this->logger->error(
                            '[export] Exception copying vehicle image', [
                            'source' => $sourcePath,
                            'error' => $e->getMessage()
                            ]
                        );
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
        *
        * @return list<array<string, mixed>>
     */
    private function exportStatusHistory(Vehicle $vehicle): array
    {
        return array_values(array_map(
            fn($h) => [
            'oldStatus' => $h->getOldStatus(),
            'newStatus' => $h->getNewStatus(),
            'changeDate' => $h->getChangeDate()?->format('Y-m-d'),
            'notes' => $h->getNotes(),
            'userEmail' => $h->getUser()?->getEmail(),
            'createdAt' => $h->getCreatedAt()?->format('c'),
            ], $vehicle->getStatusHistory()->toArray()
        ));
    }

    /**
     * Export global reference/system data required for portable backups.
        *
        * @return array<string, list<array<string, mixed>>>
     */
    private function exportGlobalState(User $user, bool $isAdmin): array
    {
        $vehicleTypes = array_map(
            static fn(VehicleType $type): array => [
            'name' => $type->getName(),
            ], $this->entityManager->getRepository(VehicleType::class)->findBy([], ['name' => 'ASC'])
        );

        $vehicleMakes = array_map(
            static fn(VehicleMake $make): array => [
            'name' => $make->getName(),
            'vehicleType' => $make->getVehicleType()->getName(),
            'isActive' => $make->isActive(),
            ], $this->entityManager->getRepository(VehicleMake::class)->findBy([], ['name' => 'ASC'])
        );

        $vehicleModels = array_map(
            static fn(VehicleModel $model): array => [
            'name' => $model->getName(),
            'make' => $model->getMake()->getName(),
            'vehicleType' => $model->getVehicleType()?->getName(),
            'startYear' => $model->getStartYear(),
            'endYear' => $model->getEndYear(),
            'imageUrl' => $model->getImageUrl(),
            'isActive' => $model->isActive(),
            ], $this->entityManager->getRepository(VehicleModel::class)->findBy([], ['name' => 'ASC'])
        );

        $partCategories = array_map(
            static fn(PartCategory $category): array => [
            'name' => $category->getName(),
            'vehicleType' => $category->getVehicleType()?->getName(),
            'description' => $category->getDescription(),
            ], $this->entityManager->getRepository(PartCategory::class)->findBy([], ['name' => 'ASC'])
        );

        $consumableTypes = array_map(
            static fn(ConsumableType $type): array => [
            'name' => $type->getName(),
            'vehicleType' => $type->getVehicleType()?->getName(),
            'unit' => $type->getUnit(),
            'description' => $type->getDescription(),
            ], $this->entityManager->getRepository(ConsumableType::class)->findBy([], ['name' => 'ASC'])
        );

        $securityFeatures = array_map(
            static fn(SecurityFeature $feature): array => [
            'name' => $feature->getName(),
            'description' => $feature->getDescription(),
            'vehicleType' => $feature->getVehicleType()?->getName(),
            'createdAt' => $feature->getCreatedAt()?->format('c'),
            ], $this->entityManager->getRepository(SecurityFeature::class)->findBy([], ['name' => 'ASC'])
        );

        $featureFlags = array_map(
            static fn(FeatureFlag $flag): array => [
            'featureKey' => $flag->getFeatureKey(),
            'label' => $flag->getLabel(),
            'description' => $flag->getDescription(),
            'category' => $flag->getCategory(),
            'defaultEnabled' => $flag->isDefaultEnabled(),
            'sortOrder' => $flag->getSortOrder(),
            'createdAt' => $flag->getCreatedAt()->format('c'),
            ], $this->entityManager->getRepository(FeatureFlag::class)->findBy([], ['sortOrder' => 'ASC', 'featureKey' => 'ASC'])
        );

        $overrideCriteria = $isAdmin ? [] : ['user' => $user];
        $featureOverrides = array_map(
            static fn(UserFeatureOverride $override): array => [
            'featureKey' => $override->getFeatureFlag()?->getFeatureKey(),
            'enabled' => $override->isEnabled(),
            'setByEmail' => $override->getSetBy()?->getEmail(),
            'createdAt' => $override->getCreatedAt()->format('c'),
            'updatedAt' => $override->getUpdatedAt()?->format('c'),
            ], $this->entityManager->getRepository(UserFeatureOverride::class)->findBy($overrideCriteria)
        );

        $preferences = array_map(
            static fn(UserPreference $preference): array => [
            'name' => $preference->getName(),
            'value' => $preference->getValue(),
            'createdAt' => $preference->getCreatedAt()->format('c'),
            'updatedAt' => $preference->getUpdatedAt()?->format('c'),
            ], $this->entityManager->getRepository(UserPreference::class)->findBy(['user' => $user], ['name' => 'ASC'])
        );

        $reportCriteria = $isAdmin ? [] : ['user' => $user];
        $reports = array_map(
            static fn(Report $report): array => [
            'name' => $report->getName(),
            'templateKey' => $report->getTemplateKey(),
            'payload' => $report->getPayload(),
            'vehicleId' => $report->getVehicleId(),
            'generatedAt' => $report->getGeneratedAt()->format('c'),
            ], $this->entityManager->getRepository(Report::class)->findBy($reportCriteria, ['generatedAt' => 'DESC'])
        );

        return [
            'vehicleTypes' => $vehicleTypes,
            'vehicleMakes' => $vehicleMakes,
            'vehicleModels' => $vehicleModels,
            'partCategories' => $partCategories,
            'consumableTypes' => $consumableTypes,
            'securityFeatures' => $securityFeatures,
            'featureFlags' => $featureFlags,
            'userFeatureOverrides' => $featureOverrides,
            'userPreferences' => $preferences,
            'reports' => $reports,
        ];
    }

    /**
     * Build a complete backup manifest with explicit entity coverage status.
        *
        * @param list<array<string, mixed>> $vehicles
        * @param list<array<string, mixed>> $stockItems
        * @param array<string, list<array<string, mixed>>> $globalState
        *
        * @return array<string, mixed>
     */
    private function buildBackupManifest(array $vehicles, array $stockItems, array $globalState): array
    {
        $coverage = [
            ['entity' => 'Attachment', 'status' => 'exported_imported', 'reason' => 'Vehicle and stock attachments are exported with media files.'],
            ['entity' => 'Consumable', 'status' => 'exported_imported', 'reason' => 'Exported in vehicle payload.'],
            ['entity' => 'ConsumableType', 'status' => 'exported_imported', 'reason' => 'Exported in globalState and also reconstructed if missing.'],
            ['entity' => 'FeatureFlag', 'status' => 'exported_imported', 'reason' => 'Exported in globalState and restored by featureKey.'],
            ['entity' => 'FuelRecord', 'status' => 'exported_imported', 'reason' => 'Exported in vehicle payload.'],
            ['entity' => 'InsurancePolicy', 'status' => 'exported_imported', 'reason' => 'Exported in vehicle payload.'],
            ['entity' => 'MotRecord', 'status' => 'exported_imported', 'reason' => 'Exported in vehicle payload.'],
            ['entity' => 'Part', 'status' => 'exported_imported', 'reason' => 'Exported in vehicle payload.'],
            ['entity' => 'PartCategory', 'status' => 'exported_imported', 'reason' => 'Exported in globalState and also reconstructed if missing.'],
            ['entity' => 'RefreshToken', 'status' => 'omitted', 'reason' => 'Security-sensitive auth session material is never exported.'],
            ['entity' => 'Report', 'status' => 'exported_imported', 'reason' => 'User report metadata exported in globalState and restored.'],
            ['entity' => 'RoadTax', 'status' => 'exported_imported', 'reason' => 'Exported in vehicle payload.'],
            ['entity' => 'SecurityFeature', 'status' => 'exported_imported', 'reason' => 'Exported in globalState and restored by vehicle type + name.'],
            ['entity' => 'ServiceItem', 'status' => 'exported_imported', 'reason' => 'Exported within service records.'],
            ['entity' => 'ServiceRecord', 'status' => 'exported_imported', 'reason' => 'Exported in vehicle payload.'],
            ['entity' => 'Specification', 'status' => 'exported_imported', 'reason' => 'Exported in vehicle payload.'],
            ['entity' => 'StockItem', 'status' => 'exported_imported', 'reason' => 'Exported in stockItems payload.'],
            ['entity' => 'Todo', 'status' => 'exported_imported', 'reason' => 'Exported in vehicle payload.'],
            ['entity' => 'User', 'status' => 'reconstructed', 'reason' => 'Target environment user identity is authoritative and not overwritten.'],
            ['entity' => 'UserFeatureOverride', 'status' => 'exported_imported', 'reason' => 'Exported in globalState and restored by feature key.'],
            ['entity' => 'UserPreference', 'status' => 'exported_imported', 'reason' => 'Exported in globalState and restored by preference name.'],
            ['entity' => 'Vehicle', 'status' => 'exported_imported', 'reason' => 'Primary backup object.'],
            ['entity' => 'VehicleAssignment', 'status' => 'omitted', 'reason' => 'Cross-user access control mappings are environment-specific.'],
            ['entity' => 'VehicleImage', 'status' => 'exported_imported', 'reason' => 'Exported with image files for ZIP backups.'],
            ['entity' => 'VehicleMake', 'status' => 'exported_imported', 'reason' => 'Exported in globalState and also reconstructed if missing.'],
            ['entity' => 'VehicleModel', 'status' => 'exported_imported', 'reason' => 'Exported in globalState and also reconstructed if missing.'],
            ['entity' => 'VehicleStatusHistory', 'status' => 'exported_imported', 'reason' => 'Exported in vehicle payload.'],
            ['entity' => 'VehicleType', 'status' => 'exported_imported', 'reason' => 'Exported in globalState and also reconstructed if missing.'],
        ];

        $globalCounts = [];
        foreach ($globalState as $key => $value) {
            $globalCounts[$key] = count($value);
        }

        return [
            'schemaVersion' => 2,
            'generatedAt' => (new \DateTimeImmutable())->format('c'),
            'counts' => [
                'vehicles' => count($vehicles),
                'stockItems' => count($stockItems),
                'globalState' => $globalCounts,
            ],
            'entityCoverage' => $coverage,
        ];
    }

    /**
     * Perform memory cleanup
     */
    private function performMemoryCleanup(): void
    {
        $this->entityManager->clear();
        gc_collect_cycles();
        $this->logger->debug(
            '[export] Memory cleanup performed', [
            'memoryUsageMB' => round(memory_get_usage(true) / 1024 / 1024, 2)
            ]
        );
    }
}
