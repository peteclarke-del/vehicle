<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Part;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Service\ReceiptOcrService;
use App\Service\UrlScraperService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/parts')]
class PartController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ReceiptOcrService $ocrService,
        private UrlScraperService $scraperService
    ) {
    }

    #[Route('', name: 'api_parts_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $vehicleId = $request->query->get('vehicleId');

        if ($vehicleId) {
            $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($vehicleId);
            if (!$vehicle || $vehicle->getOwner()->getId() !== $user->getId()) {
                return $this->json(['error' => 'Vehicle not found'], 404);
            }
            $parts = $this->entityManager->getRepository(Part::class)
                ->findBy(['vehicle' => $vehicle], ['purchaseDate' => 'DESC']);
        } else {
            $parts = [];
        }

        $data = array_map(fn($p) => $this->serializePart($p), $parts);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'api_parts_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $part = $this->entityManager->getRepository(Part::class)->find($id);

        if (!$part || $part->getVehicle()->getOwner()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Part not found'], 404);
        }

        return $this->json($this->serializePart($part));
    }

    #[Route('', name: 'api_parts_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $data = json_decode($request->getContent(), true);

        $vehicle = $this->entityManager->getRepository(Vehicle::class)
            ->find($data['vehicleId']);

        if (!$vehicle || $vehicle->getOwner()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Vehicle not found'], 404);
        }

        $part = new Part();
        $part->setVehicle($vehicle);
        $this->updatePartFromData($part, $data);

        $this->entityManager->persist($part);
        $this->entityManager->flush();

        return $this->json($this->serializePart($part), 201);
    }

    #[Route('/{id}', name: 'api_parts_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $part = $this->entityManager->getRepository(Part::class)->find($id);

        if (!$part || $part->getVehicle()->getOwner()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Part not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $this->updatePartFromData($part, $data);

        $this->entityManager->flush();

        return $this->json($this->serializePart($part));
    }

    #[Route('/{id}', name: 'api_parts_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $part = $this->entityManager->getRepository(Part::class)->find($id);

        if (!$part || $part->getVehicle()->getOwner()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Part not found'], 404);
        }

        $this->entityManager->remove($part);
        $this->entityManager->flush();

        return $this->json(['message' => 'Part deleted successfully']);
    }

    private function serializePart(Part $part): array
    {
        return [
            'id' => $part->getId(),
            'vehicleId' => $part->getVehicle()->getId(),
            'purchaseDate' => $part->getPurchaseDate()?->format('Y-m-d'),
            'description' => $part->getDescription(),
            'partNumber' => $part->getPartNumber(),
            'manufacturer' => $part->getManufacturer(),
            'cost' => $part->getCost(),
            'category' => $part->getCategory(),
            'installationDate' => $part->getInstallationDate()?->format('Y-m-d'),
            'mileageAtInstallation' => $part->getMileageAtInstallation(),
            'notes' => $part->getNotes(),
            'receiptAttachmentId' => $part->getReceiptAttachmentId(),
            'productUrl' => $part->getProductUrl(),
            'createdAt' => $part->getCreatedAt()?->format('c')
        ];
    }

    private function updatePartFromData(Part $part, array $data): void
    {
        if (isset($data['purchaseDate'])) {
            $part->setPurchaseDate(new \DateTime($data['purchaseDate']));
        }
        if (isset($data['description'])) {
            $part->setDescription($data['description']);
        }
        if (isset($data['partNumber'])) {
            $part->setPartNumber($data['partNumber']);
        }
        if (isset($data['manufacturer'])) {
            $part->setManufacturer($data['manufacturer']);
        }
        if (isset($data['cost'])) {
            $part->setCost($data['cost']);
        }
        if (isset($data['category'])) {
            $part->setCategory($data['category']);
        }
        if (isset($data['installationDate'])) {
            $part->setInstallationDate(new \DateTime($data['installationDate']));
        }
        if (isset($data['mileageAtInstallation'])) {
            $part->setMileageAtInstallation($data['mileageAtInstallation']);
        }
        if (isset($data['notes'])) {
            $part->setNotes($data['notes']);
        }
        if (isset($data['receiptAttachmentId'])) {
            $part->setReceiptAttachmentId($data['receiptAttachmentId']);
        }
        if (isset($data['productUrl'])) {
            $part->setProductUrl($data['productUrl']);
        }
    }

    #[Route('/scrape-url', name: 'api_parts_scrape_url', methods: ['POST'])]
    public function scrapeUrl(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $url = $data['url'] ?? null;

        if (!$url) {
            return $this->json(['error' => 'URL is required'], 400);
        }

        try {
            $scrapedData = $this->scraperService->scrapeProductDetails($url);

            if (empty($scrapedData)) {
                return $this->json(['error' => 'Could not scrape data from URL'], 400);
            }

            return $this->json($scrapedData);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to scrape URL: ' . $e->getMessage()], 500);
        }
    }
}
