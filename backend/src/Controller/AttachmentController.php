<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Attachment;
use Doctrine\ORM\EntityManagerInterface;
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

#[Route('/api/attachments')]
class AttachmentController extends AbstractController
{
    private const MAX_FILE_SIZE = 10485760; // 10MB
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

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        private ReceiptOcrService $ocrService
    ) {
    }

    private function getUploadDir(): string
    {
        return $this->getParameter('kernel.project_dir') . '/uploads/attachments';
    }

    private function getUserEntity(): ?\App\Entity\User
    {
        $user = $this->getUser();
        return $user instanceof \App\Entity\User ? $user : null;
    }

    #[Route('', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $user = $this->getUserEntity();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        /** @var UploadedFile $file */
        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'No file provided'], 400);
        }

        // Log upload attempt
        error_log('Upload attempt - File: ' . $file->getClientOriginalName() . ', Size: ' . $file->getSize());

        // Validate file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return $this->json(['error' => 'File too large (max 10MB)'], 400);
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
        error_log('Upload directory: ' . $uploadDir);
        error_log('Directory exists: ' . (is_dir($uploadDir) ? 'yes' : 'no'));
        error_log('Directory writable: ' . (is_writable(dirname($uploadDir)) ? 'yes' : 'no'));
        
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                error_log('Failed to create directory: ' . $uploadDir);
                return $this->json(['error' => 'Failed to create upload directory: ' . $uploadDir], 500);
            }
            error_log('Created directory: ' . $uploadDir);
        }

        try {
            $file->move($uploadDir, $newFilename);
            error_log('File uploaded successfully: ' . $uploadDir . '/' . $newFilename);
        } catch (FileException $e) {
            error_log('Upload failed: ' . $e->getMessage());
            return $this->json(['error' => 'Failed to upload file: ' . $e->getMessage()], 500);
        }

        $attachment = new Attachment();
        $attachment->setFilename($newFilename);
        $attachment->setOriginalName($file->getClientOriginalName());
        $attachment->setMimeType($mimeType);
        $attachment->setFileSize($file->getSize());
        $attachment->setUser($user);
        
        $entityType = $request->request->get('entityType');
        $entityId = $request->request->get('entityId');
        $description = $request->request->get('description');
        $category = $request->request->get('category');
        
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

        $filePath = $this->getUploadDir() . '/' . $attachment->getFilename();
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

        $filePath = $this->getUploadDir() . '/' . $attachment->getFilename();
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

        $filePath = $this->getUploadDir() . '/' . $attachment->getFilename();
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $this->entityManager->remove($attachment);
        $this->entityManager->flush();

        return $this->json(['message' => 'Attachment deleted']);
    }

    #[Route('/{id}', methods: ['PUT'])]
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
        if (isset($data['category'])) {
            $attachment->setCategory($data['category']);
        }

        }

        if (isset($data['entityId'])) {
            $attachment->setEntityId($data['entityId']);
        }

        $this->entityManager->flush();

        return $this->json($this->serializeAttachment($attachment));
    }

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
            'downloadUrl' => '/api/attachments/' . $attachment->getId(),
            'isImage' => $attachment->isImage(),
            'isPdf' => $attachment->isPdf(),
        ];
    }
}
