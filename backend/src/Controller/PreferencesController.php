<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\UserPreference;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PreferencesController extends AbstractController
{
    #[Route('', name: 'user_preferences_get', methods: ['GET'])]
    #[Route('/api/user/preferences', name: 'api_user_preferences_get', methods: ['GET'])]
    public function get(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthenticated'], 401);
        }

        $key = $request->query->get('key');
        if (!$key) {
            // return a minimal set of user preferences
            $data = [
                'distanceUnit' => method_exists($user, 'getDistanceUnit') ? $user->getDistanceUnit() : null,
                'sessionTimeout' => method_exists($user, 'getSessionTimeout') ? $user->getSessionTimeout() : null,
            ];
            return new JsonResponse(['data' => $data]);
        }

        // user fields
        if ($key === 'distanceUnit' && method_exists($user, 'getDistanceUnit')) {
            return new JsonResponse(['key' => $key, 'value' => $user->getDistanceUnit()]);
        }
        if ($key === 'sessionTimeout' && method_exists($user, 'getSessionTimeout')) {
            return new JsonResponse(['key' => $key, 'value' => $user->getSessionTimeout()]);
        }

        $repo = $em->getRepository(UserPreference::class);
        $pref = $repo->findOneBy(['user' => $user, 'name' => $key]);
        if (!$pref) {
            return new JsonResponse(['key' => $key, 'value' => null]);
        }

        $val = $pref->getValue();
        // try json decode
        $decoded = null;
        if ($val !== null) {
            $decoded = json_decode($val, true);
            if (json_last_error() === JSON_ERROR_NONE) $val = $decoded;
        }

        return new JsonResponse(['key' => $key, 'value' => $val]);
    }

    #[Route('', name: 'user_preferences_post', methods: ['POST'])]
    #[Route('/api/user/preferences', name: 'api_user_preferences_post', methods: ['POST'])]
    public function post(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthenticated'], 401);
        }

        $payload = json_decode($request->getContent(), true);
        if (!$payload || !array_key_exists('key', $payload)) {
            return new JsonResponse(['error' => 'Invalid payload'], 400);
        }

        $key = (string) $payload['key'];
        $value = $payload['value'] ?? null;

        // Map some well-known keys onto user fields
        if ($key === 'distanceUnit' && method_exists($user, 'setDistanceUnit')) {
            $user->setDistanceUnit((string) $value);
            $em->persist($user);
            $em->flush();
            return new JsonResponse(['key' => $key, 'value' => $value]);
        }

        if ($key === 'sessionTimeout' && method_exists($user, 'setSessionTimeout')) {
            $user->setSessionTimeout((int) $value);
            $em->persist($user);
            $em->flush();
            return new JsonResponse(['key' => $key, 'value' => $value]);
        }

        // otherwise store in user_preferences table
        $repo = $em->getRepository(UserPreference::class);
        $pref = $repo->findOneBy(['user' => $user, 'name' => $key]);
        if (!$pref) {
            $pref = new UserPreference();
            $pref->setUser($user);
            $pref->setName($key);
        }

        // store as JSON for non-string types
        if (is_array($value) || is_object($value)) {
            $pref->setValue(json_encode($value));
        } elseif ($value === null) {
            $pref->setValue(null);
        } else {
            $pref->setValue((string) $value);
        }

        $em->persist($pref);
        $em->flush();

        return new JsonResponse(['key' => $key, 'value' => $value]);
    }
}
