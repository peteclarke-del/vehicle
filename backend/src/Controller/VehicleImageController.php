<?php

declare(strict_types=1);

namespace App\Controller;

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

#[Route('/api')]
class VehicleImageController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
    ) {
    }

    #[Route('/vehicles/{id}/images', name: 'vehicle_images_list', methods: ['GET'])]
    public function list(int $id): JsonResponse
    {
        $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($id);
        if (!$vehicle) {
            return $this->json(['error' => 'Vehicle not found'], 404);
        }

        $this->denyAccessUnlessGranted('view', $vehicle);

        $images = [];
        foreach ($vehicle->getImages() as $image) {
            $images[] = $this->serializeImage($image);
        }

        return $this->json(['images' => $images]);
    }

    #[Route('/vehicles/{id}/images', name: 'vehicle_images_upload', methods: ['POST'])]
    public function upload(int $id, Request $request): JsonResponse
    {
        $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($id);
        if (!$vehicle) {
            return $this->json(['error' => 'Vehicle not found'], 404);
        }

        $this->denyAccessUnlessGranted('edit', $vehicle);

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

        // Validate file size (5MB max)
        if ($file->getSize() > 5 * 1024 * 1024) {
            return $this->json(['error' => 'File too large. Maximum size: 5MB'], 400);
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        // Create uploads directory if it doesn't exist
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/vehicles/' . $vehicle->getId();
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        try {
            $file->move($uploadDir, $newFilename);
        } catch (FileException $e) {
            return $this->json(['error' => 'Failed to upload file'], 500);
        }

        $image = new VehicleImage();
        $image->setVehicle($vehicle);
        $image->setPath('/uploads/vehicles/' . $vehicle->getId() . '/' . $newFilename);
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
    public function update(int $vehicleId, int $imageId, Request $request): JsonResponse
    {
        $image = $this->entityManager->getRepository(VehicleImage::class)->find($imageId);
        if (!$image || $image->getVehicle()->getId() !== $vehicleId) {
            return $this->json(['error' => 'Image not found'], 404);
        }

        $this->denyAccessUnlessGranted('edit', $image->getVehicle());

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
    public function setPrimary(int $vehicleId, int $imageId): JsonResponse
    {
        $image = $this->entityManager->getRepository(VehicleImage::class)->find($imageId);
        if (!$image || $image->getVehicle()->getId() !== $vehicleId) {
            return $this->json(['error' => 'Image not found'], 404);
        }

        $this->denyAccessUnlessGranted('edit', $image->getVehicle());

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
    public function delete(int $vehicleId, int $imageId): JsonResponse
    {
        $image = $this->entityManager->getRepository(VehicleImage::class)->find($imageId);
        if (!$image || $image->getVehicle()->getId() !== $vehicleId) {
            return $this->json(['error' => 'Image not found'], 404);
        }

        $this->denyAccessUnlessGranted('edit', $image->getVehicle());

        // Delete physical file
        $filePath = $this->getParameter('kernel.project_dir') . '/public' . $image->getPath();
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $this->entityManager->remove($image);
        $this->entityManager->flush();

        return $this->json(['message' => 'Image deleted successfully']);
    }

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
}
