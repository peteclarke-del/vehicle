<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Consumable;
use App\Entity\ConsumableType;
use App\Entity\Vehicle;
use App\Entity\VehicleType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:import-consumables', description: 'Import consumables JSON (types and optional consumables)')]
class ImportConsumablesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Path to consumables JSON file')
            ->addOption('vehicle-id', null, InputOption::VALUE_OPTIONAL, 'If provided, create Consumable entries against this vehicle id')
            ->addOption('vehicle-type-id', null, InputOption::VALUE_OPTIONAL, 'VehicleType id to associate ConsumableType entries')
            ->addOption('types-only', null, InputOption::VALUE_NONE, 'Only import ConsumableType entries (do not create Consumable rows)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');
        if (!is_file($file) || !is_readable($file)) {
            $output->writeln('<error>File not found or not readable: ' . $file . '</error>');
            return Command::FAILURE;
        }

        $json = file_get_contents($file);
        $data = json_decode($json, true);
        if (!is_array($data)) {
            $output->writeln('<error>Invalid JSON in file</error>');
            return Command::FAILURE;
        }

        $vehicleId = $input->getOption('vehicle-id');
        $vehicle = null;
        if ($vehicleId) {
            $vehicle = $this->entityManager->getRepository(Vehicle::class)->find((int)$vehicleId);
            if (!$vehicle) {
                $output->writeln('<error>Vehicle not found: ' . $vehicleId . '</error>');
                return Command::FAILURE;
            }
        }

        $vehicleTypeId = $input->getOption('vehicle-type-id');
        $vehicleType = null;
        if ($vehicleTypeId) {
            $vehicleType = $this->entityManager->getRepository(VehicleType::class)->find((int)$vehicleTypeId);
            if (!$vehicleType) {
                $output->writeln('<error>VehicleType not found: ' . $vehicleTypeId . '</error>');
                return Command::FAILURE;
            }
        } elseif ($vehicle) {
            $vehicleType = $vehicle->getVehicleType();
        }

        $typesOnly = (bool)$input->getOption('types-only');

        $createdTypes = 0;
        $createdConsumables = 0;

        // Support wrapped payloads (top-level 'consumables')
        if (isset($data['consumables']) && is_array($data['consumables'])) {
            $items = $data['consumables'];
        } else {
            $items = $data;
        }

        foreach ($items as $c) {
            $typeName = $c['consumableType'] ?? ($c['consumableTypeName'] ?? ($c['type'] ?? null));
            $typeName = is_string($typeName) ? trim($typeName) : null;

            $consumableType = null;
            if ($typeName) {
                $criteria = ['name' => $typeName];
                if ($vehicleType) {
                    $criteria['vehicleType'] = $vehicleType;
                }
                $consumableType = $this->entityManager->getRepository(ConsumableType::class)->findOneBy($criteria);
                if (!$consumableType) {
                    $consumableType = new ConsumableType();
                    $consumableType->setName($typeName);
                    if ($vehicleType) {
                        $consumableType->setVehicleType($vehicleType);
                    }
                    if (!empty($c['unit'])) {
                        $consumableType->setUnit($c['unit']);
                    }
                    $this->entityManager->persist($consumableType);
                    $createdTypes++;
                }
            }

            if ($typesOnly) {
                continue;
            }

            if ($vehicle === null) {
                // Skip creating consumable rows if no vehicle provided
                continue;
            }

            $consumable = new Consumable();
            $consumable->setVehicle($vehicle);
            if ($consumableType) {
                $consumable->setConsumableType($consumableType);
            }

            if (!empty($c['description'])) {
                $consumable->setDescription($c['description']);
            }
            if (!empty($c['brand'])) {
                $consumable->setBrand($c['brand']);
            }
            if (!empty($c['partNumber'])) {
                $consumable->setPartNumber($c['partNumber']);
            }
            if (isset($c['quantity'])) {
                $consumable->setQuantity($c['quantity']);
            }
            if (!empty($c['lastChanged'])) {
                try {
                    $consumable->setLastChanged(new \DateTime($c['lastChanged']));
                } catch (\Exception) {
                }
            }
            if (isset($c['mileageAtChange'])) {
                $consumable->setMileageAtChange((int)$c['mileageAtChange']);
            }
            if (isset($c['cost'])) {
                $consumable->setCost($c['cost']);
            }
            if (!empty($c['notes'])) {
                $consumable->setNotes($c['notes']);
            }
            if (!empty($c['supplier'])) {
                $consumable->setSupplier($c['supplier']);
            }

            $this->entityManager->persist($consumable);
            $createdConsumables++;
        }

        $this->entityManager->flush();

        $output->writeln('<info>Import complete.</info>');
        $output->writeln('Created ConsumableType rows: ' . $createdTypes);
        if (!$typesOnly) {
            $output->writeln('Created Consumable rows: ' . $createdConsumables);
        }

        return Command::SUCCESS;
    }
}
