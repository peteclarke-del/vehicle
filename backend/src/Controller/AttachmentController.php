<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Attachment;
use App\Entity\Vehicle;
use App\Entity\FuelRecord;
use App\Entity\Part;
use App\Entity\Consumable;
use App\Entity\ServiceRecord;
use App\Entity\MotRecord;
use App\Entity\RoadTax;
use App\Entity\InsurancePolicy;
use App\Entity\Todo;
use App\Service\ReceiptOcrService;
use App\Service\AttachmentLinkingService;
use App\Controller\Trait\UserSecurityTrait;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/attachments')]

/**
 * class AttachmentController
 */
class AttachmentController extends AbstractController
{
    use UserSecurityTrait;

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    private const MAX_FILE_SIZE_MB = 100; // Configurable
    private const OCR_SUPPORTED_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf'
    ];

    /**
     * function __construct
     *
     * @param EntityManagerInterface $entityManager
     * @param SluggerInterface $slugger
     * @param ReceiptOcrService $ocrService
     * @param LoggerInterface $logger
     * @param Filesystem $filesystem
     * @param string $projectDir
     * @param int $uploadMaxBytes
     *
     * @return void
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        private ReceiptOcrService $ocrService,
        private LoggerInterface $logger,
        private Filesystem $filesystem,
        private AttachmentLinkingService $attachmentLinkingService,
        private string $projectDir,
        private int $uploadMaxBytes
    ) {
    }

    /**
     * function getUploadDir
     *
     * @return string
     */
    private function getUploadDir(): string
    {
        return $this->projectDir . '/uploads/vehicles';
    }

    /**
     * function getAttachmentFilePath
     *
     * @param Attachment $attachment
     *
     * @return string
     */
    private function getAttachmentFilePath(Attachment $attachment): string
    {
        $storagePath = $attachment->getStoragePath();
        
        if ($storagePath) {
            return $this->projectDir . '/uploads/' . ltrim($storagePath, '/');
        }

        return $this->getUploadDir() . '/' . $attachment->getFilename();
    }

    /**
     * function generateFilename
     *
     * @param UploadedFile $file
     *
     * @return string
     */
    private function generateFilename(UploadedFile $file): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        
        return sprintf(
            '%s-%s.%s',
            $safeFilename,
            uniqid('', true),
            $file->guessExtension()
        );
    }

    /**
     * function getStorageSubdirectory
     *
     * @param Vehicle $vehicle
     * @param string $category
     *
     * @return string
     */
    private function getStorageSubdirectory(?Vehicle $vehicle, ?string $category): string
    {
        if (!$vehicle) {
            return 'misc';
        }

        $reg = $vehicle->getRegistrationNumber()
            ?: $vehicle->getName()
            ?: ('vehicle-' . $vehicle->getId());

        $regSlug = strtolower((string) $this->slugger->slug($reg));
        $categorySlug = $category ? strtolower((string) $this->slugger->slug($category)) : 'misc';

        return $regSlug . '/' . $categorySlug;
    }

    /**
     * function resolveVehicleForAttachment
     *
     * @param string $entityType
     * @param int $entityId
     *
     * @return Vehicle
     */
    private function resolveVehicleForAttachment(?string $entityType, ?int $entityId): ?Vehicle
    {
        if (!$entityType || !$entityId) {
            return null;
        }

        return match ($entityType) {
            'vehicle' => $this->entityManager->getRepository(Vehicle::class)->find($entityId),
            'fuel' => $this->entityManager->getRepository(FuelRecord::class)->find($entityId)?->getVehicle(),
            'part' => $this->entityManager->getRepository(Part::class)->find($entityId)?->getVehicle(),
            'consumable' => $this->entityManager->getRepository(Consumable::class)->find($entityId)?->getVehicle(),
            'service' => $this->entityManager->getRepository(ServiceRecord::class)->find($entityId)?->getVehicle(),
            'mot' => $this->entityManager->getRepository(MotRecord::class)->find($entityId)?->getVehicle(),
            'roadTax' => $this->entityManager->getRepository(RoadTax::class)->find($entityId)?->getVehicle(),
            'insurancePolicy' => $this->entityManager->getRepository(InsurancePolicy::class)->find($entityId)?->getVehicles()->first() ?: null,
            'todo' => $this->entityManager->getRepository(Todo::class)->find($entityId)?->getVehicle(),
            default => null,
        };
    }

    /**
     * function validateUploadedFile
     *
     * @param UploadedFile $file
     *
     * @return string
     */
    private function validateUploadedFile(UploadedFile $file): ?string
    {
        // Check file size
        if ($file->getSize() > $this->uploadMaxBytes) {
            $maxMb = (int) ceil($this->uploadMaxBytes / (1024 * 1024));
            return "File too large (max {$maxMb}MB)";
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return "File type not allowed: {$mimeType}";
        }

        // Additional security check: verify file extension matches MIME type
        $allowedExtensions = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
            'application/pdf' => ['pdf'],
            'application/msword' => ['doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            'application/vnd.ms-excel' => ['xls'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        ];

        $extension = strtolower($file->getClientOriginalExtension());
        if (isset($allowedExtensions[$mimeType]) && !in_array($extension, $allowedExtensions[$mimeType], true)) {
            return "File extension does not match MIME type";
        }

        return null;
    }

    /**
     * function createAttachmentEntity
     *
     * @param UploadedFile $file
     * @param string $filename
     * @param string $storagePath
     * @param string $entityType
     * @param int $entityId
     * @param string $description
     * @param string $category
     *
     * @return Attachment
     */
    private function createAttachmentEntity(
        string $filename,
        string $originalName,
        string $mimeType,
        int $fileSize,
        string $storagePath,
        ?Vehicle $vehicle = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        ?string $category = null
    ): Attachment {
        $attachment = new Attachment();
        $attachment
            ->setFilename($filename)
            ->setOriginalName($originalName)
            ->setMimeType($mimeType)
            ->setFileSize($fileSize)
            ->setUser($this->getUserEntity())
            ->setStoragePath($storagePath);

        if ($entityType) {
            $attachment->setEntityType($entityType);
        }
        
        if ($entityId) {
            $attachment->setEntityId($entityId);
            
            if ($entityType === 'vehicle') {
                $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($entityId);
                if ($vehicle) {
                    $attachment->setVehicle($vehicle);
                }
            }
        }

        if ($vehicle) {
            $attachment->setVehicle($vehicle);
            // When an admin uploads to a vehicle, attribute the attachment to the vehicle's owner
            $owner = $vehicle->getOwner();
            if ($owner) {
                $attachment->setUser($owner);
            }
        }
        
        if ($description) {
            $attachment->setDescription($description);
        }
        
        if ($category) {
            $attachment->setCategory($category);
        }

        return $attachment;
    }

    /**
     * function reorganizeAttachmentFile
     *
     * @param Attachment $attachment
     * @param Vehicle $vehicle
     *
     * @return bool
     */
    private function reorganizeAttachmentFile(Attachment $attachment, Vehicle $vehicle): bool
    {
        $currentPath = $this->getAttachmentFilePath($attachment);
        
        if (!$this->filesystem->exists($currentPath)) {
            $this->logger->warning('Cannot reorganize - file not found', ['path' => $currentPath]);
            return false;
        }

        $entityType = $attachment->getEntityType();
        if (!$entityType) {
            $this->logger->debug('No entity type set, skipping reorganization');
            return false;
        }

        $reg = $vehicle->getRegistrationNumber() 
            ?: $vehicle->getName() 
            ?: ('vehicle-' . $vehicle->getId());
        $regSlug = strtolower((string) $this->slugger->slug($reg));
        $category = $attachment->getCategory() ?: 'misc';
        $categorySlug = strtolower((string) $this->slugger->slug($category));
        
        $newSubDir = $regSlug . '/' . $categorySlug;
        $newDir = $this->getUploadDir() . '/' . $newSubDir;

        // Check if already in correct location
        $currentStoragePath = $attachment->getStoragePath();
        if ($currentStoragePath && str_contains($currentStoragePath, $newSubDir . '/')) {
            $this->logger->debug('File already in correct location', ['path' => $currentStoragePath]);
            return false;
        }

        try {
            $this->filesystem->mkdir($newDir);
            
            $filename = $attachment->getFilename();
            $newPath = $newDir . '/' . $filename;
            
            $this->filesystem->rename($currentPath, $newPath);
            
            $newStoragePath = 'vehicles/' . $newSubDir . '/' . $filename;
            $attachment->setStoragePath($newStoragePath);
            
            $this->logger->info('Reorganized attachment file', [
                'from' => $currentStoragePath,
                'to' => $newStoragePath,
                'vehicle' => $reg
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error reorganizing file', [
                'error' => $e->getMessage(),
                'from' => $currentPath,
                'to' => $newPath ?? null
            ]);
            return false;
        }
    }

    #[Route('', methods: ['POST'])]

    /**
     * function upload
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function upload(Request $request): JsonResponse
    {
        $user = $this->getUserEntity();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $entityType = $request->request->get('entityType');
        $entityId = $request->request->getInt('entityId');
        $vehicleId = $request->request->getInt('vehicleId');
        $description = $request->request->get('description');
        $category = $request->request->get('category');
        
        $this->logger->info('[AttachmentUpload] Request params', [
            'entityType' => $entityType,
            'entityId' => $entityId,
            'vehicleId' => $vehicleId,
            'category' => $category
        ]);
        
        // Use entityType as category if not explicitly provided
        // This ensures files are stored in the correct folder (e.g., mot_record, service_record)
        if (!$category && $entityType) {
            $category = $this->attachmentLinkingService->normalizeEntityType($entityType);
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'No file provided'], 400);
        }

        $this->logger->info('Upload attempt', [
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize()
        ]);

        // File validation
        if ($error = $this->validateUploadedFile($file)) {
            return $this->json(['error' => $error], 400);
        }

        // Capture metadata before moving the file
        $originalName = $file->getClientOriginalName();
        $mimeType = (string) $file->getMimeType();
        $fileSize = (int) ($file->getSize() ?? 0);

        // Generate unique filename
        $filename = $this->generateFilename($file);
        $vehicle = $this->resolveVehicleForAttachment($entityType, $entityId);
        
        // If entity doesn't exist yet, try to resolve vehicle directly from vehicleId
        if (!$vehicle && $vehicleId) {
            $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($vehicleId);
        }
        
        $storageSubDir = $this->getStorageSubdirectory($vehicle, $category);
        $uploadDir = $this->getUploadDir() . '/' . $storageSubDir;

        try {
            $this->filesystem->mkdir($uploadDir);
            $file->move($uploadDir, $filename);
            
            $this->logger->info('File uploaded successfully', [
                'path' => $uploadDir . '/' . $filename
            ]);
        } catch (FileException $e) {
            $this->logger->error('Upload failed', ['error' => $e->getMessage()]);
            return $this->json(['error' => 'Failed to upload file'], 500);
        }

        $storagePath = 'vehicles/' . $storageSubDir . '/' . $filename;
        $attachment = $this->createAttachmentEntity(
            $filename,
            $originalName,
            $mimeType,
            $fileSize,
            $storagePath,
            $vehicle,
            $entityType,
            $entityId,
            $description,
            $category
        );

        $this->entityManager->persist($attachment);
        $this->entityManager->flush();

        // If entity exists, create bidirectional link (entity -> attachment)
        if ($entityType && $entityId) {
            $entity = $this->attachmentLinkingService->resolveEntityByTypeAndId($entityType, $entityId);
            if ($entity && method_exists($entity, 'setReceiptAttachment')) {
                $this->attachmentLinkingService->linkAttachmentToEntity($attachment, $entity, $entityType, false);
                $this->entityManager->flush();
                $this->logger->info('Linked attachment to entity at upload time', [
                    'attachmentId' => $attachment->getId(),
                    'entityType' => $entityType,
                    'entityId' => $entityId
                ]);
            }
        }

        return $this->json($this->serializeAttachment($attachment), 201);
    }

    #[Route('/{id}/ocr', methods: ['GET'])]

    /**
     * function processOcr
     *
     * @param int $id
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function processOcr(int $id, Request $request): JsonResponse
    {
        $user = $this->getUserEntity();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $attachment = $this->entityManager->getRepository(Attachment::class)->findOneBy([
            'id' => $id,
            'user' => $user
        ]);
        
        if (!$attachment) {
            return $this->json(['error' => 'Attachment not found'], 404);
        }

        $filePath = $this->getAttachmentFilePath($attachment);
        if (!$this->filesystem->exists($filePath)) {
            return $this->json(['error' => 'File not found'], 404);
        }

        $mimeType = $attachment->getMimeType();
        if (!in_array($mimeType, self::OCR_SUPPORTED_TYPES, true)) {
            return $this->json(['error' => 'OCR only supports images and PDFs'], 400);
        }

        $type = $request->query->get('type', 'fuel');
        
        $data = match ($type) {
            'part', 'consumable' => $this->ocrService->extractPartReceiptData($filePath),
            'service' => $this->ocrService->extractServiceReceiptData($filePath),
            default => $this->ocrService->extractReceiptData($filePath),
        };

        return $this->json($data);
    }

    #[Route('', methods: ['GET'])]

    /**
     * function list
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUserEntity();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $entityType = $request->query->get('entityType');
        $entityId = $request->query->getInt('entityId');
        $category = $request->query->get('category');

        $qb = $this->entityManager->getRepository(Attachment::class)
            ->createQueryBuilder('a')
            ->where('a.user = :user')
            ->setParameter('user', $user);

        if ($entityType) {
            $qb->andWhere('a.entityType = :entityType')
                ->setParameter('entityType', $entityType);
        }

        if ($entityId) {
            $qb->andWhere('a.entityId = :entityId')
                ->setParameter('entityId', $entityId);
        }

        if ($category !== null && $category !== '') {
            $qb->andWhere('a.category = :category')
                ->setParameter('category', $category);
        }

        $attachments = $qb->orderBy('a.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($attachments as $attachment) {
            $data[] = $this->serializeAttachment($attachment);
        }

        return $this->json($data);
    }

    #[Route('/{id}', methods: ['GET'])]

    /**
     * function download
     *
     * @param int $id
     * @param Request $request
     *
     * @return BinaryFileResponse|JsonResponse
     */
    public function download(int $id, Request $request): BinaryFileResponse|JsonResponse
    {
        $user = $this->getUserEntity();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $attachment = $this->entityManager->getRepository(Attachment::class)->findOneBy([
            'id' => $id,
            'user' => $user
        ]);
        
        if (!$attachment) {
            return $this->json(['error' => 'Attachment not found'], 404);
        }

        if ($request->query->get('metadata') === 'true') {
            return $this->json($this->serializeAttachment($attachment));
        }

        $filePath = $this->getAttachmentFilePath($attachment);
        if (!$this->filesystem->exists($filePath)) {
            return $this->json(['error' => 'File not found'], 404);
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $attachment->getOriginalName()
        );

        return $response;
    }

    #[Route('/{id}', methods: ['DELETE'])]

    /**
     * function delete
     *
     * @param int $id
     *
     * @return JsonResponse
     */
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUserEntity();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $attachment = $this->entityManager->getRepository(Attachment::class)->findOneBy([
            'id' => $id,
            'user' => $user
        ]);
        
        if (!$attachment) {
            return $this->json(['error' => 'Attachment not found'], 404);
        }

        $filePath = $this->getAttachmentFilePath($attachment);
        if ($this->filesystem->exists($filePath)) {
            $this->filesystem->remove($filePath);
        }

        $this->entityManager->remove($attachment);
        $this->entityManager->flush();

        return $this->json(['message' => 'Attachment deleted']);
    }

    #[Route('/{id}', methods: ['PUT'])]

    /**
     * function update
     *
     * @param int $id
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->getUserEntity();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $attachment = $this->entityManager->getRepository(Attachment::class)->findOneBy([
            'id' => $id,
            'user' => $user
        ]);
        
        if (!$attachment) {
            return $this->json(['error' => 'Attachment not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['error' => 'Invalid JSON data'], 400);
        }

        // Handle simple field updates
        if (isset($data['description'])) {
            $attachment->setDescription($data['description']);
        }
        if (isset($data['category'])) {
            $attachment->setCategory($data['category']);
        }

        // Handle entity linking - use AttachmentLinkingService for consistency
        if (array_key_exists('entityType', $data) || array_key_exists('entityId', $data)) {
            $entityType = $data['entityType'] ?? $attachment->getEntityType();
            $entityId = $data['entityId'] ?? $attachment->getEntityId();

            if ($entityType && $entityId) {
                // Resolve the entity and link properly
                $entity = $this->attachmentLinkingService->resolveEntityByTypeAndId($entityType, $entityId);
                if ($entity) {
                    $this->attachmentLinkingService->linkAttachmentToEntity(
                        $attachment,
                        $entity,
                        $entityType,
                        true // reorganize file
                    );
                } else {
                    // Entity not found, just set the raw values
                    $attachment->setEntityType($entityType);
                    $attachment->setEntityId($entityId);
                }
            } elseif (!$entityType && !$entityId) {
                // Clear entity association
                $attachment->setEntityType(null);
                $attachment->setEntityId(null);
            }
        }

        $this->entityManager->flush();

        return $this->json($this->serializeAttachment($attachment));
    }

    /**
     * function serializeAttachment
     *
     * @param Attachment $attachment
     *
     * @return array
     */
    private function serializeAttachment(Attachment $attachment): array
    {
        return [
            'id' => $attachment->getId(),
            'filename' => $attachment->getFilename(),
            'originalName' => $attachment->getOriginalName(),
            'mimeType' => $attachment->getMimeType(),
            'fileSize' => $attachment->getFileSize(),
            'fileSizeFormatted' => $attachment->getFileSizeFormatted(),
            'uploadedAt' => $attachment->getUploadedAt()->format('Y-m-d H:i:s'),
            'entityType' => $attachment->getEntityType(),
            'entityId' => $attachment->getEntityId(),
            'description' => $attachment->getDescription(),
            'category' => $attachment->getCategory(),
            'storagePath' => $attachment->getStoragePath(),
            'downloadUrl' => '/api/attachments/' . $attachment->getId(),
            'isImage' => $attachment->isImage(),
            'isPdf' => $attachment->isPdf(),
        ];
    }
}
