<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Tests\TestCase\BaseWebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * DVSA Controller Test
 * 
 * Integration tests for DVSA API endpoints
 */
class DvsaControllerTest extends BaseWebTestCase
{

    public function testGetMotHistoryRequiresAuthentication(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/dvsa/mot-history/AB12CDE');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetMotHistoryForRegistration(): void
    {
        $client = $this->client;
        $token = $this->getAuthToken();
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
        $client = $this->client;
        $token = $this->getAuthToken();
        $client->request('GET', '/api/dvsa/mot-history/ab12%20cde', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('AB12CDE', $data['registration']);
    }

    public function testHandlesVehicleNotFound(): void
    {
        $client = $this->client;
        $token = $this->getAuthToken();
        $client->request('GET', '/api/dvsa/mot-history/NOTFOUND', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testGetCurrentMotStatus(): void
    {
        $client = $this->client;
        $token = $this->getAuthToken();
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
        $client = $this->client;
        $token = $this->getAuthToken();
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
        $client = $this->client;
        $token = $this->getAuthToken();
        $client->request('GET', '/api/dvsa/certificate/12345678', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        
        $response = $client->getResponse();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function testValidatesRegistrationFormat(): void
    {
        $client = $this->client;
        $token = $this->getAuthToken();
        $client->request('GET', '/api/dvsa/mot-history/INVALID!@#', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testHandlesDvsaApiRateLimiting(): void
    {
        $client = $this->client;
        $token = $this->getAuthToken();
        
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
        $client = $this->client;
        $token = $this->getAuthToken();
        
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
        $client = $this->client;
        $token = $this->getAuthToken();
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
        $client = $this->client;
        $token = $this->getAuthToken();
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
        $client = $this->client;
        $token = $this->getAuthToken();
        $client->request('GET', '/api/dvsa/mot-history/TIMEOUT', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_GATEWAY_TIMEOUT);
    }
}
