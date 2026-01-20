<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for the DVSA proxy endpoint.
 */
class DvsaProxyControllerTest extends WebTestCase
{
    /**
     * Verify a 503 response is returned when the DVSA API key is missing.
     */
    public function testReturns503WhenApiKeyMissing(): void
    {
        // Ensure env var is not set for this scenario
        unset($_ENV['DVSA_API_KEY']);
        unset($_SERVER['DVSA_API_KEY']);

        $client = static::createClient();
        $client->request('GET', '/api/dvsa/lookup?vrm=AB12CDE');

        $this->assertResponseStatusCodeSame(Response::HTTP_SERVICE_UNAVAILABLE);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('DVSA API key', $data['error']);
    }

    /**
     * Verify a mocked DVSA response is normalized and returned by the proxy.
     */
    public function testLookupByVrmReturnsNormalizedData(): void
    {
        // Set a fake DVSA API key for the test
        $_ENV['DVSA_API_KEY'] = 'test-key-123';
        $_SERVER['DVSA_API_KEY'] = 'test-key-123';

        // Prepare a fake DVSA response body
        $dvsaPayload = [
            'vin' => '1HGBH41JXMN109186',
            'make' => 'TestMake',
            'model' => 'TestModel',
            'colour' => 'Red',
            'fuelType' => 'Petrol',
            'firstRegistrationDate' => '2018-05-10',
            'taxStatus' => 'Taxed',
            'taxDueDate' => '2024-05-10',
            'motTestDueDate' => '2024-11-01',
            'cubicCapacity' => 1998,
            'co2Emissions' => 120,
        ];

        $mockResponse = new MockResponse(
            json_encode($dvsaPayload),
            ['http_code' => 200]
        );
        $mockClient = new MockHttpClient($mockResponse);

        // Create client and inject mock http_client into test container
        $client = static::createClient();
        $container = static::getContainer();
        $container->set('http_client', $mockClient);

        $client->request('GET', '/api/dvsa/lookup?vrm=AB12CDE');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame('AB12CDE', $data['vrm']);
        $this->assertSame('TestMake', $data['make']);
        $this->assertSame('TestModel', $data['model']);
        $this->assertSame('Red', $data['colour']);
        $this->assertSame('1HGBH41JXMN109186', $data['vin']);
        $this->assertArrayHasKey('motExpiryDate', $data);
    }
}
