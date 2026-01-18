<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager
    ) {
    }

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // This method can be empty - the json_login firewall handles authentication
        // If we reach here, authentication succeeded
        $user = $this->getUser();

        if (!$user instanceof \App\Entity\User) {
            return $this->json(['error' => 'Authentication required'], 401);
        }

        return $this->json([
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName()
            ]
        ]);
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email']) || !isset($data['password'])) {
            return $this->json(['error' => 'Email and password required'], 400);
        }

        $existingUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $data['email']]);

        if ($existingUser) {
            return $this->json(['error' => 'User already exists'], 400);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstName($data['firstName'] ?? '');
        $user->setLastName($data['lastName'] ?? '');
        $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        $user->setPreferredLanguage($data['preferredLanguage'] ?? 'en');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'User created successfully',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName()
            ]
        ], 201);
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'preferredLanguage' => $user->getPreferredLanguage(),
            'theme' => $user->getTheme(),
            'sessionTimeout' => $user->getSessionTimeout(),
            'distanceUnit' => $user->getDistanceUnit(),
            'roles' => $user->getRoles(),
            'passwordChangeRequired' => $user->isPasswordChangeRequired()
        ]);
    }

    #[Route('/profile', name: 'api_profile_update', methods: ['PUT'])]
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }

        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }

        if (isset($data['preferredLanguage'])) {
            $user->setPreferredLanguage($data['preferredLanguage']);
        }

        if (isset($data['theme'])) {
            $user->setTheme($data['theme']);
        }

        if (isset($data['sessionTimeout'])) {
            $timeout = (int) $data['sessionTimeout'];
            // Validate timeout is between 5 minutes and 24 hours
            if ($timeout >= 300 && $timeout <= 86400) {
                $user->setSessionTimeout($timeout);
            }
        }

        if (isset($data['distanceUnit'])) {
            $validUnits = ['km', 'mi', 'miles'];
            $unit = $data['distanceUnit'];
            
            // Normalize 'miles' to 'mi' for consistency
            if ($unit === 'miles') {
                $unit = 'mi';
            }
            
            if (in_array($unit, ['km', 'mi'])) {
                $user->setDistanceUnit($unit);
            }
        }

        $this->entityManager->flush();

        return $this->json(['message' => 'Profile updated successfully']);
    }

    #[Route('/refresh-token', name: 'api_refresh_token', methods: ['POST'])]
    public function refreshToken(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        // Generate a new JWT token
        $token = $this->jwtManager->create($user);

        return $this->json([
            'message' => 'Token refreshed successfully',
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName()
            ]
        ]);
    }

    #[Route('/change-password', name: 'api_change_password', methods: ['POST'])]
    public function changePassword(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['currentPassword']) || !isset($data['newPassword'])) {
            return $this->json(['error' => 'Current and new password required'], 400);
        }

        // Verify current password
        if (!$this->passwordHasher->isPasswordValid($user, $data['currentPassword'])) {
            return $this->json(['error' => 'Current password is incorrect'], 400);
        }

        // Validate new password
        if (strlen($data['newPassword']) < 8) {
            return $this->json(['error' => 'New password must be at least 8 characters'], 400);
        }

        // Update password and clear the change requirement flag
        $user->setPassword($this->passwordHasher->hashPassword($user, $data['newPassword']));
        $user->setPasswordChangeRequired(false);

        $this->entityManager->flush();

        return $this->json(['message' => 'Password changed successfully']);
    }

    #[Route('/force-password-change/{id}', name: 'api_force_password_change', methods: ['POST'])]
    public function forcePasswordChange(int $id): JsonResponse
    {
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        // Only admins can force password change for other users
        if (!in_array('ROLE_ADMIN', $currentUser->getRoles())) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $user = $this->entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        $user->setPasswordChangeRequired(true);
        $this->entityManager->flush();

        return $this->json(['message' => 'Password change requirement set for user']);
    }
}
