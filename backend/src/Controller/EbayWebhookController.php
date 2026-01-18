<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\EbayAccountDeletionService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/ebay/webhook', name: 'ebay_webhook_')]
class EbayWebhookController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private EbayAccountDeletionService $deletionService
    ) {
    }

    /**
     * eBay Marketplace Account Deletion Webhook Endpoint
     * 
     * This endpoint handles:
     * 1. GET requests with challenge_code for endpoint verification
     * 2. POST requests with account deletion notifications
     * 
     * @see https://developer.ebay.com/develop/guides-v2/marketplace-user-account-deletion/marketplace-user-account-deletion
     */
    #[Route('/account-deletion', name: 'account_deletion', methods: ['GET', 'POST'])]
    public function accountDeletion(Request $request): JsonResponse
    {
        // Handle GET request - Challenge code verification
        if ($request->isMethod('GET')) {
            return $this->handleChallengeVerification($request);
        }

        // Handle POST request - Account deletion notification
        if ($request->isMethod('POST')) {
            return $this->handleAccountDeletionNotification($request);
        }

        return new JsonResponse(['error' => 'Method not allowed'], Response::HTTP_METHOD_NOT_ALLOWED);
    }

    /**
     * Handle eBay challenge code verification (GET request)
     * 
     * eBay sends: GET https://<callback_URL>?challenge_code=123
     * We must respond with: {"challengeResponse": "<hash>"}
     * 
     * Hash = SHA256(challengeCode + verificationToken + endpoint)
     */
    private function handleChallengeVerification(Request $request): JsonResponse
    {
        $challengeCode = $request->query->get('challenge_code');
        
        if (!$challengeCode) {
            $this->logger->error('eBay webhook: Missing challenge_code parameter');
            return new JsonResponse(['error' => 'Missing challenge_code'], Response::HTTP_BAD_REQUEST);
        }

        // Get verification token from environment
        $verificationToken = $_ENV['EBAY_VERIFICATION_TOKEN'] ?? null;
        
        if (!$verificationToken) {
            $this->logger->error('eBay webhook: EBAY_VERIFICATION_TOKEN not configured');
            return new JsonResponse(['error' => 'Verification token not configured'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Construct the endpoint URL (must match what's registered with eBay)
        $endpoint = $request->getSchemeAndHttpHost() . $request->getPathInfo();

        // Calculate challenge response: SHA256(challengeCode + verificationToken + endpoint)
        $hash = hash_init('sha256');
        hash_update($hash, $challengeCode);
        hash_update($hash, $verificationToken);
        hash_update($hash, $endpoint);
        $challengeResponse = hash_final($hash);

        $this->logger->info('eBay webhook: Challenge verification', [
            'challenge_code' => $challengeCode,
            'endpoint' => $endpoint,
            'response_hash' => $challengeResponse,
        ]);

        // IMPORTANT: Use JSON library to avoid BOM issues
        return new JsonResponse([
            'challengeResponse' => $challengeResponse,
        ]);
    }

    /**
     * Handle eBay marketplace account deletion notification (POST request)
     * 
     * Payload format:
     * {
     *   "metadata": {
     *     "topic": "MARKETPLACE_ACCOUNT_DELETION",
     *     "schemaVersion": "1.0",
     *     "deprecated": false
     *   },
     *   "notification": {
     *     "notificationId": "...",
     *     "eventDate": "2025-09-19T20:43:59.462Z",
     *     "publishDate": "2025-09-19T20:43:59.679Z",
     *     "publishAttemptCount": 1,
     *     "data": {
     *       "username": "...",
     *       "userId": "...",
     *       "eiasToken": "..."
     *     }
     *   }
     * }
     */
    private function handleAccountDeletionNotification(Request $request): JsonResponse
    {
        $content = $request->getContent();
        $signature = $request->headers->get('x-ebay-signature');

        $this->logger->info('eBay webhook: Received account deletion notification', [
            'signature_present' => !empty($signature),
            'content_length' => strlen($content),
        ]);

        // TODO: Verify signature using eBay's public key
        // For now, we'll process the notification
        // See: https://developer.ebay.com/api-docs/commerce/notification/resources/public_key/methods/getPublicKey

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            // Validate notification structure
            if (!isset($data['metadata']['topic']) || $data['metadata']['topic'] !== 'MARKETPLACE_ACCOUNT_DELETION') {
                $this->logger->warning('eBay webhook: Invalid notification topic', ['data' => $data]);
                return new JsonResponse(['error' => 'Invalid notification topic'], Response::HTTP_BAD_REQUEST);
            }

            $notificationData = $data['notification']['data'] ?? [];
            $username = $notificationData['username'] ?? null;
            $userId = $notificationData['userId'] ?? null;
            $eiasToken = $notificationData['eiasToken'] ?? null;

            $this->logger->info('eBay webhook: Processing account deletion', [
                'notification_id' => $data['notification']['notificationId'] ?? null,
                'username' => $username,
                'user_id' => $userId,
                'event_date' => $data['notification']['eventDate'] ?? null,
                'attempt_count' => $data['notification']['publishAttemptCount'] ?? null,
            ]);

            // Process the deletion using the service
            $deleted = $this->deletionService->deleteUserData($username, $userId, $eiasToken);

            if (!$deleted) {
                $this->logger->warning('eBay webhook: Deletion service returned false');
            }

            // Acknowledge receipt with 200 OK
            return new JsonResponse([
                'status' => 'acknowledged',
                'notificationId' => $data['notification']['notificationId'] ?? null,
            ], Response::HTTP_OK);
            
        } catch (\JsonException $e) {
            $this->logger->error('eBay webhook: Invalid JSON payload', [
                'error' => $e->getMessage(),
            ]);
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            $this->logger->error('eBay webhook: Error processing notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Still acknowledge to prevent retries
            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], Response::HTTP_OK);
        }
    }
}
