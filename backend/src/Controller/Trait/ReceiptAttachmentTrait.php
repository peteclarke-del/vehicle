<?php

namespace App\Controller\Trait;

use App\Entity\Attachment;
use App\Service\AttachmentLinkingService;

/**
 * trait ReceiptAttachmentTrait
 *
 * Provides common receipt attachment handling methods
 */
trait ReceiptAttachmentTrait
{
    /**
     * function handleReceiptAttachmentUpdate
     *
     * Handle receipt attachment update from request data
     * Handles linking, unlinking, and clearing attachments
     *
     * @param object $entity
     * @param array $data
     * @param string $entityType
     * @param AttachmentLinkingService $attachmentLinkingService
     *
     * @return void
     */
    protected function handleReceiptAttachmentUpdate(
        object $entity,
        array $data,
        string $entityType,
        AttachmentLinkingService $attachmentLinkingService
    ): void {
        if (!array_key_exists('receiptAttachmentId', $data)) {
            return;
        }

        $attachmentId = $data['receiptAttachmentId'];

        // Clear attachment
        if ($attachmentId === null || $attachmentId === '' || $attachmentId === 0) {
            $currentAttachment = $entity->getReceiptAttachment();
            if ($currentAttachment) {
                $attachmentLinkingService->unlinkAttachment($currentAttachment, $entity);
            }
            $entity->setReceiptAttachment(null);
            return;
        }

        // Link new attachment
        $attachmentLinkingService->processReceiptAttachmentId(
            (int) $attachmentId,
            $entity,
            $entityType
        );
    }
}
