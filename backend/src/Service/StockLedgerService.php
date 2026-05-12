<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\StockItem;
use App\Entity\User;
use App\Entity\VehicleType;
use Doctrine\ORM\EntityManagerInterface;

class StockLedgerService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function adjust(
        User $user,
        ?VehicleType $vehicleType,
        string $itemType,
        string $category,
        ?string $supplier,
        float $delta,
        ?string $description = null,
        ?string $price = null,
        ?string $notes = null,
        ?string $purchaseDate = null,
        ?string $partNumber = null,
        ?string $manufacturer = null,
        ?string $warranty = null,
        bool $forceCreate = false
    ): void {
        $normalizedCategory = trim($category);
        if ($normalizedCategory === '' || abs($delta) < 0.00001) {
            return;
        }

        $normalizedSupplier = $supplier !== null ? trim($supplier) : null;
        if ($normalizedSupplier === '') {
            $normalizedSupplier = null;
        }

        $item = null;
        if (!$forceCreate) {
            $repo = $this->entityManager->getRepository(StockItem::class);
            $item = $repo->findOneBy([
                'user' => $user,
                'vehicleType' => $vehicleType,
                'itemType' => $itemType,
                'category' => $normalizedCategory,
                'supplier' => $normalizedSupplier,
            ]);
        }

        if (!$item) {
            if ($delta <= 0) {
                return;
            }
            $item = new StockItem();
            $item->setUser($user)
                ->setVehicleType($vehicleType)
                ->setItemType($itemType)
                ->setCategory($normalizedCategory)
                ->setSupplier($normalizedSupplier)
                ->setDescription($description)
                ->setPrice($price)
                ->setNotes($notes)
                ->setPartNumber($partNumber)
                ->setManufacturer($manufacturer)
                ->setWarranty($warranty)
                ->setQuantity(number_format($delta, 2, '.', ''));
            
            if ($purchaseDate !== null) {
                try {
                    $item->setPurchaseDate(new \DateTime($purchaseDate));
                } catch (\Exception $e) {
                    // Invalid date format, skip
                }
            }
            
            $this->entityManager->persist($item);
            return;
        }

        $current = (float) $item->getQuantity();
        $next = $current + $delta;
        if ($next < 0) {
            $next = 0;
        }

        $item->setQuantity(number_format($next, 2, '.', ''));
        if ($description !== null) {
            $item->setDescription($description);
        }
        if ($price !== null) {
            $item->setPrice($price);
        }
        if ($notes !== null) {
            $item->setNotes($notes);
        }
        if ($partNumber !== null) {
            $item->setPartNumber($partNumber);
        }
        if ($manufacturer !== null) {
            $item->setManufacturer($manufacturer);
        }
        if ($warranty !== null) {
            $item->setWarranty($warranty);
        }
        if ($purchaseDate !== null) {
            try {
                $item->setPurchaseDate(new \DateTime($purchaseDate));
            } catch (\Exception $e) {
                // Invalid date format, skip
            }
        }
        $item->touch();
    }

    public function categoryForPart(string $description, ?string $partCategoryName): string
    {
        $category = trim((string) $partCategoryName);
        if ($category !== '') {
            return $category;
        }
        $fallback = trim($description);
        return $fallback !== '' ? $fallback : 'Part';
    }

    public function categoryForConsumable(string $description, ?string $consumableTypeName): string
    {
        $category = trim((string) $consumableTypeName);
        if ($category !== '') {
            return $category;
        }
        $fallback = trim($description);
        return $fallback !== '' ? $fallback : 'Consumable';
    }
}
