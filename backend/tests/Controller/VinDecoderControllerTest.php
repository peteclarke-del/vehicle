<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\TestCase\BaseWebTestCase;

class VinDecoderControllerTest extends BaseWebTestCase
{
    public function testDecodeVinForNonExistentVehicle(): void
    {
        $this->client->request('GET', '/api/vehicles/999999/vin-decode', [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDecodeVinForVehicleWithoutVin(): void
    {
        $em = $this->getEntityManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $vehicle = $this->createTestVehicle($user, 'NO-VIN-' . uniqid());

        $this->client->request('GET', '/api/vehicles/' . $vehicle->getId() . '/vin-decode', [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
        ]);

        $this->assertResponseStatusCodeSame(404);
    }
}
