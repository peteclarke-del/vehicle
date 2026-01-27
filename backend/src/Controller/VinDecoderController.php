<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Vehicle;
use App\Service\VinDecoderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/vehicles')]
class VinDecoderController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private VinDecoderService $vinDecoderService
    ) {
    }

    private function isAdminForUser(?\App\Entity\User $user): bool
    {
        if (!$user) return false;
        $roles = $user->getRoles() ?: [];
        return in_array('ROLE_ADMIN', $roles, true);
    }

    #[Route('/{id}/vin-decode', name: 'api_vehicle_vin_decode', methods: ['GET'])]
    public function decodeVin(int $id, Request $request): JsonResponse
    {
        $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($id);

        if (!$vehicle) {
            return $this->json(['error' => 'Vehicle not found'], 404);
        }

        // Check if user owns this vehicle (admins bypass)
        if (!$this->isAdminForUser($this->getUser()) && $vehicle->getOwner() !== $this->getUser()) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $vin = $vehicle->getVin();
        
        if (!$vin) {
            return $this->json([
                'error' => 'No VIN number',
                'message' => 'This vehicle does not have a VIN number stored.'
            ], 404);
        }

        // Check for force refresh parameter
        $forceRefresh = $request->query->getBoolean('refresh', false);

        // Check if we have cached decoded data (unless force refresh)
        if (!$forceRefresh) {
            $cachedData = $vehicle->getVinDecodedData();
            if ($cachedData && $vehicle->getVinDecodedAt()) {
                return $this->json([
                    'success' => true,
                    'vin' => $vin,
                    'data' => $cachedData,
                    'cached' => true,
                    'decoded_at' => $vehicle->getVinDecodedAt()->format('Y-m-d H:i:s')
                ]);
            }
        }

        // Check VIN format
        if (!$this->vinDecoderService->isValidVinFormat($vin)) {
            return $this->json([
                'error' => 'Invalid VIN format',
                'message' => 'VIN must be exactly 17 characters and cannot contain I, O, or Q.'
            ], 400);
        }

        $decodedData = $this->vinDecoderService->decodeVin($vin);

        if (!$decodedData) {
            return $this->json([
                'error' => 'VIN decode failed',
                'message' => 'Unable to decode VIN. The VIN may be invalid or not found in the database.'
            ], 404);
        }

        // Cache the decoded data
        $vehicle->setVinDecodedData($decodedData);
        $vehicle->setVinDecodedAt(new \DateTime());
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'vin' => $vin,
            'data' => $decodedData,
            'cached' => false
        ]);
    }
}
