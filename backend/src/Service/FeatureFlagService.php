<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\FeatureFlag;
use App\Entity\User;
use App\Entity\UserFeatureOverride;
use App\Entity\VehicleAssignment;
use Doctrine\ORM\EntityManagerInterface;

/**
 * class FeatureFlagService
 *
 * Resolves effective feature flags per user by merging defaults with per-user overrides.
 * Handles vehicle assignment queries and feature flag enforcement.
 */
class FeatureFlagService
{
    /**
     * function __construct
     *
     * @param EntityManagerInterface $em
     *
     * @return void
     */
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
    }

    /**
     * function getEffectiveFlags
     *
     * Get all feature flags with their effective state for a user.
     * Merges default flag values with any per-user overrides.
     *
     * @param User $user
     *
     * @return array
     */
    public function getEffectiveFlags(User $user): array
    {
        // Admins get all features enabled unconditionally
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->getAllFlagsEnabled();
        }

        $flags = $this->em->getRepository(FeatureFlag::class)->findAll();
        $overrides = $this->em->getRepository(UserFeatureOverride::class)->findBy(['user' => $user]);

        // Index overrides by feature flag ID for fast lookup
        $overrideMap = [];
        foreach ($overrides as $override) {
            $overrideMap[$override->getFeatureFlag()->getId()] = $override->isEnabled();
        }

        $result = [];
        foreach ($flags as $flag) {
            $result[$flag->getFeatureKey()] = $overrideMap[$flag->getId()] ?? $flag->isDefaultEnabled();
        }

        return $result;
    }

    /**
     * function isFeatureEnabled
     *
     * Check if a specific feature is enabled for a user.
     *
     * @param User $user
     * @param string $featureKey
     *
     * @return bool
     */
    public function isFeatureEnabled(User $user, string $featureKey): bool
    {
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        $flag = $this->em->getRepository(FeatureFlag::class)->findOneBy(['featureKey' => $featureKey]);
        if (!$flag) {
            return true; // Unknown flags default to enabled
        }

        $override = $this->em->getRepository(UserFeatureOverride::class)->findOneBy([
            'user' => $user,
            'featureFlag' => $flag,
        ]);

        return $override ? $override->isEnabled() : $flag->isDefaultEnabled();
    }

    /**
     * function setFeatureOverride
     *
     * Set a feature override for a user. Creates or updates the override.
     *
     * @param User $user
     * @param string $featureKey
     * @param bool $enabled
     * @param User $setBy
     *
     * @return void
     */
    public function setFeatureOverride(User $user, string $featureKey, bool $enabled, ?User $setBy = null): void
    {
        $flag = $this->em->getRepository(FeatureFlag::class)->findOneBy(['featureKey' => $featureKey]);
        if (!$flag) {
            throw new \InvalidArgumentException("Unknown feature key: $featureKey");
        }

        $override = $this->em->getRepository(UserFeatureOverride::class)->findOneBy([
            'user' => $user,
            'featureFlag' => $flag,
        ]);

        if (!$override) {
            $override = new UserFeatureOverride();
            $override->setUser($user);
            $override->setFeatureFlag($flag);
            $this->em->persist($override);
        }

        $override->setEnabled($enabled);
        $override->setSetBy($setBy);
        $this->em->flush();
    }

    /**
     * function bulkSetFeatureOverrides
     *
     * Bulk-set feature overrides for a user.
     *
     * @param User $user
     * @param array $features
     * @param User $setBy
     *
     * @return void
     */
    public function bulkSetFeatureOverrides(User $user, array $features, ?User $setBy = null): void
    {
        $flags = $this->em->getRepository(FeatureFlag::class)->findAll();
        $flagMap = [];
        foreach ($flags as $flag) {
            $flagMap[$flag->getFeatureKey()] = $flag;
        }

        $existingOverrides = $this->em->getRepository(UserFeatureOverride::class)->findBy(['user' => $user]);
        $overrideMap = [];
        foreach ($existingOverrides as $override) {
            $overrideMap[$override->getFeatureFlag()->getFeatureKey()] = $override;
        }

        foreach ($features as $featureKey => $enabled) {
            if (!isset($flagMap[$featureKey])) {
                continue;
            }

            $flag = $flagMap[$featureKey];

            if (isset($overrideMap[$featureKey])) {
                $overrideMap[$featureKey]->setEnabled($enabled);
                $overrideMap[$featureKey]->setSetBy($setBy);
            } else {
                $override = new UserFeatureOverride();
                $override->setUser($user);
                $override->setFeatureFlag($flag);
                $override->setEnabled($enabled);
                $override->setSetBy($setBy);
                $this->em->persist($override);
            }
        }

        $this->em->flush();
    }

    /**
     * function resetFeatureOverrides
     *
     * Remove all feature overrides for a user (reset to defaults).
     *
     * @param User $user
     *
     * @return void
     */
    public function resetFeatureOverrides(User $user): void
    {
        $overrides = $this->em->getRepository(UserFeatureOverride::class)->findBy(['user' => $user]);
        foreach ($overrides as $override) {
            $this->em->remove($override);
        }
        $this->em->flush();
    }

    /**
     * function getAllFlagsGrouped
     *
     * Get all feature flags grouped by category.
     *
     * @return array
     */
    public function getAllFlagsGrouped(): array
    {
        $flags = $this->em->getRepository(FeatureFlag::class)->findBy([], ['category' => 'ASC', 'sortOrder' => 'ASC']);
        $grouped = [];
        foreach ($flags as $flag) {
            $grouped[$flag->getCategory()][] = [
                'id' => $flag->getId(),
                'featureKey' => $flag->getFeatureKey(),
                'label' => $flag->getLabel(),
                'description' => $flag->getDescription(),
                'defaultEnabled' => $flag->isDefaultEnabled(),
                'sortOrder' => $flag->getSortOrder(),
            ];
        }
        return $grouped;
    }

    /**
     * function getAllFlags
     *
     * Get all feature flags as a flat list.
     *
     * @return array
     */
    public function getAllFlags(): array
    {
        $flags = $this->em->getRepository(FeatureFlag::class)->findBy([], ['category' => 'ASC', 'sortOrder' => 'ASC']);
        return array_map(fn(FeatureFlag $f) => [
            'id' => $f->getId(),
            'featureKey' => $f->getFeatureKey(),
            'label' => $f->getLabel(),
            'description' => $f->getDescription(),
            'category' => $f->getCategory(),
            'defaultEnabled' => $f->isDefaultEnabled(),
            'sortOrder' => $f->getSortOrder(),
        ], $flags);
    }

    /**
     * function getVehicleAssignments
     *
     * Get vehicle assignments for a user.
     *
     * @param User $user
     *
     * @return array
     */
    public function getVehicleAssignments(User $user): array
    {
        return $this->em->getRepository(VehicleAssignment::class)->findBy(['assignedTo' => $user]);
    }

    /**
     * function getAssignedVehicleIds
     *
     * Get assigned vehicle IDs for a user (for filtering).
     *
     * @param User $user
     *
     * @return array
     */
    public function getAssignedVehicleIds(User $user): array
    {
        $assignments = $this->getVehicleAssignments($user);
        return array_map(fn(VehicleAssignment $a) => $a->getVehicle()->getId(), $assignments);
    }

    /**
     * function getVehicleAssignment
     *
     * Check if a user has assignment-based access to a vehicle.
     * Returns null if no assignments exist (unrestricted), true/false otherwise.
     *
     * @param User $user
     * @param int $vehicleId
     *
     * @return VehicleAssignment
     */
    public function getVehicleAssignment(User $user, int $vehicleId): ?VehicleAssignment
    {
        return $this->em->getRepository(VehicleAssignment::class)->findOneBy([
            'assignedTo' => $user,
            'vehicle' => $vehicleId,
        ]);
    }

    /**
     * function getSerializedAssignments
     *
     * Get serialized vehicle assignments for API response.
     *
     * @param User $user
     *
     * @return array
     */
    public function getSerializedAssignments(User $user): array
    {
        $assignments = $this->getVehicleAssignments($user);
        return array_map(fn(VehicleAssignment $a) => [
            'vehicleId' => $a->getVehicle()->getId(),
            'vehicleName' => $a->getVehicle()->getName(),
            'canView' => $a->canView(),
            'canEdit' => $a->canEdit(),
            'canAddRecords' => $a->canAddRecords(),
            'canDelete' => $a->canDelete(),
        ], $assignments);
    }

    /**
     * function getAllFlagsEnabled
     *
     * Get all flags as enabled (for admins).
     *
     * @return array
     */
    private function getAllFlagsEnabled(): array
    {
        $flags = $this->em->getRepository(FeatureFlag::class)->findAll();
        $result = [];
        foreach ($flags as $flag) {
            $result[$flag->getFeatureKey()] = true;
        }
        return $result;
    }

    /**
     * Resolve the feature key required for a given API request.
     * Uses URL path pattern + HTTP method to determine the feature key.
     *
     * @return string|null Feature key or null if no restriction applies
     */
    public function resolveFeatureKey(string $path, string $method): ?string
    {
        $method = strtoupper($method);

        // Exact path overrides (most specific first)
        $exactMap = [
            '/api/vehicles/export' => 'import_export.export',
            '/api/vehicles/export-zip' => 'import_export.export',
            '/api/vehicles/import' => 'import_export.import',
            '/api/vehicles/import-zip' => 'import_export.import',
            '/api/vehicles/purge-all' => 'import_export.import',
        ];

        foreach ($exactMap as $prefix => $featureKey) {
            if (str_starts_with($path, $prefix)) {
                return $featureKey;
            }
        }

        // Dynamic path patterns → category
        $patterns = [
            '#^/api/vehicles/\d+/specifications#' => 'specifications',
            '#^/api/vehicles/\d+/images#' => 'images',
            '#^/api/vehicles/\d+/vin-decode#' => 'specifications',
            '#^/api/fuel-records#' => 'fuel',
            '#^/api/service-records#' => 'services',
            '#^/api/mot-records#' => 'mot',
            '#^/api/parts#' => 'parts',
            '#^/api/consumables#' => 'consumables',
            '#^/api/insurance#' => 'insurance',
            '#^/api/road-tax#' => 'tax',
            '#^/api/todos#' => 'todos',
            '#^/api/attachments#' => 'attachments',
            '#^/api/reports#' => 'reports',
            '#^/api/vehicles#' => 'vehicles',
            '#^/api/security-features#' => 'vehicles',
        ];

        $category = null;
        foreach ($patterns as $pattern => $cat) {
            if (preg_match($pattern, $path)) {
                $category = $cat;
                break;
            }
        }

        if (!$category) {
            return null; // No restriction for this path
        }

        // Method → action mapping
        $action = match ($method) {
            'GET' => 'view',
            'POST' => 'create',
            'PUT', 'PATCH' => 'edit',
            'DELETE' => 'delete',
            default => 'view',
        };

        // Special action overrides
        if ($category === 'reports' && $method === 'POST') {
            $action = 'generate';
        }
        if ($category === 'images' && $method === 'POST') {
            $action = 'upload';
        }
        if ($category === 'attachments' && $method === 'POST') {
            $action = 'upload';
        }

        $featureKey = "$category.$action";

        // Validate this is a known feature key
        $allDefaults = $this->getDefaultFeatureFlags();
        $knownKeys = array_column($allDefaults, 'key');
        if (!in_array($featureKey, $knownKeys, true)) {
            return null; // Unknown combination - allow by default
        }

        return $featureKey;
    }

    /**
     * function seedDefaults
     *
     * Seed the default feature flags into the database.
     * Safe to call multiple times - skips existing keys.
     *
     * @return int
     */
    public function seedDefaults(): int
    {
        $existing = $this->em->getRepository(FeatureFlag::class)->findAll();
        $existingKeys = array_map(fn(FeatureFlag $f) => $f->getFeatureKey(), $existing);

        $defaults = $this->getDefaultFeatureFlags();
        $created = 0;

        foreach ($defaults as $data) {
            if (in_array($data['key'], $existingKeys, true)) {
                continue;
            }

            $flag = new FeatureFlag();
            $flag->setFeatureKey($data['key']);
            $flag->setLabel($data['label']);
            $flag->setDescription($data['description']);
            $flag->setCategory($data['category']);
            $flag->setDefaultEnabled($data['default']);
            $flag->setSortOrder($data['sort']);
            $this->em->persist($flag);
            $created++;
        }

        $this->em->flush();
        return $created;
    }

    /**
     * function getDefaultFeatureFlags
     *
     * Get the full list of default feature flag definitions.
     *
     * @return array
     */
    private function getDefaultFeatureFlags(): array
    {
        $sort = 0;
        $flags = [];

        $addFlags = function (string $category, array $items) use (&$flags, &$sort) {
            foreach ($items as $key => $label) {
                $flags[] = [
                    'key' => $key,
                    'label' => $label,
                    'description' => "Controls access to: $label",
                    'category' => $category,
                    'default' => true,
                    'sort' => $sort++,
                ];
            }
        };

        $addFlags('Dashboard', [
            'dashboard.view' => 'View Dashboard',
        ]);

        $addFlags('Vehicles', [
            'vehicles.view' => 'View Vehicles',
            'vehicles.create' => 'Create Vehicles',
            'vehicles.edit' => 'Edit Vehicles',
            'vehicles.delete' => 'Delete Vehicles',
        ]);

        $addFlags('Fuel Records', [
            'fuel.view' => 'View Fuel Records',
            'fuel.create' => 'Create Fuel Records',
            'fuel.edit' => 'Edit Fuel Records',
            'fuel.delete' => 'Delete Fuel Records',
        ]);

        $addFlags('Service Records', [
            'services.view' => 'View Service Records',
            'services.create' => 'Create Service Records',
            'services.edit' => 'Edit Service Records',
            'services.delete' => 'Delete Service Records',
        ]);

        $addFlags('MOT Records', [
            'mot.view' => 'View MOT Records',
            'mot.create' => 'Create MOT Records',
            'mot.edit' => 'Edit MOT Records',
            'mot.delete' => 'Delete MOT Records',
        ]);

        $addFlags('Parts', [
            'parts.view' => 'View Parts',
            'parts.create' => 'Create Parts',
            'parts.edit' => 'Edit Parts',
            'parts.delete' => 'Delete Parts',
        ]);

        $addFlags('Consumables', [
            'consumables.view' => 'View Consumables',
            'consumables.create' => 'Create Consumables',
            'consumables.edit' => 'Edit Consumables',
            'consumables.delete' => 'Delete Consumables',
        ]);

        $addFlags('Insurance', [
            'insurance.view' => 'View Insurance Policies',
            'insurance.create' => 'Create Insurance Policies',
            'insurance.edit' => 'Edit Insurance Policies',
            'insurance.delete' => 'Delete Insurance Policies',
        ]);

        $addFlags('Road Tax', [
            'tax.view' => 'View Road Tax',
            'tax.create' => 'Create Road Tax',
            'tax.edit' => 'Edit Road Tax',
            'tax.delete' => 'Delete Road Tax',
        ]);

        $addFlags('Todos', [
            'todos.view' => 'View Todos',
            'todos.create' => 'Create Todos',
            'todos.edit' => 'Edit Todos',
            'todos.delete' => 'Delete Todos',
        ]);

        $addFlags('Attachments', [
            'attachments.view' => 'View Attachments',
            'attachments.upload' => 'Upload Attachments',
            'attachments.delete' => 'Delete Attachments',
        ]);

        $addFlags('Reports', [
            'reports.generate' => 'Generate Reports',
        ]);

        $addFlags('Import / Export', [
            'import_export.import' => 'Import Vehicles',
            'import_export.export' => 'Export Vehicles',
        ]);

        $addFlags('Specifications', [
            'specifications.view' => 'View Specifications',
            'specifications.edit' => 'Edit Specifications',
        ]);

        $addFlags('Images', [
            'images.view' => 'View Images',
            'images.upload' => 'Upload Images',
            'images.delete' => 'Delete Images',
        ]);

        $addFlags('Settings', [
            'settings.edit' => 'Edit Settings',
        ]);

        return $flags;
    }
}
