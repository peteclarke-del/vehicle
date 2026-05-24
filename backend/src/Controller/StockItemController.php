<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Attachment;
use App\Entity\StockItem;
use App\Entity\User;
use App\Entity\VehicleType;
use App\Service\AttachmentLinkingService;
use App\Service\EntitySerializerService;
use App\Service\StockLedgerService;
use App\Service\UrlScraperService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\Trait\UserSecurityTrait;
use App\Controller\Trait\JsonValidationTrait;

#[Route('/api/stock-items')]

/**
 * Class StockItemController
 *
 * Provides read and adjustment endpoints for stock ledger items.
 */
class StockItemController extends AbstractController
{
    use UserSecurityTrait;
    use JsonValidationTrait;

    /**
     * StockItemController constructor.
     *
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     * @param StockLedgerService     $stockLedgerService Stock ledger domain service
     * @param UrlScraperService      $scraperService URL scraper service
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StockLedgerService $stockLedgerService,
        private UrlScraperService $scraperService,
        private AttachmentLinkingService $attachmentLinkingService,
        private EntitySerializerService $serializer
    ) {
    }

    #[Route('', name: 'api_stock_items_list', methods: ['GET'])]

    /**
     * List stock ledger items for the authenticated user.
     *
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $itemType = trim((string) $request->query->get('itemType', ''));
        $vehicleTypeId = $request->query->get('vehicleTypeId');
        $inStock = $request->query->getBoolean('inStock', false);

        $qb = $this->entityManager->createQueryBuilder()
            ->select('s', 'vt')
            ->from(StockItem::class, 's')
            ->leftJoin('s.vehicleType', 'vt')
            ->orderBy('s.updatedAt', 'DESC');

        if (!$this->isAdminForUser($user)) {
            $qb->andWhere('s.user = :user')->setParameter('user', $user);
        }

        if ($itemType !== '') {
            $qb->andWhere('s.itemType = :itemType')->setParameter('itemType', $itemType);
        }

        if ($vehicleTypeId !== null && $vehicleTypeId !== '') {
            $qb->andWhere('vt.id = :vehicleTypeId')->setParameter('vehicleTypeId', (int) $vehicleTypeId);
        }

        if ($inStock) {
            $qb->andWhere('s.quantity > 0');
        }

        $items = $qb->getQuery()->getResult();

        $data = array_map(fn(StockItem $i) => $this->serializer->serializeStockItem($i), $items);

        return $this->json($data);
    }

    #[Route('/adjust', name: 'api_stock_items_adjust', methods: ['POST'])]

    /**
     * Adjust stock quantity for an existing bucket or create/adjust by fields.
     *
     * @param Request $request JSON payload with delta and bucket identity
     *
     * @return JsonResponse
     */
    public function adjust(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON payload'], 400);
        }

        $delta = (float) ($data['delta'] ?? 0);
        if (abs($delta) < 0.00001) {
            return $this->json(['error' => 'Delta is required'], 400);
        }

        $targetUser = $user;
        $itemType = null;
        $category = null;
        $supplier = null;
        $description = null;
        $price = null;
        $notes = null;
        $purchaseDate = null;
        $partNumber = null;
        $manufacturer = null;
        $warranty = null;
        $vehicleType = null;
        $forceCreate = false;

        if (!empty($data['stockItemId'])) {
            $item = $this->entityManager
                ->getRepository(StockItem::class)
                ->find((int) $data['stockItemId']);
            if (!$item) {
                return $this->json(['error' => 'Stock item not found'], 404);
            }
            if (
                !$this->isAdminForUser($user)
                && $item->getUser()?->getId() !== $user->getId()
            ) {
                return $this->json(['error' => 'Not allowed'], 403);
            }
            $targetUser = $item->getUser() ?? $user;
            $vehicleType = $item->getVehicleType();
            $itemType = (string) $item->getItemType();
            $category = (string) $item->getCategory();
            $supplier = $item->getSupplier();
        } else {
            $itemType = trim((string) ($data['itemType'] ?? ''));
            $category = trim((string) ($data['category'] ?? ''));
            $supplier = isset($data['supplier'])
                ? trim((string) $data['supplier'])
                : null;
            $description = isset($data['description'])
                ? trim((string) $data['description'])
                : null;
            $price = isset($data['price'])
                ? trim((string) $data['price'])
                : null;
            $notes = isset($data['notes'])
                ? trim((string) $data['notes'])
                : null;
            $purchaseDate = isset($data['purchaseDate'])
                ? trim((string) $data['purchaseDate'])
                : null;
            $partNumber = isset($data['partNumber'])
                ? trim((string) $data['partNumber'])
                : null;
            $manufacturer = isset($data['manufacturer'])
                ? trim((string) $data['manufacturer'])
                : null;
            $warranty = isset($data['warranty'])
                ? trim((string) $data['warranty'])
                : null;
            if (!empty($data['vehicleTypeId'])) {
                $vehicleType = $this->entityManager->getRepository(VehicleType::class)->find((int) $data['vehicleTypeId']);
            }
            if ($itemType === '' || $category === '') {
                return $this->json(
                    ['error' => 'itemType and category are required'],
                    400
                );
            }
            $forceCreate = true;
        }

        $adjustedItem = $this->stockLedgerService->adjust(
            $targetUser,
            $vehicleType,
            $itemType,
            $category,
            $supplier,
            $delta,
            $description,
            $price,
            $notes,
            $purchaseDate,
            $partNumber,
            $manufacturer,
            $warranty,
            $forceCreate
        );
        $this->entityManager->flush();

        if (array_key_exists('receiptAttachmentId', $data) && $adjustedItem instanceof StockItem) {
            $attachmentId = $data['receiptAttachmentId'];
            if ($attachmentId === null || $attachmentId === '' || $attachmentId === 0) {
                if ($adjustedItem->getReceiptAttachment()) {
                    $this->attachmentLinkingService->unlinkAttachment($adjustedItem->getReceiptAttachment(), $adjustedItem);
                    $adjustedItem->setReceiptAttachment(null);
                }
            } else {
                $this->attachmentLinkingService->processReceiptAttachmentId(
                    (int) $attachmentId,
                    $adjustedItem,
                    'stockItem'
                );
            }
        }

        if ($adjustedItem instanceof StockItem) {
            $this->entityManager->flush();
        }

        return $this->json(['success' => true]);
    }

    #[Route('/{id}', name: 'api_stock_items_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $item = $this->entityManager->getRepository(StockItem::class)->find($id);
        if (!$item) {
            return $this->json(['error' => 'Stock item not found'], 404);
        }

        if (
            !$this->isAdminForUser($user)
            && $item->getUser()?->getId() !== $user->getId()
        ) {
            return $this->json(['error' => 'Not allowed'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON payload'], 400);
        }

        $itemType = trim((string) ($data['itemType'] ?? $item->getItemType() ?? ''));
        $category = trim((string) ($data['category'] ?? $item->getCategory() ?? ''));
        if ($itemType === '' || $category === '') {
            return $this->json(['error' => 'itemType and category are required'], 400);
        }

        $supplier = isset($data['supplier']) ? trim((string) $data['supplier']) : null;
        if ($supplier === '') {
            $supplier = null;
        }
        $description = isset($data['description']) ? trim((string) $data['description']) : null;
        $price = isset($data['price']) ? trim((string) $data['price']) : null;
        $notes = isset($data['notes']) ? trim((string) $data['notes']) : null;
        $partNumber = isset($data['partNumber']) ? trim((string) $data['partNumber']) : null;
        $manufacturer = isset($data['manufacturer']) ? trim((string) $data['manufacturer']) : null;
        $warranty = isset($data['warranty']) ? trim((string) $data['warranty']) : null;
        $quantity = isset($data['quantity']) ? (float) $data['quantity'] : (float) $item->getQuantity();
        if ($quantity < 0) {
            return $this->json(['error' => 'Quantity cannot be negative'], 400);
        }

        $vehicleType = null;
        if (array_key_exists('vehicleTypeId', $data) && !empty($data['vehicleTypeId'])) {
            $vehicleType = $this->entityManager->getRepository(VehicleType::class)->find((int) $data['vehicleTypeId']);
        } elseif (!array_key_exists('vehicleTypeId', $data)) {
            $vehicleType = $item->getVehicleType();
        }

        $item
            ->setVehicleType($vehicleType)
            ->setItemType($itemType)
            ->setCategory($category)
            ->setSupplier($supplier)
            ->setDescription($description)
            ->setPrice($price)
            ->setNotes($notes)
            ->setPartNumber($partNumber)
            ->setManufacturer($manufacturer)
            ->setWarranty($warranty)
            ->setQuantity(number_format($quantity, 2, '.', ''));

        if (array_key_exists('purchaseDate', $data)) {
            $purchaseDate = trim((string) ($data['purchaseDate'] ?? ''));
            if ($purchaseDate === '') {
                $item->setPurchaseDate(null);
            } else {
                try {
                    $item->setPurchaseDate(new \DateTime($purchaseDate));
                } catch (\Exception) {
                    return $this->json(['error' => 'Invalid purchaseDate'], 400);
                }
            }
        }

        if (array_key_exists('receiptAttachmentId', $data)) {
            $attachmentId = $data['receiptAttachmentId'];
            if ($attachmentId === null || $attachmentId === '' || $attachmentId === 0) {
                if ($item->getReceiptAttachment()) {
                    $this->attachmentLinkingService->unlinkAttachment($item->getReceiptAttachment(), $item);
                }
                $item->setReceiptAttachment(null);
            } else {
                $this->attachmentLinkingService->processReceiptAttachmentId(
                    (int) $attachmentId,
                    $item,
                    'stockItem'
                );
            }
        }

        $item->touch();
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/scrape-url', name: 'api_stock_items_scrape_url', methods: ['POST'])]

    /**
     * Scrape product details from a URL.
     *
     * @param Request $request JSON payload with URL
     *
     * @return JsonResponse
     */
    public function scrapeUrl(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $validation = $this->validateJsonRequest($request);
        if ($validation['error']) {
            return $validation['error'];
        }
        $data = $validation['data'];
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
