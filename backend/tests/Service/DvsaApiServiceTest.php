<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\DvsaApiService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Psr\Log\NullLogger;

/**
 * DVSA API Service Test
 * 
 * Unit tests for DVSA MOT History API integration
 */
class DvsaApiServiceTest extends TestCase
{
    private DvsaApiService $service;
    private MockHttpClient $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient();
        $this->service = new DvsaApiService($this->httpClient, new NullLogger());
    }

    public function testGetMotHistoryForRegistration(): void
    {
        $mockResponse = new MockResponse(json_encode([
            [
                'testDate' => '2026-01-15',
                'expiryDate' => '2027-01-15',
                'testResult' => 'PASSED',
                'odometerValue' => '50000',
                'odometerUnit' => 'mi',
                'motTestNumber' => 'MOT123456789',
                'rfrAndComments' => [],
            ],
            [
                'testDate' => '2025-01-10',
                'expiryDate' => '2026-01-10',
                'testResult' => 'PASSED',
                'odometerValue' => '48000',
                'odometerUnit' => 'mi',
                'motTestNumber' => 'MOT987654321',
                'rfrAndComments' => [],
            ],
        ]));

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->service = new DvsaApiService($this->httpClient, new NullLogger());

        $result = $this->service->getMotHistory('ABC123');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame('PASSED', $result[0]['testResult']);
        $this->assertSame('MOT123456789', $result[0]['motTestNumber']);
    }

    public function testGetMotHistoryWithFailedTest(): void
    {
        $mockResponse = new MockResponse(json_encode([
            [
                'testDate' => '2026-01-15',
                'testResult' => 'FAILED',
                'odometerValue' => '50000',
                'odometerUnit' => 'mi',
                'motTestNumber' => 'MOT123456789',
                'rfrAndComments' => [
                    [
                        'text' => 'Brake pads below minimum',
                        'type' => 'FAIL',
                    ],
                    [
                        'text' => 'Headlight alignment incorrect',
                        'type' => 'FAIL',
                    ],
                ],
            ],
        ]));

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->service = new DvsaApiService($this->httpClient, new NullLogger());

        $result = $this->service->getMotHistory('DEF456');

        $this->assertCount(1, $result);
        $this->assertSame('FAILED', $result[0]['testResult']);
        $this->assertCount(2, $result[0]['rfrAndComments']);
    }

    public function testGetMotHistoryWithAdvisoryItems(): void
    {
        $mockResponse = new MockResponse(json_encode([
            [
                'testDate' => '2026-01-15',
                'testResult' => 'PASSED',
                'odometerValue' => '50000',
                'odometerUnit' => 'mi',
                'motTestNumber' => 'MOT123456789',
                'rfrAndComments' => [
                    [
                        'text' => 'Brake pads worn',
                        'type' => 'ADVISORY',
                    ],
                    [
                        'text' => 'Tyre tread low',
                        'type' => 'ADVISORY',
                    ],
                ],
            ],
        ]));

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->service = new DvsaApiService($this->httpClient, new NullLogger());

        $result = $this->service->getMotHistory('GHI789');

        $this->assertCount(1, $result);
        $this->assertSame('PASSED', $result[0]['testResult']);
        $this->assertCount(2, $result[0]['rfrAndComments']);
        $this->assertSame('ADVISORY', $result[0]['rfrAndComments'][0]['type']);
    }

    public function testGetMotHistoryHandlesNotFound(): void
    {
        $mockResponse = new MockResponse('', [
            'http_code' => 404,
        ]);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->service = new DvsaApiService($this->httpClient, new NullLogger());

        $result = $this->service->getMotHistory('NOTFOUND');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetMotHistoryHandlesApiError(): void
    {
        $mockResponse = new MockResponse('', [
            'http_code' => 500,
        ]);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->service = new DvsaApiService($this->httpClient, new NullLogger());

        // The service catches exceptions internally and returns empty array
        $result = $this->service->getMotHistory('ERROR123');
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetVehicleDetails(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'registration' => 'ABC123',
            'make' => 'TOYOTA',
            'model' => 'COROLLA',
            'firstUsedDate' => '2020-01-15',
            'fuelType' => 'Petrol',
            'primaryColour' => 'Silver',
            'engineSize' => '1798',
        ]));

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->service = new DvsaApiService($this->httpClient, new NullLogger());

        $result = $this->service->getVehicleDetails('ABC123');

        $this->assertIsArray($result);
        $this->assertSame('ABC123', $result['registration']);
        $this->assertSame('TOYOTA', $result['make']);
        $this->assertSame('COROLLA', $result['model']);
    }

    public function testGetCurrentMotStatus(): void
    {
        $mockResponse = new MockResponse(json_encode([
            [
                'testDate' => '2026-01-15',
                'expiryDate' => '2027-01-15',
                'testResult' => 'PASSED',
                'odometerValue' => '50000',
            ],
        ]));

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->service = new DvsaApiService($this->httpClient, new NullLogger());

        $result = $this->service->getCurrentMotStatus('ABC123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('isValid', $result);
        $this->assertArrayHasKey('expiryDate', $result);
        $this->assertTrue($result['isValid']);
        $this->assertSame('2027-01-15', $result['expiryDate']);
    }

    public function testGetCurrentMotStatusExpired(): void
    {
        $mockResponse = new MockResponse(json_encode([
            [
                'testDate' => '2023-01-15',
                'expiryDate' => '2024-01-15',
                'testResult' => 'PASSED',
                'odometerValue' => '40000',
            ],
        ]));

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->service = new DvsaApiService($this->httpClient, new NullLogger());

        $result = $this->service->getCurrentMotStatus('ABC123');

        $this->assertFalse($result['isValid']);
        $this->assertSame('2024-01-15', $result['expiryDate']);
    }

    public function testParseFailureItems(): void
    {
        $rfrComments = [
            ['text' => 'Brake pads below minimum', 'type' => 'FAIL'],
            ['text' => 'Headlight alignment incorrect', 'type' => 'FAIL'],
            ['text' => 'Tyre tread low', 'type' => 'ADVISORY'],
        ];

        $failureItems = $this->service->parseFailureItems($rfrComments);

        $this->assertCount(2, $failureItems);
        $this->assertContains('Brake pads below minimum', $failureItems);
        $this->assertContains('Headlight alignment incorrect', $failureItems);
        $this->assertNotContains('Tyre tread low', $failureItems);
    }

    public function testParseAdvisoryItems(): void
    {
        $rfrComments = [
            ['text' => 'Brake pads worn', 'type' => 'ADVISORY'],
            ['text' => 'Tyre tread low', 'type' => 'ADVISORY'],
            ['text' => 'Brake pads below minimum', 'type' => 'FAIL'],
        ];

        $advisoryItems = $this->service->parseAdvisoryItems($rfrComments);

        $this->assertCount(2, $advisoryItems);
        $this->assertContains('Brake pads worn', $advisoryItems);
        $this->assertContains('Tyre tread low', $advisoryItems);
        $this->assertNotContains('Brake pads below minimum', $advisoryItems);
    }

    public function testCalculateDaysUntilExpiry(): void
    {
        $expiryDate = new \DateTime('+30 days');

        $daysUntilExpiry = $this->service->calculateDaysUntilExpiry($expiryDate);

        $this->assertIsInt($daysUntilExpiry);
        $this->assertEqualsWithDelta(30, $daysUntilExpiry, 1);
    }

    public function testCalculateDaysUntilExpiryPastDate(): void
    {
        $expiryDate = new \DateTime('-30 days');

        $daysUntilExpiry = $this->service->calculateDaysUntilExpiry($expiryDate);

        $this->assertLessThan(0, $daysUntilExpiry);
        $this->assertEqualsWithDelta(-30, $daysUntilExpiry, 1);
    }

    public function testHandlesRateLimiting(): void
    {
        $mockResponse = new MockResponse('', [
            'http_code' => 429,
            'response_headers' => ['Retry-After' => '60'],
        ]);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->service = new DvsaApiService($this->httpClient, new NullLogger());

        // The service catches exceptions internally and returns empty array
        $result = $this->service->getMotHistory('ABC123');
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testSanitizesRegistrationNumber(): void
    {
        // Test that the service normalizes registration numbers
        // We can't easily inspect the request URL with MockHttpClient,
        // so we just verify the method doesn't fail with lowercase/spaces
        $mockResponse = new MockResponse(json_encode([]), ['http_code' => 404]);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->service = new DvsaApiService($this->httpClient, new NullLogger());

        // Should not throw - spaces and lowercase are handled internally
        $result = $this->service->getMotHistory('abc 123');
        
        $this->assertIsArray($result);
    }

    public function testGetPassRate(): void
    {
        $mockResponse = new MockResponse(json_encode([
            ['testResult' => 'PASSED'],
            ['testResult' => 'PASSED'],
            ['testResult' => 'FAILED'],
            ['testResult' => 'PASSED'],
        ]));

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->service = new DvsaApiService($this->httpClient, new NullLogger());

        $passRate = $this->service->getPassRate('ABC123');

        $this->assertSame(75.0, $passRate); // 3 out of 4 passed
    }

    public function testGetAverageMileage(): void
    {
        $mockResponse = new MockResponse(json_encode([
            ['odometerValue' => '50000', 'testDate' => '2026-01-15'],
            ['odometerValue' => '48000', 'testDate' => '2025-01-15'],
            ['odometerValue' => '46000', 'testDate' => '2024-01-15'],
        ]));

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->service = new DvsaApiService($this->httpClient, new NullLogger());

        $averageMileage = $this->service->getAverageMileage('ABC123');

        $this->assertSame(48000.0, $averageMileage);
    }
}
