<?php

namespace App\Controller\Api;

use App\Entity\Todo;
use App\Repository\TodoRepository;
use App\Repository\VehicleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/**
 * @Route("/api/todos")
 * @IsGranted("ROLE_USER")
 */
class TodoController extends AbstractController
{
    private $em;
    private $todoRepo;
    private $vehicleRepo;
    private $serializer;
    private $validator;

    public function __construct(EntityManagerInterface $em, TodoRepository $todoRepo, VehicleRepository $vehicleRepo, SerializerInterface $serializer, ValidatorInterface $validator)
    {
        $this->em = $em;
        $this->todoRepo = $todoRepo;
        $this->vehicleRepo = $vehicleRepo;
        $this->serializer = $serializer;
        $this->validator = $validator;
    }

    /**
     * @Route("", methods={"GET"})
     */
    public function list(Request $request): JsonResponse
    {
        $vehicleId = $request->query->get('vehicleId');
        if ($vehicleId) {
            $todos = $this->todoRepo->findBy(['vehicle' => $vehicleId], ['createdAt' => 'DESC']);
        } else {
            $todos = $this->todoRepo->findBy([], ['createdAt' => 'DESC']);
        }

        $data = $this->serializer->serialize($todos, 'json', ['groups' => ['default']]);
        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    /**
     * @Route("", methods={"POST"})
     */
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $vehicleId = $payload['vehicleId'] ?? null;
        $vehicle = $this->vehicleRepo->find($vehicleId);
        if (!$vehicle) {
            return new JsonResponse(['error' => 'Vehicle not found'], Response::HTTP_BAD_REQUEST);
        }

        $todo = new Todo();
        $todo->setVehicle($vehicle);
        $todo->setTitle($payload['title'] ?? '');
        $todo->setDescription($payload['description'] ?? null);
        $todo->setParts($payload['parts'] ?? []);
        $todo->setConsumables($payload['consumables'] ?? []);
        $todo->setDone(!empty($payload['done']));
        $todo->setDueDate(!empty($payload['dueDate']) ? new \DateTime($payload['dueDate']) : null);
        $todo->setCompletedBy(!empty($payload['completedBy']) ? new \DateTime($payload['completedBy']) : null);

        $errors = $this->validator->validate($todo);
        if (count($errors) > 0) {
            return new JsonResponse(['errors' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $this->todoRepo->save($todo);

        $data = $this->serializer->serialize($todo, 'json', ['groups' => ['default']]);
        return new JsonResponse($data, Response::HTTP_CREATED, [], true);
    }

    /**
     * @Route("/{id}", methods={"PUT"})
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $todo = $this->todoRepo->find($id);
        if (!$todo) {
            return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);
        if (isset($payload['vehicleId'])) {
            $vehicle = $this->vehicleRepo->find($payload['vehicleId']);
            if ($vehicle) $todo->setVehicle($vehicle);
        }
        if (isset($payload['title'])) $todo->setTitle($payload['title']);
        if (array_key_exists('description', $payload)) $todo->setDescription($payload['description']);
        if (array_key_exists('parts', $payload)) $todo->setParts($payload['parts'] ?? []);
        if (array_key_exists('consumables', $payload)) $todo->setConsumables($payload['consumables'] ?? []);
        if (isset($payload['done'])) $todo->setDone((bool)$payload['done']);
        if (array_key_exists('dueDate', $payload)) $todo->setDueDate($payload['dueDate'] ? new \DateTime($payload['dueDate']) : null);
        if (array_key_exists('completedBy', $payload)) $todo->setCompletedBy($payload['completedBy'] ? new \DateTime($payload['completedBy']) : null);

        $todo->setUpdatedAt(new \DateTime());

        $errors = $this->validator->validate($todo);
        if (count($errors) > 0) {
            return new JsonResponse(['errors' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $this->todoRepo->save($todo);

        $data = $this->serializer->serialize($todo, 'json', ['groups' => ['default']]);
        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    /**
     * @Route("/{id}", methods={"DELETE"})
     */
    public function delete(int $id): JsonResponse
    {
        $todo = $this->todoRepo->find($id);
        if (!$todo) {
            return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $this->todoRepo->remove($todo);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
