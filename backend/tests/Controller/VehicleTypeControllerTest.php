<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\VehicleType;
use App\Tests\TestCase\BaseWebTestCase;

/**
 * class VehicleTypeControllerTest
 */
class VehicleTypeControllerTest extends BaseWebTestCase
{
    /**
     * function testListVehicleTypes
     *
     * @return void
     */
    public function testListVehicleTypes(): void
    {
        $this->client->request('GET', '/api/vehicle-types');

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($responseData);
        
        if (count($responseData) > 0) {
            $this->assertArrayHasKey('id', $responseData[0]);
            $this->assertArrayHasKey('name', $responseData[0]);
        }
    }

    /**
     * function testGetConsumableTypesForVehicleType
     *
     * @return void
     */
    public function testGetConsumableTypesForVehicleType(): void
    {
        $em = $this->getEntityManager();
        
        // Get first vehicle type
        $vehicleType = $em->getRepository(VehicleType::class)->findOneBy([]);
        
        if (!$vehicleType) {
            $this->markTestSkipped('No vehicle types in database');
        }

        $this->client->request('GET', '/api/vehicle-types/' . $vehicleType->getId() . '/consumable-types');

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($responseData);
    }

    /**
     * function testGetConsumableTypesForNonExistentVehicleType
     *
     * @return void
     */
    public function testGetConsumableTypesForNonExistentVehicleType(): void
    {
        $this->client->request('GET', '/api/vehicle-types/999999/consumable-types');

        $this->assertResponseStatusCodeSame(404);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
    }
}
