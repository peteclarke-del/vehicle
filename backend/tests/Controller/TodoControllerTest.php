<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Todo;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\Part;
use App\Entity\Consumable;
use App\Tests\TestCase\BaseWebTestCase;

class TodoControllerTest extends BaseWebTestCase
{
    public function testListTodosAsAuthenticatedUser(): void
    {
        $token = $this->getAuthToken();

        $this->client->request('GET', '/api/todos', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($responseData);
    }

    public function testListTodosWithoutAuthenticationReturns401(): void
    {
        $this->client->request('GET', '/api/todos');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testFilterTodosByVehicleId(): void
    {
        $token = $this->getAuthToken();
        $em = $this->getEntityManager();

        // Create test vehicle and todo
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $vehicle = $this->createTestVehicle($user, 'TODO-VEH');

        $todo = new Todo();
        $todo->setVehicle($vehicle);
        $todo->setTitle('Oil change');
        $todo->setDone(false);
        $em->persist($todo);
        $em->flush();

        $this->client->request('GET', '/api/todos?vehicleId=' . $vehicle->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($responseData);
        
        // All returned todos should belong to the vehicle
        foreach ($responseData as $todoData) {
            $this->assertEquals($vehicle->getId(), $todoData['vehicleId']);
        }
    }

    public function testCreateTodoForVehicle(): void
    {
        $token = $this->getAuthToken();
        $em = $this->getEntityManager();

        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $vehicle = new Vehicle();
        $vehicle->setOwner($user);
        $vehicle->setName('Test Create Todo Vehicle');
        $vehicle->setVehicleType($this->getVehicleType('Car'));
        $vehicle->setRegistration('CREATE-TD');
        $vehicle->setMileage(15000);
        $vehicle->setPurchaseCost('5000.00');
        $vehicle->setPurchaseDate(new \DateTime('-1 year'));
        $em->persist($vehicle);
        $em->flush();

        $payload = [
            'vehicleId' => $vehicle->getId(),
            'title' => 'Replace brake pads',
            'description' => 'Front brake pads worn',
            'done' => false,
            'dueDate' => (new \DateTime('+14 days'))->format('Y-m-d'),
        ];

        $this->client->request('POST', '/api/todos', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Replace brake pads', $responseData['title']);
        $this->assertEquals($vehicle->getId(), $responseData['vehicleId']);
        $this->assertFalse($responseData['done']);
    }

    public function testCreateTodoWithInvalidVehicleReturns400(): void
    {
        $token = $this->getAuthToken();

        $payload = [
            'vehicleId' => 999999, // Non-existent vehicle
            'title' => 'This should fail',
            'done' => false,
        ];

        $this->client->request('POST', '/api/todos', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertResponseStatusCodeSame(400);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
    }

    public function testUpdateTodoMarkAsDone(): void
    {
        $token = $this->getAuthToken();
        $em = $this->getEntityManager();

        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $vehicle = new Vehicle();
        $vehicle->setOwner($user);
        $vehicle->setName('Test Update Todo Vehicle');
        $vehicle->setVehicleType($this->getVehicleType('Car'));
        $vehicle->setRegistration('UPDATE-TD');
        $vehicle->setMileage(20000);
        $vehicle->setPurchaseCost('6000.00');
        $vehicle->setPurchaseDate(new \DateTime('-2 years'));
        $em->persist($vehicle);

        $todo = new Todo();
        $todo->setVehicle($vehicle);
        $todo->setTitle('Fix headlight');
        $todo->setDone(false);
        $em->persist($todo);
        $em->flush();

        $payload = [
            'title' => 'Fix headlight',
            'done' => true,
            'completedBy' => (new \DateTime())->format('Y-m-d'),
        ];

        $this->client->request('PUT', '/api/todos/' . $todo->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($responseData['done']);
        $this->assertNotNull($responseData['completedBy']);
    }

    public function testDeleteTodo(): void
    {
        $token = $this->getAuthToken();
        $em = $this->getEntityManager();

        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $vehicle = new Vehicle();
        $vehicle->setOwner($user);
        $vehicle->setName('Test Delete Todo Vehicle');
        $vehicle->setVehicleType($this->getVehicleType('Car'));
        $vehicle->setRegistration('DELETE-TD');
        $vehicle->setMileage(30000);
        $vehicle->setPurchaseCost('4000.00');
        $vehicle->setPurchaseDate(new \DateTime('-3 years'));
        $em->persist($vehicle);

        $todo = new Todo();
        $todo->setVehicle($vehicle);
        $todo->setTitle('To be deleted');
        $todo->setDone(false);
        $em->persist($todo);
        $em->flush();

        $todoId = $todo->getId();

        $this->client->request('DELETE', '/api/todos/' . $todoId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();

        // Verify the todo is actually deleted
        $deletedTodo = $em->getRepository(Todo::class)->find($todoId);
        $this->assertNull($deletedTodo, 'Todo should be deleted from database');
    }

    public function testCreateTodoWithParts(): void
    {
        $token = $this->getAuthToken();
        $em = $this->getEntityManager();

        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $vehicle = new Vehicle();
        $vehicle->setOwner($user);
        $vehicle->setName('Test Parts Todo Vehicle');
        $vehicle->setVehicleType($this->getVehicleType('Car'));
        $vehicle->setRegistration('PARTS-TD');
        $vehicle->setMileage(12000);
        $vehicle->setPurchaseCost('7000.00');
        $vehicle->setPurchaseDate(new \DateTime('-6 months'));
        $em->persist($vehicle);

        $part = new Part();
        $part->setVehicle($vehicle);
        $part->setName('Brake rotor');
        $part->setPartNumber('BR-12345');
        $part->setPurchaseDate(new \DateTime());
        $em->persist($part);
        $em->flush();

        $payload = [
            'vehicleId' => $vehicle->getId(),
            'title' => 'Install brake rotor',
            'description' => 'Install new brake rotor',
            'done' => false,
            'parts' => [$part->getId()],
        ];

        $this->client->request('POST', '/api/todos', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Install brake rotor', $responseData['title']);
        $this->assertNotEmpty($responseData['parts']);
    }
}
