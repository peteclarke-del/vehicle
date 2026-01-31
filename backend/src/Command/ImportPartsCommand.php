<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Part;
use App\Entity\PartCategory;
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

#[AsCommand(name: 'app:import-parts', description: 'Import parts/categories JSON (categories and optional part rows)')]
class ImportPartsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Path to parts JSON file')
            ->addOption('vehicle-id', null, InputOption::VALUE_OPTIONAL, 'If provided, create Part entries against this vehicle id')
            ->addOption('vehicle-type-id', null, InputOption::VALUE_OPTIONAL, 'VehicleType id to associate PartCategory entries')
            ->addOption('types-only', null, InputOption::VALUE_NONE, 'Only import PartCategory entries (do not create Part rows)');
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
        $createdParts = 0;

        // Support wrapped payloads (top-level 'parts')
        if (isset($data['parts']) && is_array($data['parts'])) {
            $items = $data['parts'];
        } else {
            $items = $data;
        }

        foreach ($items as $item) {
            $typeName = $item['name'] ?? null;
            $typeName = is_string($typeName) ? trim($typeName) : null;

            $partCategory = null;
            if ($typeName) {
                $criteria = ['name' => $typeName];
                if ($vehicleType) {
                    $criteria['vehicleType'] = $vehicleType;
                }
                $partCategory = $this->entityManager->getRepository(PartCategory::class)->findOneBy($criteria);
                if (!$partCategory) {
                    $partCategory = new PartCategory();
                    $partCategory->setName($typeName);
                    if ($vehicleType) {
                        $partCategory->setVehicleType($vehicleType);
                    }
                    if (!empty($item['description'])) {
                        $partCategory->setDescription($item['description']);
                    }
                    $this->entityManager->persist($partCategory);
                    $createdTypes++;
                }
            }

            if ($typesOnly) {
                continue;
            }

            if ($vehicle === null) {
                // Skip creating part rows if no vehicle provided
                continue;
            }

            // If the item includes explicit part fields (e.g., partNumber, price), create a Part row
            $hasPartFields = !empty($item['partNumber']) || isset($item['price']) || isset($item['cost']) || !empty($item['description']);
            if ($hasPartFields) {
                $part = new Part();
                $part->setVehicle($vehicle);
                // set description/name
                if (!empty($item['description'])) {
                    $part->setDescription($item['description']);
                } else {
                    $part->setDescription($item['name'] ?? '');
                }
                if (!empty($item['name'])) {
                    $part->setName($item['name']);
                }
                if (!empty($item['partNumber'])) {
                    $part->setPartNumber($item['partNumber']);
                }
                if (isset($item['price'])) {
                    $part->setPrice($item['price']);
                }
                if (isset($item['quantity'])) {
                    $part->setQuantity((int)$item['quantity']);
                }
                if (isset($item['cost'])) {
                    $part->setCost($item['cost']);
                }
                if (!empty($item['manufacturer'])) {
                    $part->setManufacturer($item['manufacturer']);
                }
                if (!empty($item['supplier'])) {
                    $part->setSupplier($item['supplier']);
                }
                if ($partCategory) {
                    $part->setPartCategory($partCategory);
                }

                $this->entityManager->persist($part);
                $createdParts++;
            }
        }

        $this->entityManager->flush();

        $output->writeln('<info>Import complete.</info>');
        $output->writeln('Created PartCategory rows: ' . $createdTypes);
        if (!$typesOnly) {
            $output->writeln('Created Part rows: ' . $createdParts);
        }

        return Command::SUCCESS;
    }
}
