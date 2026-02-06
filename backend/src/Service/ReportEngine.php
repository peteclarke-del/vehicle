<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Vehicle;
use App\Entity\FuelRecord;
use App\Entity\Part;
use App\Entity\Consumable;
use App\Entity\ServiceRecord;
use App\Entity\MotRecord;
use App\Entity\InsurancePolicy;
use App\Entity\RoadTax;
use App\Entity\Specification;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use App\Service\Trait\UnitConversionTrait;

/**
 * Generic Report Engine that interprets JSON templates and generates reports.
 *
 * Template JSON format supports:
 * - dataSources: Define where data comes from (entities, merged sources)
 * - calculations: Define computed values (sums, averages, derived fields)
 * - layout: Define the report structure (sections, grids, cells)
 * - styles: Define reusable styles (fonts, colors, borders)
 * - columnWidths: Define column widths
 */
class ReportEngine
{
    use UnitConversionTrait;

    private EntityManagerInterface $em;
    private array $template;
    private array $params;
    private array $dataCache = [];
    private array $calculatedValues = [];
    private string $pdfFontName = 'dejavusans';

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Get the user's preferred distance unit.
     * Wrapper for trait method to maintain backward compatibility.
     */
    private function getDistanceUnit(): string
    {
        return $this->getDistanceUnitPreference();
    }

    /**
     * Convert kilometers to the user's preferred distance unit.
     * Wrapper for trait method to maintain backward compatibility.
     */
    private function convertDistance(float $km): float
    {
        return $this->convertDistanceFromKm($km, 0);
    }

    /**
     * Generate a report from a JSON template.
     *
     * @param array $template The parsed JSON template
     * @param array $params Parameters like vehicle_id, from, to dates
     * @param string $format Output format: 'xlsx' or 'pdf'
     * @return array ['content' => string, 'mimeType' => string, 'filename' => string]
     */
    public function generate(array $template, array $params, string $format = 'xlsx'): array
    {
        $this->template = $template;
        $this->params = $params;
        $this->dataCache = [];
        $this->calculatedValues = [];

        // Set distance unit preference from params (uses trait)
        $this->setDistanceUnit($params['distanceUnit'] ?? 'miles');

        // Add unit-dependent labels to params for template variable resolution
        $this->params['distanceLabel'] = $this->getDistanceLabel();
        $this->params['economyLabel'] = $this->getFuelEconomyLabel();
        $this->params['distanceUnit'] = $this->getDistanceUnit();

        // 1. Load all data sources
        $this->loadDataSources();

        // 2. Perform calculations
        $this->performCalculations();

        // 3. Generate output based on format
        if ($format === 'pdf') {
            return $this->generatePdf();
        }

        return $this->generateXlsx();
    }

    /**
     * Load all data sources defined in the template.
     */
    private function loadDataSources(): void
    {
        $dataSources = $this->template['dataSources'] ?? [];

        foreach ($dataSources as $name => $config) {
            $this->dataCache[$name] = $this->fetchDataSource($name, $config);
        }

        // Also support legacy 'sheets' format with 'source' field
        $sheets = $this->template['sheets'] ?? [];
        foreach ($sheets as $sheet) {
            $source = $sheet['source'] ?? null;
            if ($source && !isset($this->dataCache[$source])) {
                $this->dataCache[$source] = $this->fetchDataSource($source, ['entity' => $source]);
            }
        }
    }

    /**
     * Fetch data for a single data source.
     */
    private function fetchDataSource(string $name, array $config): array
    {
        // Handle merged sources
        if (isset($config['merge']) && is_array($config['merge'])) {
            $merged = [];
            foreach ($config['merge'] as $sourceName) {
                $sourceConfig = $this->template['dataSources'][$sourceName] ?? ['entity' => $sourceName];
                $sourceData = $this->fetchDataSource($sourceName, $sourceConfig);
                foreach ($sourceData as $row) {
                    $row['_source'] = $sourceName;
                    $merged[] = $row;
                }
            }

            // Apply sorting if defined
            if (isset($config['sort'])) {
                $merged = $this->sortData($merged, $config['sort']);
            }

            return $merged;
        }

        // Handle entity sources
        $entity = $config['entity'] ?? $name;
        $rows = $this->fetchEntityData($entity, $config);

        // Apply transformations
        if (isset($config['fields']) && is_array($config['fields'])) {
            $rows = $this->transformFields($rows, $config['fields']);
        }

        // Apply sorting
        if (isset($config['sort'])) {
            $rows = $this->sortData($rows, $config['sort']);
        }

        return $rows;
    }

    /**
     * Fetch data from a Doctrine entity.
     */
    private function fetchEntityData(string $entity, array $config): array
    {
        $vehicleId = $this->params['vehicle_id'] ?? null;
        $rows = [];

        // Map entity names to classes
        $entityMap = [
            'vehicles' => Vehicle::class,
            'vehicle' => Vehicle::class,
            'Vehicle' => Vehicle::class,
            'fuelRecords' => FuelRecord::class,
            'FuelRecord' => FuelRecord::class,
            'parts' => Part::class,
            'Part' => Part::class,
            'consumables' => Consumable::class,
            'Consumable' => Consumable::class,
            'serviceRecords' => ServiceRecord::class,
            'ServiceRecord' => ServiceRecord::class,
            'motRecords' => MotRecord::class,
            'MotRecord' => MotRecord::class,
            'insurance' => Insurance::class,
            'Insurance' => Insurance::class,
            'roadTax' => RoadTax::class,
            'RoadTax' => RoadTax::class,
        ];

        $entityClass = $entityMap[$entity] ?? null;
        if (!$entityClass) {
            return [];
        }

        // Special handling for single vehicle
        if (($entity === 'vehicle' || $entity === 'vehicles') && ($config['single'] ?? false)) {
            if ($vehicleId) {
                $vehicle = $this->em->getRepository(Vehicle::class)->find($vehicleId);
                if ($vehicle) {
                    return [$this->vehicleToArray($vehicle)];
                }
            }
            return [];
        }

        // Build query based on entity type
        switch ($entity) {
            case 'fuelRecords':
            case 'FuelRecord':
                $qb = $this->em->createQueryBuilder()
                    ->select('fr', 'v')
                    ->from(FuelRecord::class, 'fr')
                    ->leftJoin('fr.vehicle', 'v')
                    ->orderBy('fr.date', 'ASC')
                    ->addOrderBy('fr.mileage', 'ASC');
                if ($vehicleId) {
                    $qb->where('fr.vehicle = :vid')->setParameter('vid', $vehicleId);
                }
                $results = $qb->getQuery()->getResult();
                
                // Calculate MPG for each record using previous record
                $previousRecord = null;
                $cumulativeLitres = 0;
                $cumulativeMiles = 0;
                
                foreach ($results as $fr) {
                    $vehicle = $fr->getVehicle();
                    $mpg = $fr->calculateMpg($previousRecord);
                    $miles = null;
                    
                    if ($previousRecord && $fr->getMileage() && $previousRecord->getMileage()) {
                        $miles = $fr->getMileage() - $previousRecord->getMileage();
                        $cumulativeMiles += $miles;
                    }
                    
                    $cumulativeLitres += (float)$fr->getLitres();
                    
                    $rows[] = [
                        'id' => $fr->getId(),
                        'date' => $fr->getDate() instanceof \DateTimeInterface ? $fr->getDate()->format('Y-m-d') : '',
                        'mileage' => $fr->getMileage(),
                        'litres' => $fr->getLitres(),
                        'cost' => $fr->getCost(),
                        'miles' => $miles,
                        'mpg' => $mpg,
                        'cumulativeLitres' => $cumulativeLitres,
                        'cumulativeMiles' => $cumulativeMiles,
                        'vehicle_id' => $vehicle?->getId(),
                        'vehicle' => $vehicle ? ($vehicle->getRegistrationNumber() ?? '') : '',
                        'registration' => $vehicle ? ($vehicle->getRegistrationNumber() ?? '') : '',
                    ];
                    
                    $previousRecord = $fr;
                }
                break;

            case 'parts':
            case 'Part':
                $qb = $this->em->createQueryBuilder()
                    ->select('p', 'v')
                    ->from(Part::class, 'p')
                    ->leftJoin('p.vehicle', 'v');
                if ($vehicleId) {
                    $qb->where('p.vehicle = :vid')->setParameter('vid', $vehicleId);
                }
                $results = $qb->getQuery()->getResult();
                foreach ($results as $part) {
                    $vehicle = $part->getVehicle();
                    $rows[] = [
                        'id' => $part->getId(),
                        'date' => $part->getPurchaseDate() instanceof \DateTimeInterface ? $part->getPurchaseDate()->format('Y-m-d') : '',
                        'item' => $part->getDescription() ?? '',
                        'cost' => $part->getTotalCost() ?? $part->getCost(),
                        'price' => $part->getPrice(),
                        'quantity' => $part->getQuantity(),
                        'vehicle_id' => $vehicle?->getId(),
                        'vehicle' => $vehicle ? ($vehicle->getRegistrationNumber() ?? '') : '',
                        'registration' => $vehicle ? ($vehicle->getRegistrationNumber() ?? '') : '',
                    ];
                }
                break;

            case 'consumables':
            case 'Consumable':
                $qb = $this->em->createQueryBuilder()
                    ->select('c')
                    ->from(Consumable::class, 'c')
                    ->leftJoin('c.vehicle', 'v');
                if ($vehicleId) {
                    $qb->where('c.vehicle = :vid')->setParameter('vid', $vehicleId);
                }
                $consumables = $qb->getQuery()->getResult();
                
                // Get filter from config
                $filter = $config['filter'] ?? [];
                
                foreach ($consumables as $c) {
                    // Apply "due" filter - consumables that need replacing soon based on mileage or date
                    if (isset($filter['due']) && $filter['due']) {
                        $isDue = false;
                        $vehicle = $c->getVehicle();
                        $currentMileage = $vehicle ? $vehicle->getMileage() : null;
                        $nextReplacement = $c->getNextReplacement(); // mileage at which replacement is due
                        
                        // Check if due based on mileage
                        if ($currentMileage !== null && $nextReplacement !== null && $currentMileage >= ($nextReplacement - 1000)) {
                            $isDue = true;
                        }
                        
                        if (!$isDue) {
                            continue;
                        }
                    }
                    
                    $rows[] = [
                        'id' => $c->getId(),
                        'date' => $c->getLastChanged() instanceof \DateTimeInterface ? $c->getLastChanged()->format('Y-m-d') : '',
                        'item' => $c->getDescription() ?? '',
                        'cost' => $c->getTotalCost() ?? $c->getCost(),
                        'unitCost' => $c->getCost(),
                        'quantity' => $c->getQuantity(),
                        'vehicle_id' => $c->getVehicle()?->getId(),
                        'registration' => $c->getVehicle()?->getRegistration(),
                        'make' => $c->getVehicle()?->getMake(),
                        'model' => $c->getVehicle()?->getModel(),
                        'currentMileage' => $c->getVehicle()?->getMileage(),
                        'nextReplacement' => $c->getNextReplacement(),
                    ];
                }
                break;

            case 'serviceRecords':
            case 'ServiceRecord':
                $qb = $this->em->createQueryBuilder()
                    ->select('sr', 'v')
                    ->from(ServiceRecord::class, 'sr')
                    ->leftJoin('sr.vehicle', 'v');
                if ($vehicleId) {
                    $qb->where('sr.vehicle = :vid')->setParameter('vid', $vehicleId);
                }
                $results = $qb->getQuery()->getResult();
                foreach ($results as $sr) {
                    // Calculate total cost from labor + parts + consumables
                    $totalCost = ((float)($sr->getLaborCost() ?? 0)) 
                        + ((float)($sr->getPartsCost() ?? 0)) 
                        + ((float)($sr->getConsumablesCost() ?? 0));
                    $vehicle = $sr->getVehicle();
                    $rows[] = [
                        'id' => $sr->getId(),
                        'date' => $sr->getServiceDate() instanceof \DateTimeInterface ? $sr->getServiceDate()->format('Y-m-d') : '',
                        'item' => $sr->getServiceType() ?? '',
                        'serviceType' => $sr->getServiceType() ?? '',
                        'cost' => $totalCost,
                        'vehicle_id' => $vehicle?->getId(),
                        'vehicle' => $vehicle ? ($vehicle->getRegistrationNumber() ?? '') : '',
                        'registration' => $vehicle ? ($vehicle->getRegistrationNumber() ?? '') : '',
                    ];
                }
                break;

            case 'motRecords':
            case 'MotRecord':
                $qb = $this->em->createQueryBuilder()
                    ->select('m.id, m.testDate, m.result, m.testCost, m.repairCost, IDENTITY(m.vehicle) as vehicle_id')
                    ->from(MotRecord::class, 'm');
                if ($vehicleId) {
                    $qb->where('m.vehicle = :vid')->setParameter('vid', $vehicleId);
                }
                $results = $qb->getQuery()->getArrayResult();
                foreach ($results as $row) {
                    $totalCost = ((float)($row['testCost'] ?? 0)) + ((float)($row['repairCost'] ?? 0));
                    // Skip MOT records with no cost (historical records prior to ownership)
                    if ($totalCost <= 0) {
                        continue;
                    }
                    $rows[] = [
                        'id' => $row['id'],
                        'date' => $row['testDate'] instanceof \DateTimeInterface ? $row['testDate']->format('Y-m-d') : ($row['testDate'] ?? ''),
                        'item' => 'M.O.T. (' . ($row['result'] ?? '') . ')',
                        'cost' => $totalCost,
                        'vehicle_id' => $row['vehicle_id'],
                    ];
                }
                break;

            case 'vehicles':
            case 'Vehicle':
                $qb = $this->em->createQueryBuilder()
                    ->select('v')
                    ->from(Vehicle::class, 'v');
                if ($vehicleId) {
                    $qb->where('v.id = :vid')->setParameter('vid', $vehicleId);
                }
                $vehicles = $qb->getQuery()->getResult();
                
                // Get filter from config
                $filter = $config['filter'] ?? [];
                $today = new \DateTime();
                $periodDays = $this->params['period']['days'] ?? null;
                $periodEnd = $periodDays !== null ? (clone $today)->modify("+{$periodDays} days") : null;
                
                foreach ($vehicles as $v) {
                    $vehicleData = $this->vehicleToArray($v);
                    
                    // Apply filters
                    if (!empty($filter)) {
                        $include = true;
                        
                        // Insurance expired filter
                        if (isset($filter['insuranceExpired']) && $filter['insuranceExpired']) {
                            $insuranceDate = $vehicleData['insuranceDue'] ? new \DateTime($vehicleData['insuranceDue']) : null;
                            $include = $include && ($insuranceDate === null || $insuranceDate < $today);
                        }
                        
                        // Insurance due filter (within period)
                        if (isset($filter['insuranceDue']) && $filter['insuranceDue']) {
                            $insuranceDate = $vehicleData['insuranceDue'] ? new \DateTime($vehicleData['insuranceDue']) : null;
                            $include = $include && ($insuranceDate !== null && $insuranceDate >= $today && ($periodEnd === null || $insuranceDate <= $periodEnd));
                        }
                        
                        // MOT expired filter
                        if (isset($filter['motExpired']) && $filter['motExpired']) {
                            $motDate = $vehicleData['motDue'] ? new \DateTime($vehicleData['motDue']) : null;
                            $include = $include && ($motDate === null || $motDate < $today);
                        }
                        
                        // MOT due filter (within period)
                        if (isset($filter['motDue']) && $filter['motDue']) {
                            $motDate = $vehicleData['motDue'] ? new \DateTime($vehicleData['motDue']) : null;
                            $include = $include && ($motDate !== null && $motDate >= $today && ($periodEnd === null || $motDate <= $periodEnd));
                        }
                        
                        // Road tax expired filter
                        if (isset($filter['taxExpired']) && $filter['taxExpired']) {
                            $taxDate = $vehicleData['roadTaxDue'] ? new \DateTime($vehicleData['roadTaxDue']) : null;
                            $include = $include && ($taxDate === null || $taxDate < $today);
                        }
                        
                        // Road tax due filter (within period)
                        if (isset($filter['taxDue']) && $filter['taxDue']) {
                            $taxDate = $vehicleData['roadTaxDue'] ? new \DateTime($vehicleData['roadTaxDue']) : null;
                            $include = $include && ($taxDate !== null && $taxDate >= $today && ($periodEnd === null || $taxDate <= $periodEnd));
                        }
                        
                        // Service due filter (within period)
                        if (isset($filter['serviceDue']) && $filter['serviceDue']) {
                            // Check if last service + service interval is within period
                            $lastServiceDate = $vehicleData['lastService'] ? new \DateTime($vehicleData['lastService']) : null;
                            $serviceIntervalMonths = $v->getServiceIntervalMonths() ?? 12;
                            $nextServiceDate = $lastServiceDate ? (clone $lastServiceDate)->modify("+{$serviceIntervalMonths} months") : null;
                            $include = $include && ($nextServiceDate !== null && ($periodEnd === null || $nextServiceDate <= $periodEnd));
                        }
                        
                        if (!$include) {
                            continue;
                        }
                    }
                    
                    $rows[] = $vehicleData;
                }
                break;
        }

        return $rows;
    }

    /**
     * Convert a Vehicle entity to an array.
     * Note: purchaseMileage is stored in KM and converted to user's preferred unit.
     * Fetches related data from InsurancePolicy, MotRecord, RoadTax, ServiceRecord, and Specification entities.
     */
    private function vehicleToArray(Vehicle $v): array
    {
        $purchaseMileageKm = $v->getPurchaseMileage();
        $purchaseMileageDisplay = $purchaseMileageKm !== null 
            ? $this->convertDistance((float)$purchaseMileageKm) 
            : null;
        
        // Get the latest insurance policy expiry date
        $insuranceDue = '';
        $insurancePolicies = $v->getInsurancePolicies();
        if (!$insurancePolicies->isEmpty()) {
            // Find the policy with the latest expiry date
            $latestExpiry = null;
            foreach ($insurancePolicies as $policy) {
                $expiry = $policy->getExpiryDate();
                if ($expiry !== null && ($latestExpiry === null || $expiry > $latestExpiry)) {
                    $latestExpiry = $expiry;
                }
            }
            if ($latestExpiry !== null) {
                $insuranceDue = $latestExpiry->format('Y-m-d');
            }
        }
        
        // Get the latest MOT expiry date
        $motDue = '';
        $motRecords = $v->getMotRecords();
        if (!$motRecords->isEmpty()) {
            $latestExpiry = null;
            foreach ($motRecords as $record) {
                $expiry = $record->getExpiryDate();
                if ($expiry !== null && ($latestExpiry === null || $expiry > $latestExpiry)) {
                    $latestExpiry = $expiry;
                }
            }
            if ($latestExpiry !== null) {
                $motDue = $latestExpiry->format('Y-m-d');
            }
        }
        
        // Get the latest road tax expiry date
        $roadTaxDue = '';
        $roadTaxRecords = $v->getRoadTaxRecords();
        if (!$roadTaxRecords->isEmpty()) {
            $latestExpiry = null;
            foreach ($roadTaxRecords as $record) {
                $expiry = $record->getExpiryDate();
                if ($expiry !== null && ($latestExpiry === null || $expiry > $latestExpiry)) {
                    $latestExpiry = $expiry;
                }
            }
            if ($latestExpiry !== null) {
                $roadTaxDue = $latestExpiry->format('Y-m-d');
            }
        }
        
        // Get the latest service date
        $lastService = '';
        $serviceRecords = $v->getServiceRecords();
        if (!$serviceRecords->isEmpty()) {
            $latestDate = null;
            foreach ($serviceRecords as $record) {
                $date = $record->getServiceDate();
                if ($date !== null && ($latestDate === null || $date > $latestDate)) {
                    $latestDate = $date;
                }
            }
            if ($latestDate !== null) {
                $lastService = $latestDate->format('Y-m-d');
            }
        }
        
        // Get specification data (tyres, oil, fuel capacity)
        $frontTyreSize = '';
        $frontTyrePressure = '';
        $rearTyreSize = '';
        $rearTyrePressure = '';
        $oilCapacity = '';
        $oilType = '';
        $fuelCapacity = '';
        
        $spec = $this->em->getRepository(Specification::class)->findOneBy(['vehicle' => $v]);
        if ($spec !== null) {
            $frontTyreSize = $spec->getFrontTyre() ?? '';
            $frontTyrePressure = $spec->getFrontTyrePressure() ?? '';
            $rearTyreSize = $spec->getRearTyre() ?? '';
            $rearTyrePressure = $spec->getRearTyrePressure() ?? '';
            $oilCapacity = $spec->getEngineOilCapacity() ?? '';
            $oilType = $spec->getEngineOilType() ?? '';
            $fuelCapacity = $spec->getFuelCapacity() ?? '';
        }
            
        return [
            'id' => $v->getId(),
            'registration' => $v->getRegistrationNumber() ?? '',
            'registrationNumber' => $v->getRegistrationNumber() ?? '',
            'make' => $v->getMake() ?? '',
            'model' => $v->getModel() ?? '',
            'year' => $v->getYear(),
            'vin' => $v->getVin() ?? '',
            'engineNumber' => $v->getEngineNumber() ?? '',
            'v5cReference' => $v->getV5DocumentNumber() ?? '',
            'purchaseDate' => $v->getPurchaseDate() ? $v->getPurchaseDate()->format('Y-m-d') : '',
            'purchaseCost' => $v->getPurchaseCost(),
            'purchaseMileage' => $purchaseMileageDisplay,
            'currentMileage' => $v->getMileage(),
            'mileage' => $v->getMileage(),
            'name' => trim(($v->getMake() ?? '') . ' ' . ($v->getModel() ?? '')),
            'vehicleColor' => $v->getVehicleColor() ?? '',
            // Data from related entities
            'insuranceDue' => $insuranceDue,
            'motDue' => $motDue,
            'roadTaxDue' => $roadTaxDue,
            'lastService' => $lastService,
            'frontTyreSize' => $frontTyreSize,
            'frontTyrePressure' => $frontTyrePressure,
            'rearTyreSize' => $rearTyreSize,
            'rearTyrePressure' => $rearTyrePressure,
            'oilCapacity' => $oilCapacity,
            'oilType' => $oilType,
            'fuelCapacity' => $fuelCapacity,
        ];
    }

    /**
     * Transform fields according to mapping.
     */
    private function transformFields(array $rows, array $fieldMap): array
    {
        $result = [];
        foreach ($rows as $row) {
            $newRow = [];
            foreach ($fieldMap as $newKey => $oldKey) {
                $newRow[$newKey] = $row[$oldKey] ?? ($row[$newKey] ?? null);
            }
            // Also keep original fields not in the map
            foreach ($row as $k => $v) {
                if (!isset($newRow[$k])) {
                    $newRow[$k] = $v;
                }
            }
            $result[] = $newRow;
        }
        return $result;
    }

    /**
     * Sort data by field.
     */
    private function sortData(array $data, array $sortConfig): array
    {
        $field = $sortConfig['field'] ?? 'date';
        $order = strtolower($sortConfig['order'] ?? 'asc');

        usort($data, function ($a, $b) use ($field, $order) {
            $av = $a[$field] ?? '';
            $bv = $b[$field] ?? '';

            if (is_numeric($av) && is_numeric($bv)) {
                $cmp = (float)$av <=> (float)$bv;
            } else {
                $cmp = strcmp((string)$av, (string)$bv);
            }

            return $order === 'desc' ? -$cmp : $cmp;
        });

        return $data;
    }

    /**
     * Perform all calculations defined in the template.
     */
    private function performCalculations(): void
    {
        $calculations = $this->template['calculations'] ?? [];

        foreach ($calculations as $name => $config) {
            $this->calculatedValues[$name] = $this->calculateValue($config);
        }

        // Also compute derived fields for fuel records (MPG)
        if (isset($this->dataCache['fuelRecords'])) {
            $this->dataCache['fuelRecords'] = $this->computeMpg($this->dataCache['fuelRecords']);
        }
    }

    /**
     * Calculate a single value.
     */
    private function calculateValue(array $config): mixed
    {
        $type = $config['type'] ?? 'sum';
        $source = $config['source'] ?? '';
        $field = $config['field'] ?? 'cost';

        $data = $this->dataCache[$source] ?? [];

        switch ($type) {
            case 'sum':
                return array_sum(array_map(fn($r) => (float)($r[$field] ?? 0), $data));

            case 'avg':
            case 'average':
                // Filter out null/zero values for average calculation (important for MPG where first record has no value)
                $values = array_filter(
                    array_map(fn($r) => $r[$field] ?? null, $data),
                    fn($v) => $v !== null && $v > 0
                );
                $values = array_map(fn($v) => (float)$v, $values);
                return count($values) > 0 ? array_sum($values) / count($values) : 0;

            case 'count':
                return count($data);

            case 'min':
                $values = array_map(fn($r) => (float)($r[$field] ?? 0), $data);
                return count($values) > 0 ? min($values) : 0;

            case 'max':
                $values = array_map(fn($r) => (float)($r[$field] ?? 0), $data);
                return count($values) > 0 ? max($values) : 0;

            case 'first':
                return $data[0][$field] ?? null;

            case 'last':
                return $data[count($data) - 1][$field] ?? null;

            default:
                return null;
        }
    }

    /**
     * Compute fuel economy for fuel records.
     * 
     * Mileage is stored in the database in KILOMETERS.
     * We convert to the user's preferred unit (miles or km) for display
     * and calculate fuel economy accordingly (MPG for miles, km/l for km).
     */
    private function computeMpg(array $fuelRecords): array
    {
        // Sort by date first
        usort($fuelRecords, fn($a, $b) => strcmp($a['date'] ?? '', $b['date'] ?? ''));

        $prevMileageKm = null;
        $cumulativeLitres = 0;
        $cumulativeDistance = 0; // In user's preferred unit

        foreach ($fuelRecords as $i => $row) {
            $economy = '';
            $distance = 0; // Distance in user's preferred unit

            // Get the original mileage in km from the row
            $mileageInKm = (float)($row['mileage'] ?? 0);
            
            // Convert the mileage reading from km to user's unit for display
            $fuelRecords[$i]['mileage'] = $this->convertDistanceFromKm($mileageInKm);

            if ($prevMileageKm !== null && is_numeric($row['litres']) && (float)$row['litres'] > 0) {
                // Calculate distance traveled in kilometers
                $distanceKm = $mileageInKm - $prevMileageKm;
                
                if ($distanceKm > 0) {
                    // Convert to user's preferred unit using trait method
                    $distance = (int)$this->convertDistanceFromKm($distanceKm);
                    
                    // Calculate fuel economy using trait method
                    $economy = $this->calculateFuelEconomy($distanceKm, (float)$row['litres']);
                }
            }

            $cumulativeLitres += (float)($row['litres'] ?? 0);
            $cumulativeDistance += $distance;

            // These field names remain the same but now contain converted values
            $fuelRecords[$i]['mpg'] = $economy; // MPG or km/l depending on unit
            $fuelRecords[$i]['miles'] = $distance; // Distance in user's unit (miles or km)
            $fuelRecords[$i]['cumulativeLitres'] = round($cumulativeLitres, 2);
            $fuelRecords[$i]['cumulativeMiles'] = round($cumulativeDistance, 0); // Cumulative in user's unit

            // Remember this record's km value for next iteration
            $prevMileageKm = $mileageInKm;
        }

        return $fuelRecords;
    }

    /**
     * Get data for a source, resolving references.
     */
    private function getData(string $source): array
    {
        return $this->dataCache[$source] ?? [];
    }

    /**
     * Get a calculated value.
     */
    private function getCalculation(string $name): mixed
    {
        return $this->calculatedValues[$name] ?? null;
    }

    /**
     * Resolve a template variable like {{vehicle.name}} or {{totalCosts}}.
     */
    private function resolveVariable(string $expr): mixed
    {
        // Remove {{ and }}
        $expr = trim($expr, '{}');
        $expr = trim($expr);

        // Check if it's a calculation reference
        if (isset($this->calculatedValues[$expr])) {
            return $this->calculatedValues[$expr];
        }

        // Check if it's a data source reference with field (e.g., vehicle.name)
        if (str_contains($expr, '.')) {
            [$source, $field] = explode('.', $expr, 2);
            $data = $this->dataCache[$source] ?? [];
            if (!empty($data) && isset($data[0][$field])) {
                return $data[0][$field];
            }
        }

        // Check params
        if (isset($this->params[$expr])) {
            return $this->params[$expr];
        }

        return $expr;
    }

    /**
     * Resolve all variables in a string.
     */
    private function resolveString(string $str): string
    {
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) {
            $val = $this->resolveVariable($matches[1]);
            return is_scalar($val) ? (string)$val : '';
        }, $str);
    }

    /**
     * Generate XLSX output.
     */
    private function generateXlsx(): array
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Liberation Serif')->setSize(9);

        // Get layout configuration
        $layout = $this->template['layout'] ?? null;
        $sheets = $this->template['sheets'] ?? [];
        $styles = $this->template['styles'] ?? [];
        $columnWidths = $this->template['columnWidths'] ?? [];

        // If we have a custom layout, use it
        if ($layout && isset($layout['sections'])) {
            $this->renderLayoutToXlsx($spreadsheet, $layout, $styles, $columnWidths);
        } elseif (!empty($sheets)) {
            // Legacy sheets format
            $this->renderSheetsToXlsx($spreadsheet, $sheets, $styles);
        }

        // Write to temp file
        $tmpFile = tempnam(sys_get_temp_dir(), 'report_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->save($tmpFile);

        $content = file_get_contents($tmpFile);
        @unlink($tmpFile);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        // Build filename
        $filename = $this->resolveString($this->template['filename'] ?? ($this->template['name'] ?? 'report'));
        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filename);

        return [
            'content' => $content,
            'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'filename' => $filename . '.xlsx',
        ];
    }

    /**
     * Render a custom layout to XLSX.
     */
    private function renderLayoutToXlsx(Spreadsheet $spreadsheet, array $layout, array $styles, array $columnWidths): void
    {
        $sheet = $spreadsheet->getActiveSheet();

        // Set sheet name
        $sheetName = $this->resolveString($layout['name'] ?? 'Report');
        $sheet->setTitle(substr($sheetName, 0, 31));

        // Set column widths
        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth((float)$width);
        }

        // Track current row for data grids
        $currentRow = 1;

        // Process each section
        foreach ($layout['sections'] as $section) {
            $currentRow = $this->renderSection($sheet, $section, $styles, $currentRow);
        }
    }

    /**
     * Render a section to the sheet.
     */
    private function renderSection($sheet, array $section, array $styles, int $currentRow): int
    {
        $type = $section['type'] ?? 'cells';
        // Use startRow from section if specified, otherwise use currentRow
        $row = $section['startRow'] ?? $section['row'] ?? $currentRow;

        switch ($type) {
            case 'header':
            case 'cells':
            case 'columnHeaders':
                $this->renderCells($sheet, $section['cells'] ?? [], $row, $styles);
                return $row + 1;

            case 'dataGrid':
                $endRow = $this->renderDataGrid($sheet, $section, $styles, $row);
                // Only advance currentRow if this grid didn't specify startRow (side-by-side grids don't advance)
                return isset($section['startRow']) ? $currentRow : $endRow;

            case 'totals':
                $this->renderTotals($sheet, $section, $styles, $row);
                return $row + 1;

            case 'vehicleDetails':
                $endRow = $this->renderVehicleDetails($sheet, $section, $styles, $row);
                // Only advance currentRow if this section didn't specify startRow
                return isset($section['startRow']) ? $currentRow : $endRow;

            default:
                return $currentRow;
        }
    }

    /**
     * Render static cells.
     */
    private function renderCells($sheet, array $cells, int $row, array $styles): void
    {
        foreach ($cells as $cell) {
            $col = $cell['col'] ?? 'A';
            $value = $this->resolveString($cell['value'] ?? '');
            $coord = $col . $row;

            $sheet->setCellValue($coord, $value);

            // Handle merge
            if (isset($cell['merge'])) {
                $sheet->mergeCells($cell['merge']);
            }

            // Apply style
            if (isset($cell['style']) && isset($styles[$cell['style']])) {
                $this->applyStyle($sheet, $coord, $styles[$cell['style']]);
            }
        }
    }

    /**
     * Render a data grid.
     */
    private function renderDataGrid($sheet, array $section, array $styles, int $startRow): int
    {
        $source = $section['source'] ?? '';
        $data = $this->getData($source);
        $columns = $section['columns'] ?? [];
        $maxRows = $section['maxRows'] ?? 1000;
        $overflow = $section['overflow'] ?? null;

        $style = $section['style'] ?? null;
        $styleArr = $style && isset($styles[$style]) ? $styles[$style] : [];

        $row = $startRow;
        $rowsWritten = 0;

        // Split data for main grid and overflow
        $mainData = array_slice($data, 0, $maxRows);
        $overflowData = $overflow ? array_slice($data, $maxRows) : [];

        foreach ($mainData as $i => $dataRow) {
            // Write main columns
            foreach ($columns as $colDef) {
                $col = $colDef['col'] ?? 'A';
                $field = $colDef['field'] ?? '';
                $value = $dataRow[$field] ?? '';
                $coord = $col . $row;

                $this->writeCell($sheet, $coord, $value, $colDef, $styles);
            }

            // Write overflow columns if this row has overflow data
            if ($overflow && isset($overflowData[$i])) {
                foreach ($overflow['columns'] as $colDef) {
                    $col = $colDef['col'] ?? 'A';
                    $field = $colDef['field'] ?? '';
                    $value = $overflowData[$i][$field] ?? '';
                    $coord = $col . $row;

                    $this->writeCell($sheet, $coord, $value, $colDef, $styles);
                }
            }

            $row++;
            $rowsWritten++;
        }

        // Handle remaining overflow data
        if ($overflow && count($overflowData) > count($mainData)) {
            for ($i = count($mainData); $i < count($overflowData); $i++) {
                foreach ($overflow['columns'] as $colDef) {
                    $col = $colDef['col'] ?? 'A';
                    $field = $colDef['field'] ?? '';
                    $value = $overflowData[$i][$field] ?? '';
                    $coord = $col . $row;

                    $this->writeCell($sheet, $coord, $value, $colDef, $styles);
                }
                $row++;
            }
        }

        return $row;
    }

    /**
     * Write a cell with formatting.
     */
    private function writeCell($sheet, string $coord, mixed $value, array $colDef, array $styles): void
    {
        $format = $colDef['format'] ?? null;

        if ($format === 'currency') {
            $num = is_numeric($value) ? (float)$value : 0;
            $sheet->setCellValue($coord, $num);
            $sheet->getStyle($coord)->getNumberFormat()->setFormatCode('£#,##0.00');
        } elseif ($format === 'number') {
            $sheet->setCellValue($coord, is_numeric($value) ? round((float)$value) : 0);
            $sheet->getStyle($coord)->getNumberFormat()->setFormatCode('#,##0');
        } else {
            $sheet->setCellValue($coord, (string)$value);
        }

        // Apply style
        if (isset($colDef['style']) && isset($styles[$colDef['style']])) {
            $this->applyStyle($sheet, $coord, $styles[$colDef['style']]);
        }
    }

    /**
     * Render totals row.
     */
    private function renderTotals($sheet, array $section, array $styles, int $row): void
    {
        $cells = $section['cells'] ?? [];

        foreach ($cells as $cell) {
            $col = $cell['col'] ?? 'A';
            $coord = $col . $row;

            // Resolve value - could be a calculation reference
            $value = $cell['value'] ?? '';
            if (is_string($value) && str_starts_with($value, '{{')) {
                $value = $this->resolveVariable($value);
            }

            $label = $cell['label'] ?? null;
            if ($label) {
                $sheet->setCellValue($coord, $label);
            } else {
                $format = $cell['format'] ?? null;
                if ($format === 'currency' && is_numeric($value)) {
                    $sheet->setCellValue($coord, (float)$value);
                    $sheet->getStyle($coord)->getNumberFormat()->setFormatCode('£#,##0.00');
                } else {
                    $sheet->setCellValue($coord, $value);
                }
            }

            // Apply style
            if (isset($cell['style']) && isset($styles[$cell['style']])) {
                $this->applyStyle($sheet, $coord, $styles[$cell['style']]);
            }
        }
    }

    /**
     * Render vehicle details section.
     */
    private function renderVehicleDetails($sheet, array $section, array $styles, int $startRow): int
    {
        $source = $section['source'] ?? 'vehicle';
        $data = $this->getData($source);
        $vehicle = $data[0] ?? [];
        $fields = $section['fields'] ?? [];
        $labelCol = $section['labelCol'] ?? 'U';
        $valueCol = $section['valueCol'] ?? 'V';
        $style = $section['style'] ?? null;
        $valueStyle = $section['valueStyle'] ?? $style;

        $row = $startRow;
        foreach ($fields as $fieldDef) {
            $label = $fieldDef['label'] ?? '';
            $field = $fieldDef['field'] ?? '';
            $value = $vehicle[$field] ?? '';

            // Handle special field types
            if (isset($fieldDef['calculation'])) {
                $value = $this->getCalculation($fieldDef['calculation']);
                if (isset($fieldDef['format']) && $fieldDef['format'] === 'currency') {
                    $value = '£' . number_format((float)$value, 2);
                }
            }

            $sheet->setCellValue($labelCol . $row, $label);
            $sheet->setCellValue($valueCol . $row, $value);

            if ($style && isset($styles[$style])) {
                $this->applyStyle($sheet, $labelCol . $row, $styles[$style]);
            }
            if ($valueStyle && isset($styles[$valueStyle])) {
                $this->applyStyle($sheet, $valueCol . $row, $styles[$valueStyle]);
            }

            $row++;
        }

        return $row;
    }

    /**
     * Render legacy sheets format to XLSX.
     */
    private function renderSheetsToXlsx(Spreadsheet $spreadsheet, array $sheets, array $styles): void
    {
        $sheetIndex = 0;

        foreach ($sheets as $sheetDef) {
            $xlsSheet = ($sheetIndex === 0) ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $title = $sheetDef['name'] ?? ('Sheet' . ($sheetIndex + 1));
            $xlsSheet->setTitle(substr($title, 0, 31));

            $source = $sheetDef['source'] ?? '';
            $columns = $sheetDef['columns'] ?? [];
            $data = $this->getData($source);

            // Apply sorting if defined
            if (isset($sheetDef['sort'])) {
                $data = $this->sortData($data, $sheetDef['sort']);
            }

            // Write headers
            $colNum = 1;
            foreach ($columns as $col) {
                $colLetter = Coordinate::stringFromColumnIndex($colNum);
                $xlsSheet->setCellValue($colLetter . '1', $col['label'] ?? ($col['key'] ?? ''));
                if (isset($col['width'])) {
                    $xlsSheet->getColumnDimension($colLetter)->setWidth((float)$col['width']);
                }
                $colNum++;
            }

            // Header style
            if (count($columns) > 0) {
                $lastCol = Coordinate::stringFromColumnIndex(count($columns));
                $xlsSheet->getStyle('A1:' . $lastCol . '1')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B2B2B2']],
                ]);
            }

            // Write data rows
            $rowNum = 2;
            $aggregates = [];
            foreach ($columns as $ci => $col) {
                if (isset($col['aggregate'])) {
                    $aggregates[$ci] = ['type' => $col['aggregate'], 'values' => [], 'format' => $col['format'] ?? null];
                }
            }

            foreach ($data as $dataRow) {
                $colNum = 1;
                foreach ($columns as $ci => $col) {
                    $colLetter = Coordinate::stringFromColumnIndex($colNum);
                    $key = $col['key'] ?? $col['field'] ?? '';
                    $value = $dataRow[$key] ?? '';
                    $coord = $colLetter . $rowNum;

                    // Handle derived fields
                    if (isset($col['derived']) && $col['derived'] === 'mpg') {
                        $value = $dataRow['mpg'] ?? '';
                    }

                    $format = $col['format'] ?? null;
                    if ($format === 'currency') {
                        $num = is_numeric($value) ? (float)$value : 0;
                        $xlsSheet->setCellValue($coord, $num);
                        $xlsSheet->getStyle($coord)->getNumberFormat()->setFormatCode('£#,##0.00');
                        if (isset($aggregates[$ci])) {
                            $aggregates[$ci]['values'][] = $num;
                        }
                    } else {
                        $xlsSheet->setCellValue($coord, (string)$value);
                        if (isset($aggregates[$ci]) && is_numeric($value)) {
                            $aggregates[$ci]['values'][] = (float)$value;
                        }
                    }

                    // Alignment
                    if (isset($col['alignment'])) {
                        $align = strtolower($col['alignment']);
                        $xlsSheet->getStyle($coord)->getAlignment()->setHorizontal(
                            $align === 'right' ? Alignment::HORIZONTAL_RIGHT :
                            ($align === 'center' ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_LEFT)
                        );
                    }

                    $colNum++;
                }
                $rowNum++;
            }

            // Write aggregates row if any
            if (!empty($aggregates)) {
                $xlsSheet->setCellValue('A' . $rowNum, 'Total');
                foreach ($aggregates as $ci => $agg) {
                    $colLetter = Coordinate::stringFromColumnIndex($ci + 1);
                    $result = match($agg['type']) {
                        'sum' => array_sum($agg['values']),
                        'avg' => count($agg['values']) > 0 ? array_sum($agg['values']) / count($agg['values']) : 0,
                        'count' => count($agg['values']),
                        default => '',
                    };
                    if (is_numeric($result)) {
                        $xlsSheet->setCellValue($colLetter . $rowNum, $result);
                        if (($agg['format'] ?? null) === 'currency') {
                            $xlsSheet->getStyle($colLetter . $rowNum)->getNumberFormat()->setFormatCode('£#,##0.00');
                        } else {
                            $xlsSheet->getStyle($colLetter . $rowNum)->getNumberFormat()->setFormatCode('#,##0');
                        }
                    }
                }
                $xlsSheet->getStyle('A' . $rowNum . ':' . Coordinate::stringFromColumnIndex(count($columns)) . $rowNum)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B2B2B2']],
                ]);
            }

            $sheetIndex++;
        }
    }

    /**
     * Apply a style definition to a cell.
     */
    private function applyStyle($sheet, string $coord, array $style): void
    {
        $styleArray = [];

        if (isset($style['font'])) {
            $styleArray['font'] = [];
            if (isset($style['font']['bold'])) {
                $styleArray['font']['bold'] = $style['font']['bold'];
            }
            if (isset($style['font']['size'])) {
                $styleArray['font']['size'] = $style['font']['size'];
            }
            if (isset($style['font']['color'])) {
                $styleArray['font']['color'] = ['rgb' => ltrim($style['font']['color'], '#')];
            }
        }

        if (isset($style['fill'])) {
            $color = $style['fill']['color'] ?? $style['fill'];
            $styleArray['fill'] = [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => ltrim($color, '#')],
            ];
        }

        if (isset($style['border'])) {
            $borderStyle = $style['border'] === 'thin' ? Border::BORDER_THIN : Border::BORDER_MEDIUM;
            $styleArray['borders'] = [
                'allBorders' => ['borderStyle' => $borderStyle],
            ];
        }

        if (isset($style['alignment'])) {
            $align = strtolower($style['alignment']);
            $styleArray['alignment'] = [
                'horizontal' => $align === 'right' ? Alignment::HORIZONTAL_RIGHT :
                    ($align === 'center' ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_LEFT),
            ];
        }

        if (isset($style['wrapText']) && $style['wrapText']) {
            if (!isset($styleArray['alignment'])) {
                $styleArray['alignment'] = [];
            }
            $styleArray['alignment']['wrapText'] = true;
        }

        if (!empty($styleArray)) {
            $sheet->getStyle($coord)->applyFromArray($styleArray);
        }
    }

    /**
     * Generate PDF output using TCPDF with Liberation Serif font.
     */
    private function generatePdf(): array
    {
        // Get orientation from template pageSetup, default to landscape to match XLSX
        $pageSetup = $this->template['pageSetup'] ?? [];
        $orientation = strtolower($pageSetup['orientation'] ?? 'landscape');
        $pdfOrientation = $orientation === 'portrait' ? 'P' : 'L';
        
        // Use TCPDF for better font support
        $pdf = new \TCPDF($pdfOrientation, 'mm', 'A4', true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('Vehicle Management System');
        $pdf->SetAuthor('Vehicle Management System');
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Set margins
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 10);
        
        // Use DejaVu Sans - built into TCPDF with full UTF-8 support
        $this->pdfFontName = 'dejavusans';
        
        $pdf->SetFont($this->pdfFontName, 'B', 14);
        
        $pdf->AddPage();

        // Title - use 'title' field if present (for dynamic titles), otherwise fall back to 'name'
        $title = $this->resolveString($this->template['title'] ?? $this->template['name'] ?? 'Report');
        $pdf->Cell(0, 10, $title, 0, 1, 'C');
        $pdf->Ln(5);

        // Get layout or sheets
        $layout = $this->template['layout'] ?? null;
        $sheets = $this->template['sheets'] ?? [];
        $pdfSections = $this->template['pdfLayout'] ?? null;

        if ($pdfSections) {
            $this->renderPdfSections($pdf, $pdfSections);
        } elseif (!empty($sheets)) {
            $this->renderSheetsToPdf($pdf, $sheets);
        }

        $content = $pdf->Output('', 'S');

        $filename = $this->resolveString($this->template['filename'] ?? ($this->template['name'] ?? 'report'));
        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filename);

        return [
            'content' => $content,
            'mimeType' => 'application/pdf',
            'filename' => $filename . '.pdf',
        ];
    }

    /**
     * Render PDF sections using TCPDF.
     */
    private function renderPdfSections(\TCPDF $pdf, array $sections): void
    {
        foreach ($sections as $section) {
            $type = $section['type'] ?? 'table';

            switch ($type) {
                case 'title':
                    $pdf->SetFont($this->pdfFontName, 'B', $section['fontSize'] ?? 12);
                    $text = $this->resolveString($section['text'] ?? '');
                    $pdf->Cell(0, 8, $text, 0, 1);
                    $pdf->Ln($section['spacing'] ?? 3);
                    break;

                case 'text':
                    $pdf->SetFont($this->pdfFontName, '', $section['fontSize'] ?? 9);
                    $text = $this->resolveString($section['text'] ?? '');
                    $pdf->MultiCell(0, 6, $text);
                    $pdf->Ln($section['spacing'] ?? 3);
                    break;

                case 'table':
                    $this->renderPdfTable($pdf, $section);
                    $pdf->Ln($section['spacing'] ?? 5);
                    break;

                case 'summary':
                    $this->renderPdfSummary($pdf, $section);
                    break;

                case 'spacer':
                    $pdf->Ln($section['height'] ?? 5);
                    break;
            }
        }
    }

    /**
     * Render a PDF table section using TCPDF.
     */
    private function renderPdfTable(\TCPDF $pdf, array $section): void
    {
        $source = $section['source'] ?? '';
        $data = $this->getData($source);
        $columns = $section['columns'] ?? [];
        $maxRows = $section['maxRows'] ?? null; // null means no limit
        $title = $section['title'] ?? null;

        if ($title) {
            $pdf->SetFont($this->pdfFontName, 'B', 11);
            $titleText = $this->resolveString($title);
            $pdf->Cell(0, 8, $titleText, 0, 1);
        }

        // Header
        $pdf->SetFont($this->pdfFontName, 'B', 8);
        $pdf->SetFillColor(178, 178, 178);

        foreach ($columns as $col) {
            $width = $col['width'] ?? 30;
            $label = $col['label'] ?? '';
            $pdf->Cell($width, 6, $label, 1, 0, 'C', true);
        }
        $pdf->Ln();

        // Data rows
        $pdf->SetFont($this->pdfFontName, '', 8);
        $count = 0;
        $rowHeight = 5;
        
        foreach ($data as $row) {
            if ($maxRows !== null && $count >= $maxRows) {
                break;
            }

            // First pass: calculate the maximum row height needed for wrapping text
            $maxRowHeight = $rowHeight;
            $cellValues = [];
            foreach ($columns as $i => $col) {
                $width = $col['width'] ?? 30;
                $field = $col['field'] ?? $col['key'] ?? '';
                $rawValue = $row[$field] ?? '';
                $value = (string)$rawValue;
                $format = $col['format'] ?? null;
                $align = $col['alignment'] ?? null;
                
                if ($format === 'currency' && is_numeric($rawValue)) {
                    $value = '£' . number_format((float)$rawValue, 2);
                    $align = 'R';
                } elseif ($format === 'number' && is_numeric($rawValue)) {
                    $value = number_format(round((float)$rawValue), 0);
                    $align = 'R';
                } elseif ($align === null) {
                    // Auto-detect alignment: right for numeric values, left for text
                    $align = is_numeric($rawValue) ? 'R' : 'L';
                }
                
                $cellValues[$i] = ['value' => $value, 'width' => $width, 'align' => $align];
                
                // Calculate number of lines needed for this cell
                $numLines = $pdf->getNumLines($value, $width);
                $cellHeight = $numLines * $rowHeight;
                if ($cellHeight > $maxRowHeight) {
                    $maxRowHeight = $cellHeight;
                }
            }

            // Check if we need a page break
            if ($pdf->GetY() + $maxRowHeight > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
                $pdf->AddPage();
                $pdf->SetFont($this->pdfFontName, '', 8);
            }

            // Second pass: render all cells with uniform height
            $startX = $pdf->GetX();
            $startY = $pdf->GetY();
            $currentX = $startX;
            
            foreach ($cellValues as $cell) {
                // Draw cell border/background with full row height
                $pdf->Rect($currentX, $startY, $cell['width'], $maxRowHeight);
                
                // Write text inside the cell with padding
                $pdf->SetXY($currentX + 0.5, $startY + 0.5);
                $pdf->MultiCell(
                    $cell['width'] - 1,  // width with padding
                    $rowHeight,           // line height
                    $cell['value'],       // text
                    0,                    // no border (we drew it with Rect)
                    strtoupper(substr($cell['align'], 0, 1)),  // alignment
                    false,                // no fill
                    1,                    // move to next line after
                    $currentX + 0.5,      // x position
                    $startY + 0.5,        // y position
                    true,                 // reset height
                    0,                    // stretch mode
                    false,                // is html
                    true,                 // auto padding
                    $maxRowHeight - 1,    // max height
                    'T'                   // vertical alignment top
                );
                
                $currentX += $cell['width'];
            }
            
            // Move to next row
            $pdf->SetXY($startX, $startY + $maxRowHeight);
            $count++;
        }

        // Show "more items" message if truncated
        if ($maxRows !== null && count($data) > $maxRows) {
            $pdf->SetFont($this->pdfFontName, 'I', 7);
            $pdf->Cell(0, 5, '... and ' . (count($data) - $maxRows) . ' more items', 0, 1);
        }

        // Aggregate row if defined
        if (isset($section['showTotal']) && $section['showTotal']) {
            $pdf->SetFont($this->pdfFontName, 'B', 9);
            $totalWidth = 0;
            foreach ($columns as $i => $col) {
                $width = $col['width'] ?? 30;
                if ($i < count($columns) - 1) {
                    $totalWidth += $width;
                }
            }

            $lastCol = $columns[count($columns) - 1] ?? [];
            $field = $lastCol['field'] ?? $lastCol['key'] ?? 'cost';
            $total = array_sum(array_map(fn($r) => (float)($r[$field] ?? 0), $data));

            $pdf->Cell($totalWidth, 6, 'Total:', 1, 0, 'R', true);
            $pdf->Cell($lastCol['width'] ?? 30, 6, '£' . number_format($total, 2), 1, 1, 'R', true);
        }
    }

    /**
     * Render PDF summary section using TCPDF.
     */
    private function renderPdfSummary(\TCPDF $pdf, array $section): void
    {
        $pdf->SetFont($this->pdfFontName, 'B', 11);
        $titleText = $section['title'] ?? 'Summary';
        $pdf->Cell(0, 8, $titleText, 0, 1);

        $pdf->SetFont($this->pdfFontName, '', 9);
        $items = $section['items'] ?? [];

        foreach ($items as $item) {
            $label = $item['label'] ?? '';
            $value = $item['value'] ?? '';

            // Resolve calculation references
            if (is_string($value) && str_starts_with($value, '{{')) {
                $value = $this->resolveVariable($value);
            }

            $format = $item['format'] ?? null;
            if ($format === 'currency' && is_numeric($value)) {
                // TCPDF handles UTF-8 including £ symbol natively
                $value = '£' . number_format((float)$value, 2);
            } elseif ($format === 'number' && is_numeric($value)) {
                $value = number_format(round((float)$value), 0);
            } else {
                $value = (string)$value;
            }

            $bold = $item['bold'] ?? false;
            $pdf->SetFont($this->pdfFontName, $bold ? 'B' : '', 9);
            $pdf->Cell(60, 6, $label, 0);
            $pdf->Cell(40, 6, $value, 0, 1, 'R');
        }
    }

    /**
     * Render legacy sheets format to PDF using TCPDF.
     */
    private function renderSheetsToPdf(\TCPDF $pdf, array $sheets): void
    {
        foreach ($sheets as $sheetDef) {
            $source = $sheetDef['source'] ?? '';
            $columns = $sheetDef['columns'] ?? [];
            $data = $this->getData($source);
            $title = $sheetDef['name'] ?? '';

            if (empty($data)) {
                continue;
            }

            // Apply sorting if defined
            if (isset($sheetDef['sort'])) {
                $data = $this->sortData($data, $sheetDef['sort']);
            }

            $this->renderPdfTable($pdf, [
                'title' => $title,
                'source' => $source,
                'columns' => array_map(function ($col) {
                    return [
                        'label' => $col['label'] ?? ($col['key'] ?? ''),
                        'field' => $col['key'] ?? ($col['field'] ?? ''),
                        'width' => min(($col['width'] ?? 30), 60),
                        'format' => $col['format'] ?? null,
                        'alignment' => $col['alignment'] ?? 'L',
                    ];
                }, $columns),
                'maxRows' => 30,
                'showTotal' => !empty(array_filter($columns, fn($c) => isset($c['aggregate']))),
            ]);

            $pdf->Ln(5);
        }
    }
}
