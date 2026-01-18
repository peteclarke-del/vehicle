<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\DvsaApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/dvsa', name: 'api_dvsa_')]
class DvsaController extends AbstractController
{
    private DvsaApiService $dvsaService;

    public function __construct(DvsaApiService $dvsaService)
    {
        $this->dvsaService = $dvsaService;
    }

    #[Route('/vehicle/{registration}', name: 'vehicle_details', methods: ['GET'])]
    public function getVehicleDetails(string $registration): JsonResponse
    {
        $details = $this->dvsaService->getVehicleDetails($registration);

        if (!$details) {
            return $this->json([
                'error' => 'Vehicle not found or DVSA API unavailable',
                'registration' => $registration,
            ], 404);
        }

        return $this->json($details);
    }

    #[Route('/mot-history/{registration}', name: 'mot_history', methods: ['GET'])]
    public function getMotHistory(string $registration): JsonResponse
    {
        $history = $this->dvsaService->parseMotHistory($registration);

        if (empty($history)) {
            return $this->json([
                'error' => 'MOT history not found or DVSA API unavailable',
                'registration' => $registration,
            ], 404);
        }

        return $this->json($history);
    }

    #[Route('/latest-mot/{registration}', name: 'latest_mot', methods: ['GET'])]
    public function getLatestMot(string $registration): JsonResponse
    {
        $latestMot = $this->dvsaService->getLatestMotTest($registration);

        if (!$latestMot) {
            return $this->json([
                'error' => 'No MOT tests found or DVSA API unavailable',
                'registration' => $registration,
            ], 404);
        }

        return $this->json($latestMot);
    }

    #[Route('/check', name: 'check_api', methods: ['GET'])]
    public function checkApi(): JsonResponse
    {
        // Simple endpoint to check if API is configured
        return $this->json([
            'configured' => $this->dvsaService->getVehicleDetails('TEST123') !== null,
            'message' => 'DVSA API service status',
        ]);
    }
}
