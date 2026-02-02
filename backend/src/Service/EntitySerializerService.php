<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Part;
use App\Entity\Consumable;
use App\Entity\MotRecord;

/**
 * class EntitySerializerService
 *
 * Centralized service for serializing entities to arrays
 * Eliminates duplicate serialization methods across controllers
 */
class EntitySerializerService
{
    /**
     * function serializePart
     *
     * Serialize a Part entity to an array
     *
     * @param Part $part
     * @param bool $detailed
     *
     * @return array
     */
    public function serializePart(Part $part, bool $detailed = true): array
    {
        $data = [
            'id' => $part->getId(),
            'description' => $part->getDescription(),
            'price' => $part->getPrice(),
            'quantity' => $part->getQuantity(),
            'cost' => $part->getCost(),
        ];

        if ($detailed) {
            $data = array_merge($data, [
                'vehicleId' => $part->getVehicle()?->getId(),
                'purchaseDate' => $part->getPurchaseDate()?->format('Y-m-d'),
                'partNumber' => $part->getPartNumber(),
                'manufacturer' => $part->getManufacturer(),
                'supplier' => $part->getSupplier(),
                'partCategory' => $part->getPartCategory() ? [
                    'id' => $part->getPartCategory()->getId(),
                    'name' => $part->getPartCategory()->getName(),
                ] : null,
                'installationDate' => $part->getInstallationDate()?->format('Y-m-d'),
                'mileageAtInstallation' => $part->getMileageAtInstallation(),
                'notes' => $part->getNotes(),
                'motRecordId' => $part->getMotRecord()?->getId(),
                'motTestNumber' => $part->getMotRecord()?->getMotTestNumber(),
                'motTestDate' => $part->getMotRecord()?->getTestDate()?->format('Y-m-d'),
                'serviceRecordId' => $part->getServiceRecord()?->getId(),
                'serviceRecordDate' => $part->getServiceRecord()?->getServiceDate()?->format('Y-m-d'),
                'serviceRecordSummary' => $part->getServiceRecord() ? (
                    ($part->getServiceRecord()->getWorkPerformed() ?? $part->getServiceRecord()->getServiceProvider() ?? null)
                ) : null,
                'warranty' => $part->getWarranty(),
                'receiptAttachmentId' => $part->getReceiptAttachment()?->getId(),
                'productUrl' => $part->getProductUrl(),
                'createdAt' => $part->getCreatedAt()?->format('c'),
                'includedInServiceCost' => $part->isIncludedInServiceCost(),
            ]);
        } else {
            // Include partCategory even in non-detailed mode for service records
            $data['partCategory'] = $part->getPartCategory() ? [
                'id' => $part->getPartCategory()->getId(),
                'name' => $part->getPartCategory()->getName(),
            ] : null;
        }

        return $data;
    }

    /**
     * function serializeConsumable
     *
     * Serialize a Consumable entity to an array
     *
     * @param Consumable $consumable
     * @param bool $detailed
     *
     * @return array
     */
    public function serializeConsumable(Consumable $consumable, bool $detailed = true): array
    {
        $data = [
            'id' => $consumable->getId(),
            'description' => $consumable->getDescription(),
            'cost' => $consumable->getCost(),
            'quantity' => $consumable->getQuantity() ?? 1,
        ];

        if ($detailed) {
            $data = array_merge($data, [
                'vehicleId' => $consumable->getVehicle()?->getId(),
                'serviceRecordId' => $consumable->getServiceRecord()?->getId(),
                'consumableType' => $consumable->getConsumableType() ? [
                    'id' => $consumable->getConsumableType()->getId(),
                    'name' => $consumable->getConsumableType()->getName(),
                    'unit' => $consumable->getConsumableType()->getUnit()
                ] : null,
                'brand' => $consumable->getBrand(),
                'partNumber' => $consumable->getPartNumber(),
                'supplier' => $consumable->getSupplier(),
                'lastChanged' => $consumable->getLastChanged()?->format('Y-m-d'),
                'mileageAtChange' => $consumable->getMileageAtChange(),
                // Note: These values are stored in KM in the database
                'replacementIntervalMiles' => $consumable->getReplacementIntervalMiles(),
                'nextReplacementMileage' => $consumable->getNextReplacementMileage(),
                'notes' => $consumable->getNotes(),
                'receiptAttachmentId' => $consumable->getReceiptAttachment()?->getId(),
                'productUrl' => $consumable->getProductUrl(),
                'motRecordId' => $consumable->getMotRecord()?->getId(),
                'motTestNumber' => $consumable->getMotRecord()?->getMotTestNumber(),
                'motTestDate' => $consumable->getMotRecord()?->getTestDate()?->format('Y-m-d'),
                'createdAt' => $consumable->getCreatedAt()?->format('c'),
                'includedInServiceCost' => $consumable->isIncludedInServiceCost(),
            ]);
        }

        return $data;
    }

    /**
     * function serializeMotRecord
     *
     * Serialize a MotRecord entity to an array
     *
     * @param MotRecord $mot
     * @param bool $detailed
     *
     * @return array
     */
    public function serializeMotRecord(MotRecord $mot, bool $detailed = false): array
    {
        // Normalize result for frontend form consumption
        $result = $mot->getResult();
        $upper = strtoupper((string)$result);
        if (str_contains($upper, 'PASS')) {
            $result = 'Pass';
        } elseif (str_contains($upper, 'FAIL')) {
            $result = 'Fail';
        } else {
            $result = 'Advisory';
        }

        // Handle advisory items (can be array or string)
        $advisoryItems = $mot->getAdvisoryItems();
        if (is_array($advisoryItems)) {
            $advisoriesText = implode(
                "\n",
                array_map(
                    fn($a) => is_array($a) ? ($a['text'] ?? json_encode($a)) : (string)$a,
                    $advisoryItems
                )
            );
        } else {
            $advisoriesText = $mot->getAdvisories();
        }

        // Handle failure items (can be array or string)
        $failureItems = $mot->getFailureItems();
        if (is_array($failureItems)) {
            $failuresText = implode(
                "\n",
                array_map(
                    fn($a) => is_array($a) ? ($a['text'] ?? json_encode($a)) : (string)$a,
                    $failureItems
                )
            );
        } else {
            $failuresText = $mot->getFailures();
        }

        $data = [
            'id' => $mot->getId(),
            'vehicleId' => $mot->getVehicle()->getId(),
            'testDate' => $mot->getTestDate()?->format('Y-m-d'),
            'result' => $result,
            'expiryDate' => $mot->getExpiryDate()?->format('Y-m-d'),
            'motTestNumber' => $mot->getMotTestNumber(),
            'testerName' => $mot->getTesterName(),
            'isRetest' => $mot->getIsRetest(),
            'testCost' => $mot->getTestCost(),
            'repairCost' => $mot->getRepairCost(),
            'totalCost' => $mot->getTotalCost(),
            'mileage' => $mot->getMileage(),
            'testCenter' => $mot->getTestCenter() ?? 'Unknown',
            'advisories' => $advisoriesText,
            'failures' => $failuresText,
            'advisoryItems' => $advisoryItems,
            'failureItems' => $failureItems,
            'receiptAttachmentId' => $mot->getReceiptAttachment()?->getId(),
            'repairDetails' => $mot->getRepairDetails(),
            'notes' => $mot->getNotes(),
            'createdAt' => $mot->getCreatedAt()?->format('c'),
        ];

        return $data;
    }
}
