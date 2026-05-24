<?php

declare(strict_types=1);

use App\Tests\TestCase\BaseWebTestCase;

class AuthControllerTest extends BaseWebTestCase
{
    public function testRegisterWithInvalidEmail(): void
    {
        $this->client->request('POST', '/api/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => '10.0.0.11',
        ], json_encode([
            'email' => 'not-an-email',
            'password' => 'Password123!',
            'firstName' => 'Test',
            'lastName' => 'User',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testRegisterWithExistingEmail(): void
    {
        $this->client->request('POST', '/api/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => '10.0.0.12',
        ], json_encode([
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'firstName' => 'Test',
            'lastName' => 'User',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testMeEndpointRequiresAuthAndWorksWithToken(): void
    {
        $this->client->request('GET', '/api/me');
        $this->assertResponseStatusCodeSame(401);

        $this->client->request('GET', '/api/me', [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('email', $data);
    }
}
