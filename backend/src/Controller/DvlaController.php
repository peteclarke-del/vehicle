<?php

namespace App\Controller;

use App\Service\DvlaApiService;
use App\Service\DvlaBusyException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('/api/dvla', name: 'api_dvla_')]
class DvlaController extends AbstractController
{
    private DvlaApiService $dvlaService;
    private CacheInterface $cache;
    private LoggerInterface $logger;
    private int $cacheTtl;

    public function __construct(
        DvlaApiService $dvlaService,
        CacheInterface $cache,
        LoggerInterface $logger,
        ?int $cacheTtl = null
    ) {
        $this->dvlaService = $dvlaService;
        $this->cache = $cache;
        $this->logger = $logger;
        // TTL in seconds; allow env override via DVLA_CACHE_TTL
        $this->cacheTtl = $cacheTtl ?? (int) ($_ENV['DVLA_CACHE_TTL'] ?? getenv('DVLA_CACHE_TTL') ?: 3600);
    }

    #[Route('/vehicle/{registration}', name: 'vehicle', methods: ['GET'])]
    public function getVehicle(string $registration): JsonResponse
    {
        $this->logger->info('DVLA lookup requested', ['registration' => $registration]);
        $normalizedRegistration = strtoupper(str_replace(' ', '', $registration));
        $cacheKey = 'dvla.vehicle.' . $normalizedRegistration;

        try {
            $result = $this->cache->get(
                $cacheKey,
                function (ItemInterface $item) use (
                    $normalizedRegistration,
                    $registration
                ) {
                    $item->expiresAfter($this->cacheTtl);

                    try {
                        $data = $this->dvlaService
                            ->getVehicleByRegistration($registration);
                    } catch (\RuntimeException $e) {
                        throw $e;
                    }

                    if (!$data) {
                        return null;
                    }

                    // Normalize response for frontend - include more useful fields
                    // Helper to capitalise first letter only
                    $capitalize = function ($v) {
                        if (!$v) {
                            return $v;
                        }
                        $s = trim((string) $v);
                        return ucfirst(strtolower($s));
                    };

                    return [
                        'registration' => $normalizedRegistration,
                        'vin' => $data['vin'] ?? null,
                        'make' => isset($data['make']) ? $capitalize($data['make']) : null,
                        'model' => isset($data['model']) ? $capitalize($data['model']) : null,
                        'colour' => isset($data['primaryColour']) || isset($data['colour']) ? $capitalize($data['primaryColour'] ?? $data['colour']) : null,
                        'yearOfManufacture' => $data['yearOfManufacture'] ?? null,
                        'firstRegistrationDate' =>
                            $data['firstRegistrationDate'] ?? null,
                        'vehicleType' => $data['vehicleType'] ?? null,
                        'engineCapacity' => $data['engineCapacity'] ?? null,
                        'fuelType' => $data['fuelType'] ?? null,
                        'taxStatus' => $data['taxStatus'] ?? null,
                        'taxDetails' => $data['taxDetails'] ?? null,
                        'motTests' =>
                            ($data['motTestHistory'] ?? ($data['motTests'] ?? null)),
                        'dvlaRaw' => $data,
                    ];
                }
            );
        } catch (DvlaBusyException $e) {
            $this->logger->warning(
                'DVLA busy/429 after retries',
                ['registration' => $registration]
            );
            return $this->json(
                [
                    'error' => 'dvla.lookup_busy',
                    'message' => $e->getMessage(),
                ],
                503
            );
        } catch (\RuntimeException $e) {
            return $this->json(
                [
                    'error' => 'DVLA authentication not configured',
                    'message' => $e->getMessage(),
                ],
                503
            );
        }

        if (!$result) {
            $this->logger->info(
                'DVLA lookup returned no result',
                ['registration' => $registration]
            );

            return $this->json(
                [
                    'error' => 'Vehicle not found or DVLA unavailable',
                    'registration' => $registration,
                ],
                404
            );
        }

        return $this->json($result);
    }
}
