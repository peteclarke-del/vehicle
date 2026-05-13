<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\TestCase\BaseWebTestCase;

class NotificationControllerTest extends BaseWebTestCase
{
    public function testListNotificationsAsAuthenticatedUser(): void
    {
        $this->client->request('GET', '/api/notifications', [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($responseData);
    }

    public function testListNotificationsWithoutAuthenticationReturns401(): void
    {
        $this->client->request('GET', '/api/notifications');

        $this->assertResponseStatusCodeSame(401);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertSame('Not authenticated', $responseData['error']);
    }

    public function testStreamNotificationsWithoutAuthenticationReturns401(): void
    {
        $this->client->request('GET', '/api/notifications/stream');

        $this->assertResponseStatusCodeSame(401);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
    }
}
