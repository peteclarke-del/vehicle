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
    private const DEFAULTS = [
        'preferredLanguage' => 'en',
        'distanceUnit' => 'miles',
        'sessionTimeout' => 3600,
        'theme' => 'light',
    ];

    /**
     * Decode a stored preference value, unwrapping JSON if applicable.
     */
    private function decodeValue(?string $raw): mixed
    {
        if ($raw === null) {
            return null;
        }
        $decoded = json_decode($raw, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
    }

    /**
     * Resolve a single preference: stored value → user entity getter → default.
     */
    private function resolvePreference(array $prefsByName, object $user, string $name): mixed
    {
        if (isset($prefsByName[$name])) {
            $val = $this->decodeValue($prefsByName[$name]->getValue());
            if ($val !== null) {
                return $val;
            }
        }
        $method = 'get' . ucfirst($name);
        if (method_exists($user, $method)) {
            return $user->{$method}();
        }
        return self::DEFAULTS[$name] ?? null;
    }

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
            // Load all preferences in a single query
            $allPrefs = $em->getRepository(UserPreference::class)->findBy(['user' => $user]);
            $prefsByName = [];
            foreach ($allPrefs as $p) {
                $prefsByName[$p->getName()] = $p;
            }

            $data = [];
            foreach (array_keys(self::DEFAULTS) as $name) {
                $data[$name] = $this->resolvePreference($prefsByName, $user, $name);
            }

            return new JsonResponse(['data' => $data]);
        }

        // well-known keys - read from user_preferences first, fallback to user entity methods if present
        // Single-key lookup: well-known keys get fallback chain, others are raw
        $repo = $em->getRepository(UserPreference::class);
        $pref = $repo->findOneBy(['user' => $user, 'name' => $key]);

        if (array_key_exists($key, self::DEFAULTS)) {
            $prefsByName = $pref ? [$key => $pref] : [];
            $val = $this->resolvePreference($prefsByName, $user, $key);
            return new JsonResponse(['key' => $key, 'value' => $val]);
        }

        $val = $pref ? $this->decodeValue($pref->getValue()) : null;
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

        // Log incoming preference changes for debugging
        try {
            $logger = $this->container->get('logger');
            $logger->info('Preferences POST received', [
                'user_id' => $user?->getId(),
                'user_email' => $user?->getEmail(),
                'key' => $key,
                'value' => $value,
            ]);
        } catch (\Throwable $e) {
            // ignore logging failures
        }

        // well-known keys are stored in user_preferences (see generic branch below)

        // theme is not stored on the users table anymore; it will be saved
        // into the user_preferences table below by the generic branch.

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
