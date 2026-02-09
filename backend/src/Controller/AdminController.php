<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Trait\UserSecurityTrait;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleAssignment;
use App\Service\FeatureFlagService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/admin')]
class AdminController extends AbstractController
{
    use UserSecurityTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FeatureFlagService $featureFlagService,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Verify the current user is an admin. Returns error response or null.
     */
    private function requireAdmin(): ?JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        if (!$this->isAdminForUser($user)) {
            return $this->json(['error' => 'Admin access required'], 403);
        }

        return null;
    }

    // ─── Users ─────────────────────────────────────────────────────────

    /**
     * List all non-admin users with their feature override counts and assignment counts.
     */
    #[Route('/users', name: 'api_admin_users_list', methods: ['GET'])]
    public function listUsers(): JsonResponse
    {
        $error = $this->requireAdmin();
        if ($error) return $error;

        $users = $this->entityManager->getRepository(User::class)->findAll();

        $data = [];
        foreach ($users as $user) {
            $data[] = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'roles' => $user->getRoles(),
                'isActive' => $user->isActive(),
                'isVerified' => $user->isVerified(),
                'passwordChangeRequired' => $user->isPasswordChangeRequired(),
                'lastLoginAt' => $user->getLastLoginAt()?->format('c'),
                'createdAt' => $user->getCreatedAt()?->format('c'),
            ];
        }

        return $this->json($data);
    }

    /**
     * Create a new user (admin only).
     */
    #[Route('/users', name: 'api_admin_users_create', methods: ['POST'])]
    public function createUser(Request $request): JsonResponse
    {
        $error = $this->requireAdmin();
        if ($error) return $error;

        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['password'])) {
            return $this->json(['error' => 'Email and password are required'], 400);
        }

        if (empty($data['firstName']) || empty($data['lastName'])) {
            return $this->json(['error' => 'First name and last name are required'], 400);
        }

        $existing = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existing) {
            return $this->json(['error' => 'A user with this email already exists'], 400);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstName($data['firstName']);
        $user->setLastName($data['lastName']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        $user->setRoles($data['isAdmin'] ?? false ? ['ROLE_ADMIN', 'ROLE_USER'] : ['ROLE_USER']);
        $user->setPasswordChangeRequired($data['forcePasswordChange'] ?? true);
        $user->setIsActive(true);

        $this->entityManager->persist($user);

        // Create default preferences
        $defaults = ['preferredLanguage' => 'en', 'distanceUnit' => 'mi', 'theme' => 'light'];
        foreach ($defaults as $name => $value) {
            $pref = new \App\Entity\UserPreference();
            $pref->setUser($user);
            $pref->setName($name);
            $pref->setValue($value);
            $this->entityManager->persist($pref);
        }

        $this->entityManager->flush();

        $admin = $this->getUser();
        $this->logger->info('Admin created new user', [
            'adminId' => $admin instanceof User ? $admin->getId() : null,
            'userId' => $user->getId(),
            'email' => $user->getEmail(),
        ]);

        return $this->json([
            'message' => 'User created successfully',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'roles' => $user->getRoles(),
                'isActive' => true,
                'isVerified' => false,
                'passwordChangeRequired' => $user->isPasswordChangeRequired(),
                'lastLoginAt' => null,
                'createdAt' => $user->getCreatedAt()?->format('c'),
            ],
        ], 201);
    }

    /**
     * Get a single user with full details including features and assignments.
     */
    #[Route('/users/{id}', name: 'api_admin_users_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getUserDetails(int $id): JsonResponse
    {
        $error = $this->requireAdmin();
        if ($error) return $error;

        $user = $this->entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        $features = $this->featureFlagService->getEffectiveFlags($user);
        $assignments = $this->featureFlagService->getSerializedAssignments($user);

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getRoles(),
            'isActive' => $user->isActive(),
            'isVerified' => $user->isVerified(),
            'lastLoginAt' => $user->getLastLoginAt()?->format('c'),
            'createdAt' => $user->getCreatedAt()?->format('c'),
            'features' => $features,
            'vehicleAssignments' => $assignments,
        ]);
    }

    /**
     * Update a user's roles.
     */
    #[Route('/users/{id}/roles', name: 'api_admin_user_roles_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function updateUserRoles(int $id, Request $request): JsonResponse
    {
        $error = $this->requireAdmin();
        if ($error) return $error;

        $user = $this->entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
            return $this->json(['error' => 'Cannot change your own roles'], 400);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['roles']) || !is_array($data['roles'])) {
            return $this->json(['error' => 'Missing or invalid "roles" field'], 400);
        }

        $allowed = ['ROLE_USER', 'ROLE_ADMIN'];
        $roles = array_values(array_intersect($data['roles'], $allowed));
        if (!in_array('ROLE_USER', $roles)) {
            $roles[] = 'ROLE_USER';
        }

        $user->setRoles($roles);
        $this->entityManager->flush();

        $this->logger->info('Admin updated user roles', [
            'adminId' => $currentUser instanceof User ? $currentUser->getId() : null,
            'userId' => $user->getId(),
            'roles' => $roles,
        ]);

        return $this->json([
            'message' => 'Roles updated',
            'roles' => $user->getRoles(),
        ]);
    }

    /**
     * Toggle a user's active status.
     */
    #[Route('/users/{id}/toggle-active', name: 'api_admin_user_toggle_active', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function toggleUserActive(int $id): JsonResponse
    {
        $error = $this->requireAdmin();
        if ($error) return $error;

        $user = $this->entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        // Prevent admin from disabling themselves
        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
            return $this->json(['error' => 'Cannot disable your own account'], 400);
        }

        $user->setIsActive(!$user->isActive());
        $this->entityManager->flush();

        $this->logger->info('Admin toggled user active status', [
            'adminId' => $currentUser instanceof User ? $currentUser->getId() : null,
            'userId' => $user->getId(),
            'isActive' => $user->isActive(),
        ]);

        return $this->json([
            'message' => $user->isActive() ? 'User activated' : 'User deactivated',
            'isActive' => $user->isActive(),
        ]);
    }

    /**
     * Force a user to change their password on next login.
     */
    #[Route('/users/{id}/force-password-change', name: 'api_admin_user_force_pw', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function forcePasswordChange(int $id): JsonResponse
    {
        $error = $this->requireAdmin();
        if ($error) return $error;

        $user = $this->entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        $user->setPasswordChangeRequired(true);
        $this->entityManager->flush();

        $currentUser = $this->getUser();
        $this->logger->info('Admin forced password change for user', [
            'adminId' => $currentUser instanceof User ? $currentUser->getId() : null,
            'userId' => $user->getId(),
        ]);

        return $this->json([
            'message' => 'User will be required to change password on next login',
            'passwordChangeRequired' => true,
        ]);
    }

    // ─── Feature Flags ─────────────────────────────────────────────────

    /**
     * Get all available feature flags grouped by category.
     */
    #[Route('/feature-flags', name: 'api_admin_feature_flags_list', methods: ['GET'])]
    public function listFeatureFlags(): JsonResponse
    {
        $error = $this->requireAdmin();
        if ($error) return $error;

        return $this->json($this->featureFlagService->getAllFlagsGrouped());
    }

    /**
     * Get feature flag overrides for a specific user.
     */
    #[Route('/users/{id}/features', name: 'api_admin_user_features_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getUserFeatures(int $id): JsonResponse
    {
        $error = $this->requireAdmin();
        if ($error) return $error;

        $user = $this->entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        return $this->json([
            'features' => $this->featureFlagService->getEffectiveFlags($user),
            'allFlags' => $this->featureFlagService->getAllFlagsGrouped(),
        ]);
    }

    /**
     * Bulk-update feature flags for a user.
     * Body: { "features": { "vehicles.view": true, "fuel.create": false, ... } }
     */
    #[Route('/users/{id}/features', name: 'api_admin_user_features_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateUserFeatures(int $id, Request $request): JsonResponse
    {
        $error = $this->requireAdmin();
        if ($error) return $error;

        $user = $this->entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['features']) || !is_array($data['features'])) {
            return $this->json(['error' => 'Missing or invalid "features" field'], 400);
        }

        $admin = $this->getUser();
        $this->featureFlagService->bulkSetFeatureOverrides($user, $data['features'], $admin instanceof User ? $admin : null);

        $this->logger->info('Admin updated feature flags for user', [
            'adminId' => $admin instanceof User ? $admin->getId() : null,
            'userId' => $user->getId(),
            'features' => $data['features'],
        ]);

        return $this->json([
            'message' => 'Feature flags updated',
            'features' => $this->featureFlagService->getEffectiveFlags($user),
        ]);
    }

    /**
     * Reset all feature overrides for a user back to defaults.
     */
    #[Route('/users/{id}/features/reset', name: 'api_admin_user_features_reset', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function resetUserFeatures(int $id): JsonResponse
    {
        $error = $this->requireAdmin();
        if ($error) return $error;

        $user = $this->entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        $this->featureFlagService->resetFeatureOverrides($user);

        return $this->json([
            'message' => 'Feature flags reset to defaults',
            'features' => $this->featureFlagService->getEffectiveFlags($user),
        ]);
    }

    // ─── Vehicle Assignments ───────────────────────────────────────────

    /**
     * Get vehicle assignments for a user.
     */
    #[Route('/users/{id}/assignments', name: 'api_admin_user_assignments_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getUserAssignments(int $id): JsonResponse
    {
        $error = $this->requireAdmin();
        if ($error) return $error;

        $user = $this->entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        $assignments = $this->featureFlagService->getSerializedAssignments($user);

        // Also return all available vehicles (for the admin to pick from)
        $allVehicles = $this->entityManager->getRepository(Vehicle::class)->findAll();
        $vehicleList = array_map(fn(Vehicle $v) => [
            'id' => $v->getId(),
            'name' => $v->getName(),
            'make' => $v->getMake(),
            'model' => $v->getModel(),
            'year' => $v->getYear(),
            'registrationNumber' => $v->getRegistrationNumber(),
            'ownerId' => $v->getOwner()?->getId(),
            'ownerName' => $v->getOwner() ? ($v->getOwner()->getFirstName() . ' ' . $v->getOwner()->getLastName()) : null,
        ], $allVehicles);

        return $this->json([
            'assignments' => $assignments,
            'availableVehicles' => $vehicleList,
        ]);
    }

    /**
     * Set vehicle assignments for a user.
     * Body: { "assignments": [ { "vehicleId": 1, "canView": true, "canEdit": true, "canAddRecords": true, "canDelete": false }, ... ] }
     * This replaces ALL assignments for the user.
     */
    #[Route('/users/{id}/assignments', name: 'api_admin_user_assignments_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateUserAssignments(int $id, Request $request): JsonResponse
    {
        $error = $this->requireAdmin();
        if ($error) return $error;

        $user = $this->entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['assignments']) || !is_array($data['assignments'])) {
            return $this->json(['error' => 'Missing or invalid "assignments" field'], 400);
        }

        $admin = $this->getUser();

        // Remove existing assignments
        $existing = $this->entityManager->getRepository(VehicleAssignment::class)->findBy(['assignedTo' => $user]);
        foreach ($existing as $assignment) {
            $this->entityManager->remove($assignment);
        }
        $this->entityManager->flush();

        // Create new assignments
        foreach ($data['assignments'] as $assignmentData) {
            if (!isset($assignmentData['vehicleId'])) {
                continue;
            }

            $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($assignmentData['vehicleId']);
            if (!$vehicle) {
                continue;
            }

            $assignment = new VehicleAssignment();
            $assignment->setVehicle($vehicle);
            $assignment->setAssignedTo($user);
            $assignment->setAssignedBy($admin instanceof User ? $admin : null);
            $assignment->setCanView($assignmentData['canView'] ?? true);
            $assignment->setCanEdit($assignmentData['canEdit'] ?? true);
            $assignment->setCanAddRecords($assignmentData['canAddRecords'] ?? true);
            $assignment->setCanDelete($assignmentData['canDelete'] ?? false);

            $this->entityManager->persist($assignment);
        }

        $this->entityManager->flush();

        $this->logger->info('Admin updated vehicle assignments for user', [
            'adminId' => $admin instanceof User ? $admin->getId() : null,
            'userId' => $user->getId(),
            'assignmentCount' => count($data['assignments']),
        ]);

        return $this->json([
            'message' => 'Vehicle assignments updated',
            'assignments' => $this->featureFlagService->getSerializedAssignments($user),
        ]);
    }

    /**
     * Remove all vehicle assignments for a user (give them unrestricted access to own vehicles).
     */
    #[Route('/users/{id}/assignments', name: 'api_admin_user_assignments_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteUserAssignments(int $id): JsonResponse
    {
        $error = $this->requireAdmin();
        if ($error) return $error;

        $user = $this->entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        $existing = $this->entityManager->getRepository(VehicleAssignment::class)->findBy(['assignedTo' => $user]);
        foreach ($existing as $assignment) {
            $this->entityManager->remove($assignment);
        }
        $this->entityManager->flush();

        return $this->json(['message' => 'All vehicle assignments removed']);
    }

    // ─── Seed Feature Flags ─────────────────────────────────────────────

    /**
     * Trigger feature flag seeding via API (useful during initial setup).
     */
    #[Route('/seed-feature-flags', name: 'api_admin_seed_flags', methods: ['POST'])]
    public function seedFeatureFlags(): JsonResponse
    {
        $error = $this->requireAdmin();
        if ($error) return $error;

        $created = $this->featureFlagService->seedDefaults();

        return $this->json([
            'message' => "Seeded feature flags",
            'created' => $created,
        ]);
    }
}
