<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Todo;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\RoadTax;
use App\Tests\TestCase\BaseWebTestCase;

class NotificationControllerTest extends BaseWebTestCase
{
    public function testListNotificationsAsAuthenticatedUser(): void
    {
        $token = $this->getAuthToken();

        $this->client->request('GET', '/api/notifications', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('notifications', $responseData);
    }

    public function testListNotificationsWithoutAuthenticationReturns401(): void
    {
        $this->client->request('GET', '/api/notifications');

        $this->assertResponseStatusCodeSame(401);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Not authenticated', $responseData['error']);
    }

    public function testNotificationsIncludeTodosDueSoon(): void
    {
        $token = $this->getAuthToken();
        $em = $this->getEntityManager();

        // Create a test vehicle and todo
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $vehicle = new Vehicle();
        $vehicle->setOwner($user);
        $vehicle->setName('Test Notification Vehicle');
        $vehicle->setVehicleType($this->getVehicleType('Car'));
        $vehicle->setRegistration('TEST123');
        $vehicle->setMileage(10000);
        $vehicle->setPurchaseCost('5500.00');
        $vehicle->setPurchaseDate(new \DateTime('-1 year'));
        $em->persist($vehicle);

        $todo = new Todo();
        $todo->setVehicle($vehicle);
        $todo->setTitle('Urgent repair');
        $todo->setDueDate(new \DateTime('+5 days'));
        $todo->setDone(false);
        $em->persist($todo);
        $em->flush();

        $this->client->request('GET', '/api/notifications', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($responseData['notifications']);
        
        // Find the todo notification
        $foundTodo = false;
        foreach ($responseData['notifications'] as $notification) {
            if ($notification['type'] === 'todo' && str_contains($notification['title'] ?? '', 'Urgent repair')) {
                $foundTodo = true;
                break;
            }
        }
        $this->assertTrue($foundTodo, 'Should find todo notification in response');
    }

    public function testStreamNotificationsWithoutAuthenticationReturns401(): void
    {
        $this->client->request('GET', '/api/notifications/stream');

        $this->assertResponseStatusCodeSame(401);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Not authenticated', $responseData['error']);
    }

    public function testNotificationsAreGroupedByType(): void
    {
        $token = $this->getAuthToken();

        $this->client->request('GET', '/api/notifications', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        
        // Check structure includes standard notification types
        $this->assertArrayHasKey('notifications', $responseData);
        $this->assertIsArray($responseData['notifications']);
        
        // Each notification should have a type
        foreach ($responseData['notifications'] as $notification) {
            $this->assertArrayHasKey('type', $notification);
            $this->assertContains($notification['type'], ['todo', 'roadtax', 'mot', 'service', 'consumable', 'insurance']);
        }
    }
}
