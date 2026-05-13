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

        $this->assertResponseStatusCodeSame(200);
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

        $this->assertResponseStatusCodeSame(200);
    }

    public function testListSecurityFeaturesWithInvalidVehicleType(): void
    {
        $this->client->request('GET', '/api/security-features?vehicleTypeId=999999');

        $this->assertResponseStatusCodeSame(200);
    }
}
