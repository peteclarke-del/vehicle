<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\PartCategory;
use App\Entity\VehicleType;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * class PartCategoryFixtures
 */
class PartCategoryFixtures extends \App\DataFixtures\AbstractJsonFixture implements DependentFixtureInterface
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
        $categoryNames = [];
        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }
            $typeNames[] = $item['vehicleType'] ?? null;
            $categoryNames[] = $item['name'] ?? null;
        }
        $typeNames = array_values(array_unique(array_filter($typeNames)));
        $categoryNames = array_filter($categoryNames);
        $categoryNames = array_values(array_unique($categoryNames));

        if (count($typeNames) === 0) {
            return;
        }

        $typeRepo = $manager->getRepository(VehicleType::class);
        $types = $typeRepo->findBy(['name' => $typeNames]);
        foreach ($types as $t) {
            $this->_typeMap[$t->getName()] = $t;
        }

        if (count($categoryNames) > 0 && count($types) > 0) {
            $existing = $manager->getRepository(PartCategory::class)
                ->findBy(['vehicleType' => $types, 'name' => $categoryNames]);
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
        return '*/parts.json';
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
        $description = $item['description'] ?? '';

        if (!$typeName || !$name) {
            return;
        }

        $vehicleType = $this->_typeMap[$typeName] ?? null;
        if (!$vehicleType) {
            return;
        }

        $key = $vehicleType->getId() . '-' . $name;
        if (isset($this->_existing[$key])) {
            return;
        }

        $pc = new PartCategory();
        $pc->setVehicleType($vehicleType);
        $pc->setName($name);
        $pc->setDescription($description);
        $manager->persist($pc);
        $this->_existing[$key] = true;
    }

    /**
     * function getDependencies
     *
     * @return array
     */
    public function getDependencies(): array
    {
        return [VehicleModelFixtures::class];
    }
}
