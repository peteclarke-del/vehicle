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
                // preferredLanguage, sessionTimeout and distanceUnit are stored in user_preferences
                'preferredLanguage' => (function() use ($em, $user) {
                    $repo = $em->getRepository(UserPreference::class);
                    $pref = $repo->findOneBy(['user' => $user, 'name' => 'preferredLanguage']);
                    if ($pref) {
                        $val = $pref->getValue();
                        $decoded = null;
                        if ($val !== null) {
                            $decoded = json_decode($val, true);
                            if (json_last_error() === JSON_ERROR_NONE) $val = $decoded;
                        }
                        return $val;
                    }
                    return method_exists($user, 'getPreferredLanguage') ? $user->getPreferredLanguage() : 'en';
                })(),
                'distanceUnit' => (function() use ($em, $user) {
                    $repo = $em->getRepository(UserPreference::class);
                    $pref = $repo->findOneBy(['user' => $user, 'name' => 'distanceUnit']);
                    if ($pref) {
                        $val = $pref->getValue();
                        $decoded = null;
                        if ($val !== null) {
                            $decoded = json_decode($val, true);
                            if (json_last_error() === JSON_ERROR_NONE) $val = $decoded;
                        }
                        return $val;
                    }
                    return method_exists($user, 'getDistanceUnit') ? $user->getDistanceUnit() : 'miles';
                })(),
                'sessionTimeout' => (function() use ($em, $user) {
                    $repo = $em->getRepository(UserPreference::class);
                    $pref = $repo->findOneBy(['user' => $user, 'name' => 'sessionTimeout']);
                    if ($pref) {
                        $val = $pref->getValue();
                        $decoded = null;
                        if ($val !== null) {
                            $decoded = json_decode($val, true);
                            if (json_last_error() === JSON_ERROR_NONE) $val = $decoded;
                        }
                        return $val;
                    }
                    return method_exists($user, 'getSessionTimeout') ? $user->getSessionTimeout() : 3600;
                })(),
                // theme is now stored in user_preferences table
                'theme' => (function() use ($em, $user) {
                    $repo = $em->getRepository(UserPreference::class);
                    $pref = $repo->findOneBy(['user' => $user, 'name' => 'theme']);
                    if (!$pref) return 'light';
                    $val = $pref->getValue();
                    $decoded = null;
                    if ($val !== null) {
                        $decoded = json_decode($val, true);
                        if (json_last_error() === JSON_ERROR_NONE) $val = $decoded;
                    }
                    return $val;
                })(),
            ];
            return new JsonResponse(['data' => $data]);
        }

        // well-known keys - read from user_preferences first, fallback to user entity methods if present
        if (in_array($key, ['distanceUnit','sessionTimeout','preferredLanguage'])) {
            $repo = $em->getRepository(UserPreference::class);
            $pref = $repo->findOneBy(['user' => $user, 'name' => $key]);
            if ($pref) {
                $val = $pref->getValue();
                $decoded = null;
                if ($val !== null) {
                    $decoded = json_decode($val, true);
                    if (json_last_error() === JSON_ERROR_NONE) $val = $decoded;
                }

                // If the stored value is null (or JSON 'null'), fall back to the
                // user entity getter or a sensible default for well-known keys.
                if ($val === null) {
                    $method = 'get' . ucfirst($key);
                    if (method_exists($user, $method)) {
                        return new JsonResponse(['key' => $key, 'value' => $user->{$method}()]);
                    }
                    // sensible defaults when no user getter exists
                    switch ($key) {
                        case 'sessionTimeout':
                            return new JsonResponse(['key' => $key, 'value' => 3600]);
                        case 'distanceUnit':
                            return new JsonResponse(['key' => $key, 'value' => 'miles']);
                        case 'preferredLanguage':
                            return new JsonResponse(['key' => $key, 'value' => 'en']);
                        default:
                            return new JsonResponse(['key' => $key, 'value' => null]);
                    }
                }

                return new JsonResponse(['key' => $key, 'value' => $val]);
            }
            // fallback to user property if available
            $method = 'get' . ucfirst($key);
            if (method_exists($user, $method)) {
                return new JsonResponse(['key' => $key, 'value' => $user->{$method}()]);
            }
            return new JsonResponse(['key' => $key, 'value' => null]);
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
