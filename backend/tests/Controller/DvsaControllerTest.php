<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * DVSA Controller Test
 * 
 * Integration tests for DVSA API endpoints
 */
class DvsaControllerTest extends WebTestCase
{
    private function getAuthToken($client): string
    {
        $client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'test@example.com',
            'password' => 'password123'
        ]));

        $response = json_decode($client->getResponse()->getContent(), true);
        return $response['token'] ?? '';
    }

    public function testGetMotHistoryRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/dvsa/mot-history/AB12CDE');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetMotHistoryForRegistration(): void
    {
        $client = static::createClient();
        $token = $this->getAuthToken($client);
        $client->request('GET', '/api/dvsa/mot-history/AB12CDE', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('registration', $data);
        $this->assertArrayHasKey('tests', $data);
    }

    public function testSanitizesRegistration(): void
    {
        $client = static::createClient();
        $token = $this->getAuthToken($client);
        $client->request('GET', '/api/dvsa/mot-history/ab12%20cde', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('AB12CDE', $data['registration']);
    }

    public function testHandlesVehicleNotFound(): void
    {
        $client = static::createClient();
        $token = $this->getAuthToken($client);
        $client->request('GET', '/api/dvsa/mot-history/NOTFOUND', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testGetCurrentMotStatus(): void
    {
        $client = static::createClient();
        $token = $this->getAuthToken($client);
        $client->request('GET', '/api/dvsa/current-status/AB12CDE', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('expiryDate', $data);
        $this->assertArrayHasKey('testStatus', $data);
    }

    public function testLookupVehicleDetails(): void
    {
        $client = static::createClient();
        $token = $this->getAuthToken($client);
        $client->request('GET', '/api/dvsa/vehicle/AB12CDE', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('make', $data);
        $this->assertArrayHasKey('model', $data);
        $this->assertArrayHasKey('colour', $data);
    }

    public function testGetTestCertificate(): void
    {
        $client = static::createClient();
        $token = $this->getAuthToken($client);
        $client->request('GET', '/api/dvsa/certificate/12345678', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        
        $response = $client->getResponse();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function testValidatesRegistrationFormat(): void
    {
        $client = static::createClient();
        $token = $this->getAuthToken($client);
        $client->request('GET', '/api/dvsa/mot-history/INVALID!@#', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testHandlesDvsaApiRateLimiting(): void
    {
        $client = static::createClient();
        $token = $this->getAuthToken($client);
        
        // Make multiple requests to trigger rate limiting
        for ($i = 0; $i < 15; $i++) {
            $client->request('GET', '/api/dvsa/mot-history/AB12CDE', [], [], [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]);
        }

        $this->assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
    }

    public function testCachesMotHistory(): void
    {
        $client = static::createClient();
        $token = $this->getAuthToken($client);
        
        // First request
        $client->request('GET', '/api/dvsa/mot-history/AB12CDE', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
        $this->assertResponseIsSuccessful();
        
        // Second request should be cached
        $client->request('GET', '/api/dvsa/mot-history/AB12CDE', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('X-Cache', 'HIT');
    }

    public function testReturnsAdvisoryItems(): void
    {
        $client = static::createClient();
        $token = $this->getAuthToken($client);
        $client->request('GET', '/api/dvsa/mot-history/AB12CDE', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $firstTest = $data['tests'][0] ?? null;
        
        $this->assertNotNull($firstTest);
        $this->assertArrayHasKey('advisoryItems', $firstTest);
    }

    public function testReturnsFailureItems(): void
    {
        $client = static::createClient();
        $token = $this->getAuthToken($client);
        $client->request('GET', '/api/dvsa/mot-history/AB12CDE', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $firstTest = $data['tests'][0] ?? null;
        
        $this->assertNotNull($firstTest);
        $this->assertArrayHasKey('failureItems', $firstTest);
    }

    public function testHandlesDvsaApiTimeout(): void
    {
        $client = static::createClient();
        $token = $this->getAuthToken($client);
        $client->request('GET', '/api/dvsa/mot-history/TIMEOUT', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_GATEWAY_TIMEOUT);
    }
}
