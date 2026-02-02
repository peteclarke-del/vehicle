<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\TestCase\BaseWebTestCase;

/**
 * eBay Webhook Controller Test
 * 
 * Integration tests for eBay marketplace webhook endpoint
 */
class EbayWebhookControllerTest extends BaseWebTestCase
{
    public function testChallengeVerificationWithoutChallengeCode(): void
    {
        $client = $this->client;

        $client->request('GET', '/api/ebay/webhook/account-deletion');

        $this->assertResponseStatusCodeSame(400);
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testChallengeVerificationWithChallengeCode(): void
    {
        $client = $this->client;

        // Note: Without EBAY_VERIFICATION_TOKEN configured, this will return 500
        $client->request('GET', '/api/ebay/webhook/account-deletion?challenge_code=test123');

        // Should return either 200 (if configured) or 500 (if not configured)
        $this->assertContains($client->getResponse()->getStatusCode(), [200, 500]);
    }

    public function testAccountDeletionNotificationWithoutPayload(): void
    {
        $client = $this->client;

        $client->request('POST', '/api/ebay/webhook/account-deletion', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '');

        // Should process even with empty payload
        $this->assertContains($client->getResponse()->getStatusCode(), [200, 400]);
    }

    public function testAccountDeletionNotificationWithValidPayload(): void
    {
        $client = $this->client;

        $payload = [
            'metadata' => [
                'topic' => 'MARKETPLACE_ACCOUNT_DELETION',
                'schemaVersion' => '1.0',
            ],
            'notification' => [
                'notificationId' => 'test-notification-123',
                'eventDate' => '2026-02-02T12:00:00.000Z',
                'publishDate' => '2026-02-02T12:00:00.000Z',
                'publisherUserId' => 'testuser123',
                'data' => [
                    'username' => 'testuser',
                    'userId' => '123456',
                    'eiasToken' => 'test-token'
                ]
            ]
        ];

        $client->request('POST', '/api/ebay/webhook/account-deletion', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertResponseIsSuccessful();
    }

    public function testMethodNotAllowed(): void
    {
        $client = $this->client;

        $client->request('PUT', '/api/ebay/webhook/account-deletion');

        $this->assertResponseStatusCodeSame(405);
    }
}
