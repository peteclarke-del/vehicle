<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\InsurancePolicy;
use App\Entity\User;
use App\Entity\Vehicle;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Insurance Controller Test
 *
 * Comprehensive test suite for InsuranceController covering all endpoints
 */
class InsuranceControllerTest extends WebTestCase
{

    /**
     * Get authentication token for testing
     *
     * @return string JWT token
     */
    private function getAuthToken(): string
    {
        $client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'test@example.com',
                'password' => 'testpassword'
            ])
        );

        $data = json_decode($client->getResponse()->getContent(), true);
        return $data['token'] ?? '';
    }

    /**
     * Test listing insurance records requires authentication
     */
    public function testListInsuranceRequiresAuthentication(): void
    {
        $client = static::createClient();
$client->request('GET', '/api/insurance?vehicleId=1');
        
        $this->assertEquals(
            Response::HTTP_UNAUTHORIZED,
            $client->getResponse()->getStatusCode()
        );
    }

    /**
     * Test listing insurance records requires vehicleId parameter
     */
    public function testListInsuranceRequiresVehicleId(): void
    {
        $client = static::createClient();
$client->request(
            'GET',
            '/api/insurance',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('vehicleId is required', $data['error']);
    }

    /**
     * Test listing insurance records for invalid vehicle returns 404
     */
    public function testListInsuranceForInvalidVehicle(): void
    {
        $client = static::createClient();
$client->request(
            'GET',
            '/api/insurance?vehicleId=99999',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    /**
     * Test creating insurance record
     */
    public function testCreateInsurance(): void
    {
        $client = static::createClient();
$insuranceData = [
            'vehicleId' => 1,
            'provider' => 'Test Insurance Co',
            'policyNumber' => 'POL123456',
            'coverageType' => 'Comprehensive',
            'annualCost' => 650.00,
            'startDate' => '2026-01-01',
            'expiryDate' => '2027-01-01',
            'notes' => 'Test policy notes'
        ];

        $client->request(
            'POST',
            '/api/insurance',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode($insuranceData)
        );

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('Test Insurance Co', $data['provider']);
        $this->assertEquals('POL123456', $data['policyNumber']);
        $this->assertEquals('Comprehensive', $data['coverageType']);
        $this->assertEquals(650.00, $data['annualCost']);
    }

    /**
     * Test updating insurance record
     */
    public function testUpdateInsurance(): void
    {
        $client = static::createClient();
$updatedData = [
            'provider' => 'Updated Insurance Co',
            'annualCost' => 700.00
        ];

        $client->request(
            'PUT',
            '/api/insurance/1',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode($updatedData)
        );

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Updated Insurance Co', $data['provider']);
        $this->assertEquals(700.00, $data['annualCost']);
    }

    /**
     * Test updating non-existent insurance returns 404
     */
    public function testUpdateNonExistentInsurance(): void
    {
        $client = static::createClient();
$client->request(
            'PUT',
            '/api/insurance/99999',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode(['provider' => 'Test'])
        );

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    /**
     * Test deleting insurance record
     */
    public function testDeleteInsurance(): void
    {
        $client = static::createClient();
$client->request(
            'DELETE',
            '/api/insurance/1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Insurance record deleted', $data['message']);
    }

    /**
     * Test deleting non-existent insurance returns 404
     */
    public function testDeleteNonExistentInsurance(): void
    {
        $client = static::createClient();
$client->request(
            'DELETE',
            '/api/insurance/99999',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    /**
     * Test user cannot access another user's insurance
     */
    public function testUserCannotAccessOtherUsersInsurance(): void
    {
        $client = static::createClient();
// Create second user and get their token
        $client->request(
            'POST',
            '/api/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'other@example.com',
                'password' => 'password',
                'firstName' => 'Other',
                'lastName' => 'User'
            ])
        );

        $otherToken = $this->getAuthToken();

        // Try to access first user's insurance
        $client->request(
            'GET',
            '/api/insurance?vehicleId=1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $otherToken]
        );

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $client = null;
        $token = null;
    }
}
