<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleImage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Controller\Trait\UserSecurityTrait;

#[Route('/api')]

/**
 * class VehicleImageController
 */
class VehicleImageController extends AbstractController
{
    use UserSecurityTrait;
    /**
     * function __construct
     *
     * @param EntityManagerInterface $entityManager
     * @param SluggerInterface $slugger
     * @param int $uploadMaxBytes
     *
     * @return void
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        private int $uploadMaxBytes
    ) {
    }

    #[Route('/vehicles/{id}/images', name: 'vehicle_images_list', methods: ['GET'])]

    /**
     * function list
     *
     * @param int $id
     *
     * @return JsonResponse
     */
    public function list(int $id): JsonResponse
    {
        $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($id);
        if (!$vehicle) {
            return $this->json(['error' => 'Vehicle not found'], 404);
        }

        $user = $this->getUserEntity();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }
        if (!$this->canAccessVehicle($user, $vehicle)) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $images = [];
        foreach ($vehicle->getImages() as $image) {
            $images[] = $this->serializeImage($image);
        }

        return $this->json(['images' => $images]);
    }

    #[Route('/vehicles/{id}/images', name: 'vehicle_images_upload', methods: ['POST'])]

    /**
     * function upload
     *
     * @param int $id
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function upload(int $id, Request $request): JsonResponse
    {
        $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($id);
        if (!$vehicle) {
            return $this->json(['error' => 'Vehicle not found'], 404);
        }

        $user = $this->getUserEntity();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }
        if (!$this->canAccessVehicle($user, $vehicle)) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        /** @var UploadedFile $file */
        $file = $request->files->get('image');
        if (!$file) {
            return $this->json(['error' => 'No file uploaded'], 400);
        }

        // Validate file type
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            return $this->json(['error' => 'Invalid file type. Allowed: JPG, PNG, WEBP'], 400);
        }

        // Validate file size (configured max)
        if ($file->getSize() > $this->uploadMaxBytes) {
            $maxMb = (int) ceil($this->uploadMaxBytes / (1024 * 1024));
            return $this->json(['error' => 'File too large. Maximum size: ' . $maxMb . 'MB'], 400);
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        // Create uploads directory if it doesn't exist
        $reg = $vehicle->getRegistrationNumber()
            ?: $vehicle->getName()
            ?: ('vehicle-' . $vehicle->getId());
        $storageSubDir = strtolower((string) $this->slugger->slug($reg));
        $uploadDir = $this->getParameter('kernel.project_dir')
            . '/uploads/vehicles/'
            . $storageSubDir;
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                return $this->json([
                    'error' => 'Failed to create upload directory: ' . $uploadDir,
                ], 500);
            }
        }

        try {
            $file->move($uploadDir, $newFilename);
        } catch (FileException $e) {
            return $this->json(['error' => 'Failed to upload file'], 500);
        }

        $image = new VehicleImage();
        $image->setVehicle($vehicle);
        $image->setPath('/uploads/vehicles/' . $storageSubDir . '/' . $newFilename);
        $image->setCaption($request->request->get('caption'));

        // Set as primary if it's the first image
        if ($vehicle->getImages()->count() === 0) {
            $image->setIsPrimary(true);
        }

        // Set display order
        $maxOrder = 0;
        foreach ($vehicle->getImages() as $existingImage) {
            if ($existingImage->getDisplayOrder() > $maxOrder) {
                $maxOrder = $existingImage->getDisplayOrder();
            }
        }
        $image->setDisplayOrder($maxOrder + 1);

        $this->entityManager->persist($image);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Image uploaded successfully',
            'image' => $this->serializeImage($image)
        ], 201);
    }

    #[Route('/vehicles/{vehicleId}/images/{imageId}', name: 'vehicle_images_update', methods: ['PUT'])]

    /**
     * function update
     *
     * @param int $vehicleId
     * @param int $imageId
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function update(int $vehicleId, int $imageId, Request $request): JsonResponse
    {
        $image = $this->entityManager->getRepository(VehicleImage::class)->find($imageId);
        if (!$image || $image->getVehicle()->getId() !== $vehicleId) {
            return $this->json(['error' => 'Image not found'], 404);
        }

        $user = $this->getUserEntity();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }
        if (!$this->canAccessVehicle($user, $image->getVehicle())) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['caption'])) {
            $image->setCaption($data['caption']);
        }

        if (isset($data['displayOrder'])) {
            $image->setDisplayOrder((int) $data['displayOrder']);
        }

        $this->entityManager->flush();

        return $this->json([
            'message' => 'Image updated successfully',
            'image' => $this->serializeImage($image)
        ]);
    }

    #[Route('/vehicles/{vehicleId}/images/{imageId}/primary', name: 'vehicle_images_set_primary', methods: ['PUT'])]

    /**
     * function setPrimary
     *
     * @param int $vehicleId
     * @param int $imageId
     *
     * @return JsonResponse
     */
    public function setPrimary(int $vehicleId, int $imageId): JsonResponse
    {
        $image = $this->entityManager->getRepository(VehicleImage::class)->find($imageId);
        if (!$image || $image->getVehicle()->getId() !== $vehicleId) {
            return $this->json(['error' => 'Image not found'], 404);
        }

        $user = $this->getUserEntity();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }
        if (!$this->canAccessVehicle($user, $image->getVehicle())) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        // Remove primary flag from other images
        foreach ($image->getVehicle()->getImages() as $vehicleImage) {
            $vehicleImage->setIsPrimary(false);
        }

        // Set this image as primary
        $image->setIsPrimary(true);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Primary image updated successfully',
            'image' => $this->serializeImage($image)
        ]);
    }

    #[Route('/vehicles/{vehicleId}/images/{imageId}', name: 'vehicle_images_delete', methods: ['DELETE'])]

    /**
     * function delete
     *
     * @param int $vehicleId
     * @param int $imageId
     *
     * @return JsonResponse
     */
    public function delete(int $vehicleId, int $imageId): JsonResponse
    {
        $image = $this->entityManager->getRepository(VehicleImage::class)->find($imageId);
        if (!$image || $image->getVehicle()->getId() !== $vehicleId) {
            return $this->json(['error' => 'Image not found'], 404);
        }

        $user = $this->getUserEntity();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }
        if (!$this->canAccessVehicle($user, $image->getVehicle())) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        // Delete physical file
        $filePath = $this->getParameter('kernel.project_dir') . $image->getPath();
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $this->entityManager->remove($image);
        $this->entityManager->flush();

        return $this->json(['message' => 'Image deleted successfully']);
    }

    /**
     * function serializeImage
     *
     * @param VehicleImage $image
     *
     * @return array
     */
    private function serializeImage(VehicleImage $image): array
    {
        return [
            'id' => $image->getId(),
            'path' => $image->getPath(),
            'caption' => $image->getCaption(),
            'isPrimary' => $image->getIsPrimary(),
            'displayOrder' => $image->getDisplayOrder(),
            'isScraped' => $image->getIsScraped(),
            'sourceUrl' => $image->getSourceUrl(),
            'uploadedAt' => $image->getUploadedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * function canAccessVehicle
     *
     * @param User $user
     * @param Vehicle $vehicle
     *
     * @return bool
     */
    private function canAccessVehicle(User $user, Vehicle $vehicle): bool
    {
        if ($this->isAdminForUser($user)) {
            return true;
        }

        $owner = $vehicle->getOwner();
        return $owner instanceof User && $owner->getId() === $user->getId();
    }
}
