<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\SecurityFeature;
use App\Entity\VehicleType;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * class SecurityFeatureFixtures
 */
class SecurityFeatureFixtures extends \App\DataFixtures\AbstractJsonFixture implements DependentFixtureInterface
{
    /**
     * @var array
     */
    private array $_typeMap = [];

    /**
     * @var array
     */
    private array $_existing = [];

    /**
     * function beforeLoad
     *
     * @param ObjectManager $manager
     * @param array $data
     *
     * @return void
     */
    protected function beforeLoad(ObjectManager $manager, array $data): void
    {
        $typeNames = [];
        $featureNames = [];
        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }
            $typeNames[] = $item['vehicleType'] ?? null;
            $featureNames[] = $item['name'] ?? null;
        }
        $typeNames = array_values(array_unique(array_filter($typeNames)));
        $featureNames = array_filter($featureNames);
        $featureNames = array_values(array_unique($featureNames));

        if (count($typeNames) === 0) {
            return;
        }

        $typeRepo = $manager->getRepository(VehicleType::class);
        $types = $typeRepo->findBy(['name' => $typeNames]);
        foreach ($types as $t) {
            $this->_typeMap[$t->getName()] = $t;
        }

        if (count($featureNames) > 0 && count($types) > 0) {
            $existing = $manager->getRepository(SecurityFeature::class)
                ->findBy(['vehicleType' => $types, 'name' => $featureNames]);
            foreach ($existing as $e) {
                $k = $e->getVehicleType()->getId() . '-' . $e->getName();
                $this->_existing[$k] = true;
            }
        }
    }

    /**
     * function getDataFilename
     *
     * @return string
     */
    protected function getDataFilename(): string
    {
        return '*/security_features.json';
    }

    /**
     * function processItem
     *
     * @param mixed $item
     * @param ObjectManager $manager
     *
     * @return void
     */
    protected function processItem(mixed $item, ObjectManager $manager): void
    {
        if (!is_array($item)) {
            return;
        }

        $typeName = $item['vehicleType'] ?? null;
        $name = $item['name'] ?? null;
        $description = $item['description'] ?? null;

        if (!$typeName || !$name) {
            return;
        }

        $vehicleType = $this->_typeMap[$typeName] ?? null;
        if (!$vehicleType) {
            return;
        }

        $k = $vehicleType->getId() . '-' . $name;
        if (isset($this->_existing[$k])) {
            return;
        }

        $feature = new SecurityFeature();
        $feature->setName($name);
        $feature->setDescription($description);
        $feature->setVehicleType($vehicleType);

        $manager->persist($feature);
    }

    /**
     * function getDependencies
     *
     * @return array
     */
    public function getDependencies(): array
    {
        return [
            VehicleTypeFixtures::class,
        ];
    }
}
