<?php

namespace App\Controller;

use App\Entity\Todo;
use App\Entity\Vehicle;
use App\Entity\Part;
use App\Entity\Consumable;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use App\Controller\Trait\UserSecurityTrait;

#[Route('/api/todos')]
#[IsGranted('ROLE_USER')]
class TodoController extends AbstractController
{
    use UserSecurityTrait;
    private EntityManagerInterface $em;
    private ValidatorInterface $validator;

    public function __construct(EntityManagerInterface $em, ValidatorInterface $validator)
    {
        $this->em = $em;
        $this->validator = $validator;
    }

    #[Route('', name: 'api_todos_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $vehicleId = $request->query->get('vehicleId');

        if ($vehicleId) {
            $vehicle = $this->em->getRepository(\App\Entity\Vehicle::class)->find($vehicleId);
            if (!$vehicle || (!$this->isAdminForUser($user) && $vehicle->getOwner()->getId() !== $user->getId())) {
                return $this->json(['error' => 'Vehicle not found'], 404);
            }

            $todos = $this->em->getRepository(Todo::class)->findBy(['vehicle' => $vehicle], ['createdAt' => 'DESC']);
        } else {
            $vehicleRepo = $this->em->getRepository(\App\Entity\Vehicle::class);
            $vehicles = $this->isAdminForUser($user) ? $vehicleRepo->findAll() : $vehicleRepo->findBy(['owner' => $user]);
            if (empty($vehicles)) {
                $todos = [];
            } else {
                $qb = $this->em->createQueryBuilder()
                    ->select('t')
                    ->from(Todo::class, 't')
                    ->where('t.vehicle IN (:vehicles)')
                    ->setParameter('vehicles', $vehicles)
                    ->orderBy('t.createdAt', 'DESC');

                $todos = $qb->getQuery()->getResult();
            }
        }

        $data = array_map(fn($t) => $this->serializeTodo($t), $todos);
        return $this->json($data);
    }

    #[Route('', name: 'api_todos_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $vehicleId = $payload['vehicleId'] ?? null;
        $vehicle = $this->em->getRepository(Vehicle::class)->find($vehicleId);
        $user = $this->getUser();
        if (!$vehicle || !$user instanceof User || (!$this->isAdminForUser($user) && $vehicle->getOwner()->getId() !== $user->getId())) {
            return new JsonResponse(['error' => 'Vehicle not found'], Response::HTTP_BAD_REQUEST);
        }

        $todo = new Todo();
        $todo->setVehicle($vehicle);
        $todo->setTitle($payload['title'] ?? '');
        $todo->setDescription($payload['description'] ?? null);
        $todo->setDone(!empty($payload['done']));
        $todo->setDueDate(!empty($payload['dueDate']) ? new \DateTime($payload['dueDate']) : null);
        $todo->setCompletedBy(!empty($payload['completedBy']) ? new \DateTime($payload['completedBy']) : null);

        // attach parts if provided (expect array of ids)
        if (!empty($payload['parts']) && is_array($payload['parts'])) {
            foreach ($payload['parts'] as $pid) {
                $part = $this->em->getRepository(Part::class)->find($pid);
                if ($part) {
                    // only attach parts that do not already have an installation date
                    if (method_exists($part, 'getInstallationDate') && $part->getInstallationDate() === null) {
                        $todo->addPart($part);
                    }
                }
            }
        }
        if (!empty($payload['consumables']) && is_array($payload['consumables'])) {
            foreach ($payload['consumables'] as $cid) {
                $cons = $this->em->getRepository(Consumable::class)->find($cid);
                if ($cons) {
                    // only attach consumables that do not already have a lastChanged date
                    if (method_exists($cons, 'getLastChanged') && $cons->getLastChanged() === null) {
                        $todo->addConsumable($cons);
                    }
                }
            }
        }

        $errors = $this->validator->validate($todo);
        if (count($errors) > 0) {
            return new JsonResponse(['errors' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        // If this todo was created as completed, cascade completed date to linked parts/consumables
        if ($todo->isDone() && $todo->getCompletedBy() instanceof \DateTimeInterface) {
            $completedAt = $todo->getCompletedBy();
            foreach ($todo->getParts() as $part) {
                if (method_exists($part, 'getInstallationDate') && $part->getInstallationDate() === null) {
                    $part->setInstallationDate($completedAt);
                }
            }
            foreach ($todo->getConsumables() as $cons) {
                if (method_exists($cons, 'getLastChanged') && $cons->getLastChanged() === null) {
                    $cons->setLastChanged($completedAt);
                }
            }
        }

        $this->em->persist($todo);
        $this->em->flush();

        return $this->json($this->serializeTodo($todo), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_todos_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $todo = $this->em->getRepository(Todo::class)->find($id);
        if (!$todo) {
            return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();
        if (!$user instanceof User || (!$this->isAdminForUser($user) && $todo->getVehicle()->getOwner()->getId() !== $user->getId())) {
            return $this->json(['error' => 'Not authorized'], 403);
        }

        $payload = json_decode($request->getContent(), true);
        if (isset($payload['vehicleId'])) {
            $vehicle = $this->em->getRepository(Vehicle::class)->find($payload['vehicleId']);
            if ($vehicle) {
                $todo->setVehicle($vehicle);
            }
        }
        if (isset($payload['title'])) {
            $todo->setTitle($payload['title']);
        }
        if (array_key_exists('description', $payload)) {
            $todo->setDescription($payload['description']);
        }
        if (array_key_exists('parts', $payload)) {
            foreach ($todo->getParts() as $existing) {
                $todo->removePart($existing);
            }
            if (is_array($payload['parts'])) {
                foreach ($payload['parts'] as $pid) {
                    $part = $this->em->getRepository(Part::class)->find($pid);
                    if ($part) {
                        // only attach if installationDate not already set
                        if (method_exists($part, 'getInstallationDate') && $part->getInstallationDate() === null) {
                            $todo->addPart($part);
                        }
                    }
                }
            }
        }
        if (array_key_exists('consumables', $payload)) {
            foreach ($todo->getConsumables() as $existing) {
                $todo->removeConsumable($existing);
            }
            if (is_array($payload['consumables'])) {
                foreach ($payload['consumables'] as $cid) {
                    $cons = $this->em->getRepository(Consumable::class)->find($cid);
                    if ($cons) {
                        // only attach if lastChanged not already set
                        if (method_exists($cons, 'getLastChanged') && $cons->getLastChanged() === null) {
                            $todo->addConsumable($cons);
                        }
                    }
                }
            }
        }
        if (isset($payload['done'])) {
            $todo->setDone((bool)$payload['done']);
        }
        if (array_key_exists('dueDate', $payload)) {
            $todo->setDueDate($payload['dueDate'] ? new \DateTime($payload['dueDate']) : null);
        }
        if (array_key_exists('completedBy', $payload)) {
            $todo->setCompletedBy($payload['completedBy'] ? new \DateTime($payload['completedBy']) : null);
        }

        $todo->setUpdatedAt(new \DateTime());

        $errors = $this->validator->validate($todo);
        if (count($errors) > 0) {
            return new JsonResponse(['errors' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        // If todo is now marked done, cascade completed date to linked parts/consumables
        if ($todo->isDone() && $todo->getCompletedBy() instanceof \DateTimeInterface) {
            $completedAt = $todo->getCompletedBy();
            foreach ($todo->getParts() as $part) {
                if (method_exists($part, 'getInstallationDate') && $part->getInstallationDate() === null) {
                    $part->setInstallationDate($completedAt);
                }
            }
            foreach ($todo->getConsumables() as $cons) {
                if (method_exists($cons, 'getLastChanged') && $cons->getLastChanged() === null) {
                    // protect against setters that don't accept nulls/types
                    try {
                        $cons->setLastChanged($completedAt);
                    } catch (\TypeError $e) {
                        // skip if setter signature mismatches
                    }
                }
            }
        } else {
            // If marking as not done, clear installation/lastChanged where allowed
            if (!$todo->isDone()) {
                foreach ($todo->getParts() as $part) {
                    if (method_exists($part, 'setInstallationDate')) {
                        $part->setInstallationDate(null);
                    }
                }
                foreach ($todo->getConsumables() as $cons) {
                    if (method_exists($cons, 'setLastChanged')) {
                        try {
                            $rm = new \ReflectionMethod($cons, 'setLastChanged');
                            $param = $rm->getParameters()[0] ?? null;
                            if ($param === null || $param->allowsNull()) {
                                $cons->setLastChanged(null);
                            }
                        } catch (\ReflectionException $e) {
                            // skip if we cannot reflect
                        } catch (\TypeError $e) {
                            // setter doesn't accept null, skip
                        }
                    }
                }
            }
        }

        $this->em->persist($todo);
        $this->em->flush();

        return $this->json($this->serializeTodo($todo));
    }

    #[Route('/{id}', name: 'api_todos_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $todo = $this->em->getRepository(Todo::class)->find($id);
        if (!$todo) {
            return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();
        if (!$user instanceof User || (!$this->isAdminForUser($user) && $todo->getVehicle()->getOwner()->getId() !== $user->getId())) {
            return $this->json(['error' => 'Not authorized'], 403);
        }

        $this->em->remove($todo);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function serializeTodo(Todo $todo): array
    {
        return [
            'id' => $todo->getId(),
            'vehicleId' => $todo->getVehicle()?->getId(),
            'title' => $todo->getTitle(),
            'description' => $todo->getDescription(),
            'parts' => array_map(fn($p) => $p->getId(), $todo->getParts()),
            'consumables' => array_map(fn($c) => $c->getId(), $todo->getConsumables()),
            'done' => $todo->isDone(),
            'dueDate' => $todo->getDueDate()?->format('Y-m-d'),
            'completedBy' => $todo->getCompletedBy()?->format('Y-m-d'),
            'createdAt' => $todo->getCreatedAt()?->format('c'),
            'updatedAt' => $todo->getUpdatedAt()?->format('c')
        ];
    }
}
