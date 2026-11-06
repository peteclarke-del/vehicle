<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Attachment;
use App\Entity\InsurancePolicy;
use App\Entity\StockItem;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleAssignment;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Prepares the target account for a "Full import".
 *
 * A Full import makes the account match the export file:
 *  - Vehicles in the file that already exist (same registration, same owner)
 *    are removed and re-created from the file, so edits made since the
 *    previous import are applied. Sharing (VehicleAssignment) is not part of
 *    an export, so it is captured here and re-applied afterwards.
 *  - With wipeData, ALL of the user's vehicles, stock items and attachments
 *    are removed first, i.e. the import starts from a blank account.
 *  - With deleteFiles (only meaningful alongside wipeData or replaced
 *    vehicles), the physical upload files of removed records are deleted too.
 *    When false, files stay on disk so that a re-import can re-link them.
 *
 * Only the importing user's data is touched; other users are never affected.
 */
class FullImportPreparer
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private string $uploadDirectory,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $vehiclesData
     * @return array{
     *   removedVehicles:int, removedStockItems:int, removedAttachments:int,
     *   deletedFiles:int, pendingFiles:list<string>, assignments:array<string, list<array<string, mixed>>>
     * }
     */
    public function prepare(User $user, array $vehiclesData, bool $wipeData, bool $deleteFiles): array
    {
        $vehicleRepo = $this->entityManager->getRepository(Vehicle::class);
        $owned = $vehicleRepo->findBy(['owner' => $user]);

        if ($wipeData) {
            $toRemove = $owned;
        } else {
            $incoming = [];
            foreach ($vehiclesData as $row) {
                $reg = $this->normaliseReg($row['registrationNumber'] ?? $row['registration'] ?? null);
                if ($reg !== '') {
                    $incoming[$reg] = true;
                }
            }
            $toRemove = array_values(array_filter(
                $owned,
                fn (Vehicle $v) => isset($incoming[$this->normaliseReg($v->getRegistrationNumber())])
            ));
        }

        $files = [];
        $assignments = [];
        foreach ($toRemove as $vehicle) {
            $assignments[$this->normaliseReg($vehicle->getRegistrationNumber())] = $this->captureAssignments($vehicle);
            foreach ($vehicle->getImages() as $image) {
                $files[] = $image->getPath();
            }
            // Shared policies: detach, and remove only if nothing else uses them.
            foreach ($vehicle->getInsurancePolicies()->toArray() as $policy) {
                $policy->removeVehicle($vehicle);
                if ($policy->getVehicles()->isEmpty()) {
                    $this->entityManager->remove($policy);
                }
            }
        }

        $attachments = [];
        if ($wipeData) {
            $attachments = $this->entityManager->getRepository(Attachment::class)->findBy(['user' => $user]);
        } elseif ($toRemove !== []) {
            $attachments = $this->entityManager->getRepository(Attachment::class)->findBy(['vehicle' => $toRemove]);
        }
        foreach ($attachments as $attachment) {
            $files[] = $attachment->getStoragePath();
        }

        $stockItems = $wipeData
            ? $this->entityManager->getRepository(StockItem::class)->findBy(['user' => $user])
            : [];

        // Remove children that point at attachments first, then attachments,
        // then vehicles (DB-level ON DELETE CASCADE handles the rest).
        foreach ($stockItems as $item) {
            $this->entityManager->remove($item);
        }
        foreach ($toRemove as $vehicle) {
            $this->entityManager->remove($vehicle);
        }
        $this->entityManager->flush();
        foreach ($attachments as $attachment) {
            if ($this->entityManager->contains($attachment)) {
                $this->entityManager->remove($attachment);
            }
        }
        $this->entityManager->flush();

        $this->logger->info('[full-import] prepared account', [
            'userId' => $user->getId(),
            'wipeData' => $wipeData,
            'deleteFiles' => $deleteFiles,
            'removedVehicles' => count($toRemove),
            'removedStockItems' => count($stockItems),
            'removedAttachments' => count($attachments),
        ]);

        return [
            'removedVehicles' => count($toRemove),
            'removedStockItems' => count($stockItems),
            'removedAttachments' => count($attachments),
            'deletedFiles' => 0,
            'pendingFiles' => $deleteFiles ? array_values(array_unique(array_filter($files))) : [],
            'assignments' => $wipeData ? [] : $assignments,
        ];
    }

    /**
     * Re-apply sharing captured before replacement onto the re-imported vehicles.
     *
     * @param array<string, list<array<string, mixed>>> $assignments
     */
    public function restoreAssignments(User $user, array $assignments): int
    {
        if ($assignments === []) {
            return 0;
        }
        $restored = 0;
        $vehicles = $this->entityManager->getRepository(Vehicle::class)->findBy(['owner' => $user]);
        foreach ($vehicles as $vehicle) {
            foreach ($assignments[$this->normaliseReg($vehicle->getRegistrationNumber())] ?? [] as $a) {
                $assignedTo = $this->entityManager->find(User::class, $a['assignedTo']);
                if (!$assignedTo) {
                    continue;
                }
                $assignment = (new VehicleAssignment())
                    ->setVehicle($vehicle)
                    ->setAssignedTo($assignedTo)
                    ->setAssignedBy($a['assignedBy'] ? $this->entityManager->find(User::class, $a['assignedBy']) : null)
                    ->setCanView($a['canView'])
                    ->setCanEdit($a['canEdit'])
                    ->setCanAddRecords($a['canAddRecords'])
                    ->setCanDelete($a['canDelete']);
                $this->entityManager->persist($assignment);
                $restored++;
            }
        }
        $this->entityManager->flush();

        return $restored;
    }

    /** @return list<array<string, mixed>> */
    private function captureAssignments(Vehicle $vehicle): array
    {
        $out = [];
        foreach ($this->entityManager->getRepository(VehicleAssignment::class)->findBy(['vehicle' => $vehicle]) as $a) {
            $out[] = [
                'assignedTo' => $a->getAssignedTo()?->getId(),
                'assignedBy' => $a->getAssignedBy()?->getId(),
                'canView' => $a->canView(),
                'canEdit' => $a->canEdit(),
                'canAddRecords' => $a->canAddRecords(),
                'canDelete' => $a->canDelete(),
            ];
        }

        return $out;
    }

    /**
     * Delete upload files. Call only after the import transaction committed.
     *
     * @param list<?string> $paths
     */
    public function deleteFiles(array $paths): int
    {
        $root = realpath($this->uploadDirectory);
        if ($root === false) {
            return 0;
        }
        $deleted = 0;
        foreach (array_unique(array_filter($paths)) as $path) {
            $relative = preg_replace('#^/?uploads/#', '', ltrim((string) $path, '/'));
            $full = realpath($root . '/' . $relative);
            // Never follow a path outside the uploads directory.
            if ($full !== false && str_starts_with($full, $root . '/') && is_file($full) && @unlink($full)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function normaliseReg(?string $reg): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $reg));
    }
}
