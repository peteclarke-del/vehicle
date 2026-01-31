<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Attachment;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Service\ReceiptOcrService;
use App\Entity\Vehicle;

#[Route('/api/attachments')]

/**
 * class AttachmentController
 */
class AttachmentController extends AbstractController
{
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

    /**
     * function __construct
     *
     * @param EntityManagerInterface $entityManager
     * @param SluggerInterface $slugger
     * @param ReceiptOcrService $ocrService
     *
     * @return void
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        private ReceiptOcrService $ocrService,
        private int $uploadMaxBytes,
        private LoggerInterface $logger
    ) {
    }

    /**
     * function getUploadDir
     *
     * @return string
     */
    private function getUploadDir(): string
    {
        return $this->getParameter('kernel.project_dir') . '/uploads/attachments';
    }

    private function getAttachmentFilePath(Attachment $attachment): string
    {
        $uploadsRoot = $this->getParameter('kernel.project_dir') . '/uploads';
        $storagePath = $attachment->getStoragePath();
        if ($storagePath) {
            return $uploadsRoot . '/' . ltrim($storagePath, '/');
        }

        return $this->getUploadDir() . '/' . $attachment->getFilename();
    }

    /**
     * function getUserEntity
     *
     * @return \App\Entity\User
     */
    private function getUserEntity(): ?\App\Entity\User
    {
        $user = $this->getUser();
        return $user instanceof \App\Entity\User ? $user : null;
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
        $entityId = $request->request->get('entityId');
        $description = $request->request->get('description');
        $category = $request->request->get('category');

        /** @var UploadedFile $file */
        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'No file provided'], 400);
        }

        // Log upload attempt
        $fileSize = $file->getSize();
        $this->logger->info('Upload attempt', ['filename' => $file->getClientOriginalName(), 'size' => $fileSize]);

        // Validate file size
        $contentLength = (int) $request->server->get('CONTENT_LENGTH', 0);
        if ($contentLength > $this->uploadMaxBytes) {
            $maxMb = (int) ceil($this->uploadMaxBytes / (1024 * 1024));
            return $this->json(['error' => 'File too large (max ' . $maxMb . 'MB)'], 413);
        }

        // Validate MIME type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            return $this->json(['error' => 'File type not allowed: ' . $mimeType], 400);
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        $uploadDir = $this->getUploadDir();
        $storageSubDir = 'misc';
        $vehicle = null;
        if ($entityType === 'vehicle' && $entityId) {
            $vehicle = $this->entityManager->getRepository(Vehicle::class)->find((int) $entityId);
            $reg = $vehicle?->getRegistrationNumber() ?: $vehicle?->getName() ?: ('vehicle-' . $entityId);
            $storageSubDir = strtolower((string) $this->slugger->slug($reg));
        } elseif ($entityType && $entityId) {
            $storageSubDir = strtolower((string) $this->slugger->slug($entityType . '-' . $entityId));
        }

        $uploadDir = $uploadDir . '/' . $storageSubDir;
        $this->logger->debug('Upload directory', ['path' => $uploadDir]);
        $this->logger->debug('Directory status', ['exists' => is_dir($uploadDir), 'writable' => is_writable(dirname($uploadDir))]);
        // Logged above with directory status

        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                $this->logger->error('Failed to create directory', ['path' => $uploadDir]);
                return $this->json(['error' => 'Failed to create upload directory: ' . $uploadDir], 500);
            }
            $this->logger->info('Created directory', ['path' => $uploadDir]);
        }

        try {
            $file->move($uploadDir, $newFilename);
            $this->logger->info('File uploaded successfully', ['path' => $uploadDir . '/' . $newFilename]);
        } catch (FileException $e) {
            $this->logger->error('Upload failed', ['error' => $e->getMessage()]);
            return $this->json(['error' => 'Failed to upload file: ' . $e->getMessage()], 500);
        }

        $storagePath = 'attachments/' . $storageSubDir . '/' . $newFilename;

        $attachment = new Attachment();
        $attachment->setFilename($newFilename);
        $attachment->setOriginalName($file->getClientOriginalName());
        $attachment->setMimeType($mimeType);
        $attachment->setFileSize($fileSize ?? (int) @filesize($uploadDir . '/' . $newFilename));
        $attachment->setUser($user);
        $attachment->setStoragePath($storagePath);
        if ($vehicle) {
            $attachment->setVehicle($vehicle);
        }

        if ($entityType) {
            $attachment->setEntityType($entityType);
        }
        if ($entityId) {
            $attachment->setEntityId((int)$entityId);
        }
        if ($description) {
            $attachment->setDescription($description);
        }
        if ($category) {
            $attachment->setCategory($category);
        }

        $this->entityManager->persist($attachment);
        $this->entityManager->flush();

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

        $attachment = $this->entityManager->getRepository(Attachment::class)->find($id);
        if (!$attachment || $attachment->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Attachment not found'], 404);
        }

        $filePath = $this->getAttachmentFilePath($attachment);
        if (!file_exists($filePath)) {
            return $this->json(['error' => 'File not found'], 404);
        }

        $mimeType = $attachment->getMimeType();
        $imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mimeType, $imageTypes) && $mimeType !== 'application/pdf') {
            return $this->json(['error' => 'OCR only supports images and PDFs'], 400);
        }

        // Determine OCR type from query parameter (fuel, part, service)
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
        $entityId = $request->query->get('entityId');
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

        if ($category) {
            $qb->andWhere('a.category = :category')
                ->setParameter('category', $category);
        }

        $attachments = $qb->orderBy('a.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->json(array_map([$this, 'serializeAttachment'], $attachments));
    }

    #[Route('/{id}', methods: ['GET'])]

    /**
     * function download
     *
     * @param int $id
     *
     * @return BinaryFileResponse|JsonResponse
     */
    public function download(int $id): BinaryFileResponse|JsonResponse
    {
        $user = $this->getUserEntity();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $attachment = $this->entityManager->getRepository(Attachment::class)->find($id);
        if (!$attachment || $attachment->getUser() !== $user) {
            return $this->json(['error' => 'Attachment not found'], 404);
        }

        $filePath = $this->getAttachmentFilePath($attachment);
        if (!file_exists($filePath)) {
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

        $attachment = $this->entityManager->getRepository(Attachment::class)->find($id);
        if (!$attachment || $attachment->getUser() !== $user) {
            return $this->json(['error' => 'Attachment not found'], 404);
        }

        $filePath = $this->getAttachmentFilePath($attachment);
        if (file_exists($filePath)) {
            unlink($filePath);
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

        $attachment = $this->entityManager->getRepository(Attachment::class)->find($id);
        if (!$attachment || $attachment->getUser() !== $user) {
            return $this->json(['error' => 'Attachment not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['description'])) {
            $attachment->setDescription($data['description']);
        }

        if (isset($data['entityType'])) {
            $attachment->setEntityType($data['entityType']);
        }

        if (isset($data['category'])) {
            $attachment->setCategory($data['category']);
        }

        if (isset($data['entityId'])) {
            $attachment->setEntityId($data['entityId']);
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
