<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\UserPreference;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\Trait\JsonValidationTrait;

#[Route('/api/user')]
#[IsGranted('ROLE_USER')]
class UserPreferenceController extends AbstractController
{
    use JsonValidationTrait;

    #[Route('/preferences', name: 'user_preferences_get', methods: ['GET'])]
    public function list(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $prefs = $em->getRepository(UserPreference::class)->findBy(['user' => $user]);
        $out = [];
        foreach ($prefs as $p) {
            $value = $p->getValue();
            // Try to decode JSON values where possible
            $decoded = json_decode($value, true);
            $out[$p->getName()] = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        return new JsonResponse($out);
    }

    #[Route('/preferences', name: 'user_preferences_post', methods: ['POST'])]
    public function save(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $validation = $this->validateJsonRequest($request);
        if ($validation['error']) {
            return $validation['error'];
        }
        $data = $validation['data'];
        $key = $data['key'] ?? $data['name'] ?? null;
        $value = $data['value'] ?? null;

        if (!$key) {
            return new JsonResponse(['error' => 'Missing key'], 400);
        }

        $repo = $em->getRepository(UserPreference::class);
        $pref = $repo->findOneBy(['user' => $user, 'name' => $key]);
        if (!$pref) {
            $pref = new UserPreference();
            $pref->setUser($user);
            $pref->setName($key);
        }

        // Normalize value to string for storage
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        } elseif ($value === null) {
            $value = null;
        } else {
            $value = (string) $value;
        }

        $pref->setValue($value);
        $em->persist($pref);
        $em->flush();

        // Return current value (decoded if JSON)
        $decoded = json_decode($pref->getValue(), true);
        $out = json_last_error() === JSON_ERROR_NONE ? $decoded : $pref->getValue();

        return new JsonResponse(['key' => $key, 'value' => $out]);
    }
}
