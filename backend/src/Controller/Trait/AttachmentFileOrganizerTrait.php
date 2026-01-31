<?php

declare(strict_types=1);

namespace App\Controller\Trait;

use App\Entity\Attachment;
use App\Entity\Vehicle;

trait AttachmentFileOrganizerTrait
{
    /**
     * Reorganize receipt file into proper directory structure: <regno>/<entity_type>/
     * Moves files from misc/ or flat structure into organized folders
     */
    private function reorganizeReceiptFile(Attachment $attachment, ?Vehicle $vehicle): void
    {
        if (!$vehicle) {
            return;
        }

        $uploadsRoot = $this->getParameter('kernel.project_dir') . '/uploads';
        $currentStoragePath = $attachment->getStoragePath();
        if (!$currentStoragePath) {
            return;
        }

        $currentPath = $uploadsRoot . '/' . ltrim($currentStoragePath, '/');
        if (!file_exists($currentPath)) {
            $this->logger->warning('Cannot reorganize - file not found', ['path' => $currentPath]);
            return;
        }

        $entityType = $attachment->getEntityType();
        if (!$entityType) {
            return;
        }

        $reg = $vehicle->getRegistrationNumber() ?: $vehicle->getName() ?: ('vehicle-' . $vehicle->getId());
        $regSlug = strtolower((string) $this->slugger->slug($reg));
        
        // New structure: <regno>/<entity_type>/
        $newSubDir = $regSlug . '/' . $entityType;
        
        // Check if already in correct location
        if (str_contains($currentStoragePath, $newSubDir . '/')) {
            return;
        }

        $uploadDir = $uploadsRoot . '/attachments/' . $newSubDir;
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            $this->logger->error('Failed to create directory', ['path' => $uploadDir]);
            return;
        }

        $filename = $attachment->getFilename();
        $newPath = $uploadDir . '/' . $filename;

        try {
            if (rename($currentPath, $newPath)) {
                $newStoragePath = 'attachments/' . $newSubDir . '/' . $filename;
                $attachment->setStoragePath($newStoragePath);
                $this->logger->info('Reorganized receipt file', [
                    'from' => $currentStoragePath,
                    'to' => $newStoragePath,
                    'vehicle' => $reg
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error reorganizing file', ['error' => $e->getMessage()]);
        }
    }
}
