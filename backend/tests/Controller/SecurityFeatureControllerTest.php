<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\VehicleType;
use App\Tests\TestCase\BaseWebTestCase;

class SecurityFeatureControllerTest extends BaseWebTestCase
{
    public function testListAllSecurityFeatures(): void
    {
        $this->client->request('GET', '/api/security-features');

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($responseData);
    }

    public function testListSecurityFeaturesByVehicleType(): void
    {
        $em = $this->getEntityManager();
        
        // Get first vehicle type
        $vehicleType = $em->getRepository(VehicleType::class)->findOneBy([]);
        
        if (!$vehicleType) {
            $this->markTestSkipped('No vehicle types in database');
        }

        $this->client->request('GET', '/api/security-features?vehicleTypeId=' . $vehicleType->getId());

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($responseData);
    }

    public function testListSecurityFeaturesWithInvalidVehicleType(): void
    {
        $this->client->request('GET', '/api/security-features?vehicleTypeId=999999');

        // Should still return success with empty array
        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($responseData);
    }
}
