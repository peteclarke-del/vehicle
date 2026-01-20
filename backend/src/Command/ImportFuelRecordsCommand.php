<?php
namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportFuelRecordsCommand extends Command
{
    protected static $defaultName = 'app:import-fuel-records';

    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Import fuel records CSV and attach to a vehicle')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to CSV file')
            ->addOption('vin', null, InputOption::VALUE_OPTIONAL, 'Vehicle VIN to attach records to')
            ->addOption('vehicle-id', null, InputOption::VALUE_OPTIONAL, 'Vehicle database id to attach records to')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');
        if (!file_exists($file)) {
            $output->writeln('<error>File not found: '.$file.'</error>');
            return Command::FAILURE;
        }

        $vin = $input->getOption('vin');
        $vehicleId = $input->getOption('vehicle-id');

        $repo = $this->em->getRepository(\App\Entity\Vehicle::class);
        $vehicle = null;
        if ($vehicleId) {
            $vehicle = $repo->find($vehicleId);
        } elseif ($vin) {
            $vehicle = $repo->findOneBy(['vin' => $vin]);
        }

        if (!$vehicle) {
            $output->writeln('<error>Vehicle not found. Provide --vin or --vehicle-id</error>');
            return Command::FAILURE;
        }

        $handle = fopen($file,'r');
        if (!$handle) {
            $output->writeln('<error>Unable to open file</error>');
            return Command::FAILURE;
        }

        // Read header and map columns
        $header = fgetcsv($handle);
        $output->writeln('<info>Header:</info> '.json_encode($header));
        $colMap = [];
        if (is_array($header)) {
            foreach ($header as $i => $h) {
                $key = strtolower(trim((string)$h));
                $colMap[$key] = $i;
            }
        }

        $count = 0;
        $frRepo = $this->em->getRepository(\App\Entity\FuelRecord::class);

        while (($row = fgetcsv($handle)) !== false) {
            // expected columns: Date, Mileage, No. Litres, Cost
            // Support flexible columns by header name, fall back to positional
            $dateRaw = $row[$colMap['date'] ?? 0] ?? '';
            $mileageIdx = $colMap['mileage'] ?? ($colMap['mileage (km)'] ?? 1);
            $mileageRaw = $row[$mileageIdx] ?? '';
            $litresIdx = $colMap['no. litres'] ?? ($colMap['no litres'] ?? 2);
            $litresRaw = $row[$litresIdx] ?? '';
            $costRaw = $row[$colMap['cost'] ?? 3] ?? '';
            $fuelTypeRaw = null;
            if (isset($colMap['fueltype'])) {
                $fuelTypeRaw = $row[$colMap['fueltype']] ?? null;
            } elseif (isset($colMap['fuel type'])) {
                $fuelTypeRaw = $row[$colMap['fuel type']] ?? null;
            } elseif (isset($row[4])) {
                $fuelTypeRaw = $row[4] ?? null;
            }

            if (trim($dateRaw) === '') {
                continue;
            }

            try {
                $date = new \DateTime($dateRaw);
            } catch (\Exception $e) {
                $date = null;
            }

            $mileage = null;
            if (is_numeric($mileageRaw)) {
                // CSV mileage is in miles; convert to kilometres for DB
                $miles = (float)$mileageRaw;
                $km = (int) round($miles * 1.60934);
                $mileage = $km;
            }
            $litres = is_numeric($litresRaw) ? (float)$litresRaw : null;
            $cost = is_numeric($costRaw) ? (float)$costRaw : null;

            // basic duplicate check: vehicle + date + mileage
            $existing = null;
            if ($date && $mileage !== null) {
                $existing = $frRepo->findOneBy(
                    [
                        'vehicle' => $vehicle,
                        'date' => $date,
                        'mileage' => $mileage,
                    ]
                );
            }
            if ($existing) {
                continue;
            }

            $fuel = new \App\Entity\FuelRecord();
            $fuel->setVehicle($vehicle);
            if ($date) {
                $fuel->setDate($date);
            }
            if ($mileage !== null) {
                $fuel->setMileage($mileage);
            }
            if ($litres !== null) {
                $fuel->setLitres($litres);
            }
            if ($cost !== null) {
                $fuel->setCost($cost);
            }
            // Set fuel type: use provided value if present, otherwise default to E5
            $fuelType = null;
            if ($fuelTypeRaw !== null && trim((string)$fuelTypeRaw) !== '') {
                $fuelType = trim((string)$fuelTypeRaw);
            } else {
                $fuelType = 'E5';
            }
            $fuel->setFuelType($fuelType);

            $this->em->persist($fuel);
            $count++;

            if ($count % 50 === 0) {
                $this->em->flush();
                $this->em->clear();
                $vehicle = $repo->find($vehicle->getId());
            }
        }

        fclose($handle);
        $this->em->flush();

        $output->writeln('<info>Imported '.$count.' fuel records.</info>');
        return Command::SUCCESS;
    }
}
