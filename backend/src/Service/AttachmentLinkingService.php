<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Attachment;
use App\Entity\Consumable;
use App\Entity\FuelRecord;
use App\Entity\MotRecord;
use App\Entity\Part;
use App\Entity\ServiceRecord;
use App\Entity\Vehicle;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * class AttachmentLinkingService
 *
 * Centralized service for managing attachment-entity relationships
 * This service handles:
 * - Bidirectional linking between attachments and entities
 * - File reorganization to proper directory structure
 * - Consistency validation
 */
class AttachmentLinkingService
{
    // Maps compound entity type names to simple folder names
    // Input is normalized (lowercase, underscores removed) before lookup
    private const ENTITY_TYPE_MAP = [
        'motrecord' => 'mot',
        'servicerecord' => 'service',
        'fuelrecord' => 'fuel'
    ];

    /**
     * function __construct
     *
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface $logger
     * @param SluggerInterface $slugger
     * @param string $projectDir
     *
     * @return void
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private SluggerInterface $slugger,
        private string $projectDir
    ) {
    }

    /**
     * function linkAttachmentToEntity
     *
     * Link an attachment to an entity with proper bidirectional relationship
     *
     * @param Attachment $attachment
     * @param object $entity
     * @param string $entityType
     * @param bool $reorganizeFile
     *
     * @return void
     */
    public function linkAttachmentToEntity(
        Attachment $attachment,
        object $entity,
        string $entityType,
        bool $reorganizeFile = true
    ): void {
        // Normalize entity type
        $normalizedType = $this->normalizeEntityType($entityType);

        // Set entity -> attachment relationship FIRST (this works even without ID)
        // Doctrine will handle the bidirectional relationship when both are flushed
        if (method_exists($entity, 'setReceiptAttachment')) {
            $entity->setReceiptAttachment($attachment);
            // IMPORTANT: Persist entity so Doctrine tracks this change
            $this->entityManager->persist($entity);
            $this->logger->debug('[AttachmentLinking] Set receiptAttachment on entity', [
                'entityType' => $entityType,
                'entityId' => method_exists($entity, 'getId') ? $entity->getId() : 'new',
                'attachmentId' => $attachment->getId()
            ]);
        }

        // Set vehicle if available
        $vehicle = $this->getVehicleFromEntity($entity);
        if ($vehicle) {
            $attachment->setVehicle($vehicle);
        }

        // If entity has ID, set attachment -> entity relationship immediately
        // If entity doesn't have ID yet, it will be set after flush via callback
        if (method_exists($entity, 'getId') && $entity->getId()) {
            $attachment->setEntityType($entityType);
            $attachment->setEntityId((int) $entity->getId());
            $this->logger->info('[AttachmentLinking] Linked attachment to entity', [
                'attachmentId' => $attachment->getId(),
                'entityType' => $entityType,
                'entityId' => $entity->getId(),
                'vehicleId' => $vehicle?->getId()
            ]);
        } else {
            // Entity doesn't have ID yet - set entity type for now
            // The entity_id will need to be set after flush
            $attachment->setEntityType($entityType);
            
            $this->logger->info('[AttachmentLinentityTypeetId()', [
                'entityType' => $entityType,
                'vehicleId' => $vehicle?->getId()
            ]);
        }

        // Persist attachment (entity should be persisted by caller)
        $this->entityManager->persist($attachment);

        // Reorganize file if requested and vehicle is available
        if ($reorganizeFile && $vehicle && $attachment->getId()) {
            $this->reorganizeFile($attachment, $vehicle, $normalizedType);
        }
    }

    /**
     * function unlinkAttachment
     *
     * Unlink an attachment from its current entity
     *
     * @param Attachment $attachment
     * @param object $entity
     *
     * @return void
     */
    public function unlinkAttachment(Attachment $attachment, ?object $entity = null): void
    {
        $attachment->setEntityType(null);
        $attachment->setEntityId(null);

        if ($entity && method_exists($entity, 'setReceiptAttachment')) {
            $entity->setReceiptAttachment(null);
            $this->entityManager->persist($entity);
        }

        $this->entityManager->persist($attachment);

        $this->logger->info('[AttachmentLinking] Unlinked attachment', [
            'attachmentId' => $attachment->getId()
        ]);
    }

    /**
     * function finalizeAttachmentLink
     *
     * Call this AFTER flush to set entity_id on attachment if entity was new
     * This ensures the attachment has the correct entity_id after the entity gets its ID
     *
     * @param object $entity
     *
     * @return void
     */
    public function finalizeAttachmentLink(object $entity): void
    {
        if (!method_exists($entity, 'getReceiptAttachment') || !method_exists($entity, 'getId')) {
            return;
        }

        $attachment = $entity->getReceiptAttachment();
        if (!$attachment) {
            return;
        }

        $entityId = $entity->getId();
        if (!$entityId) {
            $this->logger->warning('[AttachmentLinking] Cannot finalize - entity still has no ID');
            return;
        }

        // Only update if entity_id is not set or doesn't match
        if ($attachment->getEntityId() !== $entityId) {
            $attachment->setEntityId($entityId);
            $this->entityManager->persist($attachment);
            
            $this->logger->info('[AttachmentLinking] Finalized attachment entity_id', [
                'attachmentId' => $attachment->getId(),
                'entityId' => $entityId,
                'entityType' => $attachment->getEntityType()
            ]);
        }
    }

    /**
     * function processReceiptAttachmentId
     *
     * Find or create attachment and link to entity
     * Used by controllers when processing receiptAttachmentId from frontend
     *
     * @param int $attachmentId
     * @param object $entity
     * @param string $entityType
     *
     * @return Attachment
     */
    public function processReceiptAttachmentId(
        ?int $attachmentId,
        object $entity,
        string $entityType
    ): ?Attachment {
        $this->logger->info('[AttachmentLinking] processReceiptAttachmentId called', [
            'attachmentId' => $attachmentId,
            'entityType' => $entityType,
            'entityClass' => get_class($entity),
            'entityId' => method_exists($entity, 'getId') ? $entity->getId() : 'unknown'
        ]);

        if ($attachmentId === null || $attachmentId === 0) {
            // Unlink current attachment if any
            if (method_exists($entity, 'getReceiptAttachment') && method_exists($entity, 'setReceiptAttachment')) {
                $currentAttachment = $entity->getReceiptAttachment();
                if ($currentAttachment) {
                    $this->unlinkAttachment($currentAttachment, $entity);
                }
            }
            return null;
        }

        $attachment = $this->entityManager->getRepository(Attachment::class)->find($attachmentId);
        if (!$attachment) {
            $this->logger->warning('[AttachmentLinking] Attachment not found', [
                'attachmentId' => $attachmentId,
                'entityType' => $entityType
            ]);
            return null;
        }

        $this->logger->info('[AttachmentLinking] Found attachment, linking to entity', [
            'attachmentId' => $attachment->getId(),
            'attachmentFilename' => $attachment->getFilename()
        ]);

        $this->linkAttachmentToEntity($attachment, $entity, $entityType);
        
        // Force flush to ensure the relationship is persisted immediately
        $this->entityManager->flush();
        
        // Verify the link was set
        if (method_exists($entity, 'getReceiptAttachment')) {
            $linked = $entity->getReceiptAttachment();
            $this->logger->info('[AttachmentLinking] After linking and flush - entity receiptAttachment', [
                'linkedAttachmentId' => $linked ? $linked->getId() : 'NULL',
                'entityId' => $entity->getId()
            ]);
        }
        
        return $attachment;
    }

    /**
     * function normalizeEntityType
     *
     * Normalize entity type to consistent format
     *
     * @param string $entityType
     *
     * @return string
     */
    public function normalizeEntityType(string $entityType): string
    {
        // Remove underscores and lowercase - 'mot_record' becomes 'motrecord'
        $normalized = strtolower(str_replace('_', '', trim($entityType)));
        // Map compound names to simple names, or return as-is if already simple
        return self::ENTITY_TYPE_MAP[$normalized] ?? $normalized;
    }

    /**
     * function getVehicleFromEntity
     *
     * Get vehicle from entity if available
     *
     * @param object $entity
     *
     * @return Vehicle
     */
    private function getVehicleFromEntity(object $entity): ?Vehicle
    {
        if (method_exists($entity, 'getVehicle')) {
            return $entity->getVehicle();
        }
        return null;
    }

    /**
     * function reorganizeFile
     *
     * Reorganize attachment file into proper directory structure
     *
     * @param Attachment $attachment
     * @param Vehicle $vehicle
     * @param string $entityType
     *
     * @return bool
     */
    public function reorganizeFile(Attachment $attachment, Vehicle $vehicle, ?string $entityType = null): bool
    {
        $uploadsRoot = $this->projectDir . '/uploads';
        $currentStoragePath = $attachment->getStoragePath();
        
        if (!$currentStoragePath) {
            $this->logger->debug('[AttachmentLinking] No storage path for attachment', [
                'attachmentId' => $attachment->getId()
            ]);
            return false;
        }

        $currentPath = $uploadsRoot . '/' . ltrim($currentStoragePath, '/');
        if (!file_exists($currentPath)) {
            $this->logger->warning('[AttachmentLinking] File not found for reorganization', [
                'attachmentId' => $attachment->getId(),
                'path' => $currentPath
            ]);
            return false;
        }

        $type = $entityType ?: $attachment->getEntityType();
        if (!$type) {
            $this->logger->debug('[AttachmentLinking] No entity type for reorganization', [
                'attachmentId' => $attachment->getId()
            ]);
            return false;
        }

        // Build target path
        $reg = $vehicle->getRegistrationNumber() 
            ?: $vehicle->getName() 
            ?: ('vehicle-' . $vehicle->getId());
        $regSlug = strtolower((string) $this->slugger->slug($reg));
        $typeSlug = strtolower($type); // Already normalized to simple word (mot, service, fuel, etc.)
        
        $newSubDir = $regSlug . '/' . $typeSlug;

        // Check if already in correct location
        if (str_contains($currentStoragePath, $newSubDir . '/')) {
            return true;
        }

        $uploadDir = $uploadsRoot . '/vehicles/' . $newSubDir;
        
        try {
            if (!is_dir($uploadDir)) {
                if (!@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                    throw new \RuntimeException("Failed to create directory: $uploadDir");
                }
            }

            $filename = $attachment->getFilename();
            $newPath = $uploadDir . '/' . $filename;
            
            // Handle filename collision
            if (file_exists($newPath) && $newPath !== $currentPath) {
                $pathInfo = pathinfo($filename);
                $filename = $pathInfo['filename'] . '_' . uniqid() . '.' . ($pathInfo['extension'] ?? '');
                $newPath = $uploadDir . '/' . $filename;
                $attachment->setFilename($filename);
            }

            if (!rename($currentPath, $newPath)) {
                throw new \RuntimeException("Failed to move file from $currentPath to $newPath");
            }

            $newStoragePath = 'vehicles/' . $newSubDir . '/' . $filename;
            $attachment->setStoragePath($newStoragePath);
            
            $this->entityManager->persist($attachment);

            $this->logger->info('[AttachmentLinking] Reorganized file', [
                'attachmentId' => $attachment->getId(),
                'from' => $currentStoragePath,
                'to' => $newStoragePath
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('[AttachmentLinking] Failed to reorganize file', [
                'attachmentId' => $attachment->getId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * function validateRelationship
     *
     * Validate attachment-entity relationship integrity
     *
     * @param Attachment $attachment
     * @param object $entity
     * @param string $entityType
     *
     * @return array
     */
    public function validateRelationship(Attachment $attachment, object $entity, string $entityType): array
    {
        $issues = [];

        // Check attachment -> entity
        if ($attachment->getEntityType() !== $entityType) {
            $issues[] = "Attachment entityType mismatch: {$attachment->getEntityType()} vs {$entityType}";
        }
        
        if ($attachment->getEntityId() !== $entity->getId()) {
            $issues[] = "Attachment entityId mismatch: {$attachment->getEntityId()} vs {$entity->getId()}";
        }

        // Check entity -> attachment
        if (method_exists($entity, 'getReceiptAttachment')) {
            $linkedAttachment = $entity->getReceiptAttachment();
            if ($linkedAttachment?->getId() !== $attachment->getId()) {
                $issues[] = "Entity receiptAttachment mismatch";
            }
        }

        // Check vehicle
        $entityVehicle = $this->getVehicleFromEntity($entity);
        if ($entityVehicle && $attachment->getVehicle()?->getId() !== $entityVehicle->getId()) {
            $issues[] = "Vehicle mismatch: {$attachment->getVehicle()?->getId()} vs {$entityVehicle->getId()}";
        }

        return $issues;
    }

    /**
     * function repairRelationship
     *
     * Fix broken relationships for an entity's attachment
     *
     * @param object $entity
     * @param string $entityType
     *
     * @return bool
     */
    public function repairRelationship(object $entity, string $entityType): bool
    {
        if (!method_exists($entity, 'getReceiptAttachment') || !method_exists($entity, 'getId')) {
            return false;
        }

        $attachment = $entity->getReceiptAttachment();
        if (!$attachment) {
            return true; // No attachment to repair
        }

        $issues = $this->validateRelationship($attachment, $entity, $entityType);
        if (empty($issues)) {
            return true; // Already valid
        }

        $this->logger->info('[AttachmentLinking] Repairing relationship', [
            'attachmentId' => $attachment->getId(),
            'entityType' => $entityType,
            'entityId' => $entity->getId(),
            'issues' => $issues
        ]);

        // Re-link to fix
        $this->linkAttachmentToEntity($attachment, $entity, $entityType, true);
        return true;
    }

    /**
     * function resolveEntityByTypeAndId
     *
     * Resolve an entity by its type and ID
     *
     * @param string $entityType
     * @param int $entityId
     *
     * @return object
     */
    public function resolveEntityByTypeAndId(string $entityType, int $entityId): ?object
    {
        $normalized = $this->normalizeEntityType(strtolower(trim($entityType)));
        
        $entityClass = match ($normalized) {
            'mot' => MotRecord::class,
            'service' => ServiceRecord::class,
            'fuel' => FuelRecord::class,
            'part' => Part::class,
            'consumable' => Consumable::class,
            'vehicle' => Vehicle::class,
            default => null,
        };

        if (!$entityClass) {
            $this->logger->warning('[AttachmentLinking] Unknown entity type', [
                'entityType' => $entityType
            ]);
            return null;
        }

        return $this->entityManager->getRepository($entityClass)->find($entityId);
    }
}
