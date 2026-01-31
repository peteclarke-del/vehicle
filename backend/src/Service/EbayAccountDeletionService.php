<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service to handle eBay marketplace account deletion requests
 *
 * @see https://developer.ebay.com/develop/guides-v2/marketplace-user-account-deletion/marketplace-user-account-deletion
 */
class EbayAccountDeletionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Process eBay user account deletion
     *
     * This method should:
     * 1. Find all data associated with the eBay user
     * 2. Delete or anonymize personal data
     * 3. Log the deletion for audit purposes
     * 4. Ensure deletion is irreversible
     *
     * @param string|null $username eBay username (may be null for US users after Sept 2025)
     * @param string $userId eBay immutable user ID
     * @param string $eiasToken eBay EIAS token
     * @return bool True if deletion was successful
     */
    public function deleteUserData(?string $username, string $userId, string $eiasToken): bool
    {
        $this->logger->info('EbayAccountDeletion: Starting deletion process', [
            'username' => $username,
            'user_id' => $userId,
            'eias_token' => substr($eiasToken, 0, 10) . '...', // Log partial token only
        ]);

        try {
            // TODO: Implement actual deletion logic based on your data model

            // Example: If you store eBay user data in your database, delete it here
            // This is application-specific and depends on what eBay data you persist

            // Example queries (adjust to your schema):
            // $this->deleteByEbayUsername($username);
            // $this->deleteByEbayUserId($userId);
            // $this->deleteByEiasToken($eiasToken);

            // For now, we just log that we received the notification
            $this->logger->notice('EbayAccountDeletion: Deletion processed', [
                'username' => $username,
                'user_id' => $userId,
                'timestamp' => new \DateTime(),
            ]);

            // Create an audit log entry
            $this->createAuditLog($username, $userId, $eiasToken);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('EbayAccountDeletion: Failed to delete user data', [
                'username' => $username,
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Create an audit log entry for the deletion request
     *
     * This is important for compliance and audit purposes
     */
    private function createAuditLog(?string $username, string $userId, string $eiasToken): void
    {
        // TODO: Implement audit logging to a dedicated audit table
        // This should be in a separate table that is not automatically deleted
        // and serves as a record that the deletion was performed

        $this->logger->info('EbayAccountDeletion: Audit log created', [
            'username' => $username,
            'user_id' => $userId,
            'eias_token_partial' => substr($eiasToken, 0, 10),
            'deleted_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Check if this application stores any eBay user data
     *
     * @return bool True if the application stores eBay data
     */
    public function storesEbayData(): bool
    {
        // TODO: Update this based on your actual implementation
        // Return false if you want to opt out of notifications (if you don't store eBay data)
        // Return true if you store any eBay user data

        // Current implementation uses eBay Browse API to fetch product information
        // but doesn't appear to store eBay user personal data
        // You should review your data model and update this accordingly

        return false; // Change to true if you store eBay user data
    }

    /**
     * Example: Delete data associated with eBay username
     */
    private function deleteByEbayUsername(?string $username): void
    {
        if (!$username) {
            return;
        }

        // Example implementation - adjust to your schema:
        // $qb = $this->entityManager->createQueryBuilder();
        // $qb->delete(YourEntity::class, 'e')
        //    ->where('e.ebayUsername = :username')
        //    ->setParameter('username', $username)
        //    ->getQuery()
        //    ->execute();
    }

    /**
     * Example: Delete data associated with eBay user ID
     */
    private function deleteByEbayUserId(string $userId): void
    {
        // Example implementation - adjust to your schema:
        // $qb = $this->entityManager->createQueryBuilder();
        // $qb->delete(YourEntity::class, 'e')
        //    ->where('e.ebayUserId = :userId')
        //    ->setParameter('userId', $userId)
        //    ->getQuery()
        //    ->execute();
    }

    /**
     * Example: Delete data associated with EIAS token
     */
    private function deleteByEiasToken(string $eiasToken): void
    {
        // Example implementation - adjust to your schema:
        // $qb = $this->entityManager->createQueryBuilder();
        // $qb->delete(YourEntity::class, 'e')
        //    ->where('e.ebayEiasToken = :token')
        //    ->setParameter('token', $eiasToken)
        //    ->getQuery()
        //    ->execute();
    }
}
