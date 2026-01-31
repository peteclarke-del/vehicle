<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\VehicleModel;
use App\Entity\VehicleMake;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class VehicleModelFixtures extends \App\DataFixtures\AbstractJsonFixture implements DependentFixtureInterface
{
    private array $_makeMap = [];
    private array $_existingModels = [];

    protected function beforeLoad(ObjectManager $manager, array $data): void
    {
        // build list of make names needed and preload only those makes
        $makeNames = [];
        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }
            $makeNames[] = $item['make'] ?? null;
        }
        $makeNames = array_values(array_unique(array_filter($makeNames)));
        if (count($makeNames) === 0) {
            return;
        }

        $makes = $manager->getRepository(VehicleMake::class)->findBy(['name' => $makeNames]);
        foreach ($makes as $m) {
            $this->_makeMap[$m->getName()] = $m;
        }
        // preload existing models only for the makes we loaded
        if (count($makes) > 0) {
            $models = $manager->getRepository(VehicleModel::class)->findBy(['make' => $makes]);
            foreach ($models as $mod) {
                $this->_existingModels[$mod->getMake()->getId() . '-' . $mod->getName()] = true;
            }
        }
    }

    protected function getDataFilename(): string
    {
        // discover model files under data/<Type>/models/<Make>.json
        return '*/models/*.json';
    }

    protected function processItem(mixed $item, ObjectManager $manager): void
    {
        if (!is_array($item)) {
            return;
        }

        $makeName = $item['make'] ?? null;
        if (!$makeName) {
            return;
        }

        $make = $this->_makeMap[$makeName] ?? null;
        if (!$make) {
            return;
        }

        $name = $item['name'] ?? '';
        $key = $make->getId() . '-' . $name;
        if (isset($this->_existingModels[$key])) {
            return;
        }

        $startYear = $item['startYear'] ?? $item['productionStartYear'] ?? null;
        $endYear = $item['endYear'] ?? $item['productionEndYear'] ?? null;

        $model = new VehicleModel();
        $model->setName($name);
        $model->setStartYear($startYear);
        $model->setEndYear($endYear);
        $model->setMake($make);
        $model->setVehicleType($make->getVehicleType());
        $model->setImageUrl($item['imageUrl'] ?? null);
        $model->setIsActive($item['isActive'] ?? true);

        $manager->persist($model);
        $this->_existingModels[$key] = true;
    }

    public function getDependencies(): array
    {
        return [VehicleMakeFixtures::class];
    }
}
