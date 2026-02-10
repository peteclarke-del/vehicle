<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Attachment;
use App\Entity\Consumable;
use App\Entity\ConsumableType;
use App\Entity\FeatureFlag;
use App\Entity\FuelRecord;
use App\Entity\InsurancePolicy;
use App\Entity\MotRecord;
use App\Entity\Part;
use App\Entity\PartCategory;
use App\Entity\RoadTax;
use App\Entity\Report;
use App\Entity\ServiceItem;
use App\Entity\ServiceRecord;
use App\Entity\Specification;
use App\Entity\Todo;
use App\Entity\User;
use App\Entity\UserFeatureOverride;
use App\Entity\UserPreference;
use App\Entity\Vehicle;
use App\Entity\VehicleAssignment;
use App\Entity\VehicleImage;
use App\Entity\VehicleStatusHistory;
use App\Entity\VehicleType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * class DemoFixtures
 *
 * Demo fixtures for populating a showcase/demo site with realistic data.
 * Usage:
 * php bin/console doctrine:fixtures:load --group=demo
 * WARNING: This will PURGE the database. Do NOT run against a production database
 * containing real data you wish to keep. Use --append to add without purging.
 * php bin/console doctrine:fixtures:load --group=demo --append
 */
class DemoFixtures extends Fixture implements FixtureGroupInterface
{
    /**
     * function __construct
     *
     * @param UserPasswordHasherInterface $passwordHasher
     *
     * @return void
     */
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * function getGroups
     *
     * @return array
     */
    public static function getGroups(): array
    {
        return ['demo'];
    }

    /**
     * function load
     *
     * @param ObjectManager $manager
     *
     * @return void
     */
    public function load(ObjectManager $manager): void
    {
        // Safety guard: only run when explicitly requested via --group=demo
        // This prevents demo data from loading during normal fixture reloads
        if (!in_array('demo', $_SERVER['FIXTURE_GROUPS'] ?? [], true)
            && !getenv('DEMO_FIXTURES')
            && !isset($_ENV['DEMO_FIXTURES'])
        ) {
            // Check if we were loaded as part of an ungrouped (load-all) run
            // by looking at the CLI arguments for --group=demo
            $args = $_SERVER['argv'] ?? [];
            $groupIdx = array_search('--group', $args);
            $hasGroupArg = $groupIdx !== false && isset($args[$groupIdx + 1]) && $args[$groupIdx + 1] === 'demo';
            $hasGroupEquals = !empty(array_filter($args, fn($a) => str_starts_with($a, '--group=demo')));

            if (!$hasGroupArg && !$hasGroupEquals) {
                echo "⏭  Skipping DemoFixtures (use --group=demo to load demo data)\n";
                return;
            }
        }

        echo "╔══════════════════════════════════════════════╗\n";
        echo "║       Vehicle Manager — Demo Fixtures        ║\n";
        echo "╚══════════════════════════════════════════════╝\n\n";

        // 1. Vehicle Types
        $vehicleTypes = $this->loadVehicleTypes($manager);

        // 2. Users
        [$users, $preferences] = $this->loadUsers($manager);

        // 3. Consumable Types & Part Categories (minimal set for demo)
        $consumableTypes = $this->loadConsumableTypes($manager, $vehicleTypes);
        $partCategories = $this->loadPartCategories($manager, $vehicleTypes);

        // 4. Vehicles
        $vehicles = $this->loadVehicles($manager, $users, $vehicleTypes);

        // 5. Specifications
        $this->loadSpecifications($manager, $vehicles);

        // 6. Fuel Records
        $this->loadFuelRecords($manager, $vehicles);

        // 7. Service Records (with Service Items)
        $serviceRecords = $this->loadServiceRecords($manager, $vehicles, $consumableTypes, $partCategories);

        // 8. Parts
        $this->loadParts($manager, $vehicles, $partCategories);

        // 9. Consumables
        $this->loadConsumables($manager, $vehicles, $consumableTypes);

        // 10. MOT Records
        $this->loadMotRecords($manager, $vehicles);

        // 11. Insurance Policies
        $this->loadInsurancePolicies($manager, $vehicles, $users);

        // 12. Road Tax
        $this->loadRoadTax($manager, $vehicles);

        // 13. Todos
        $this->loadTodos($manager, $vehicles);

        // 14. Vehicle Status History
        $this->loadStatusHistory($manager, $vehicles, $users);

        // 15. Feature Flags
        $featureFlags = $this->loadFeatureFlags($manager);

        // 16. User Feature Overrides
        $this->loadUserFeatureOverrides($manager, $users, $featureFlags);

        // 17. Vehicle Assignments
        $this->loadVehicleAssignments($manager, $vehicles, $users);

        // 18. Reports
        $this->loadReports($manager, $vehicles, $users);

        $manager->flush();

        echo "\n✅ Demo fixtures loaded successfully!\n";
        echo "   Credentials:\n";
        echo "   • Admin:  demo-admin@vehicle.local / DemoAdmin123!\n";
        echo "   • User 1: john.smith@example.com   / DemoUser123!\n";
        echo "   • User 2: sarah.jones@example.com  / DemoUser123!\n";
        echo "   • User 3: mike.wilson@example.com  / DemoUser123!\n\n";
    }

    // ─────────────────────────────────────────────────────────────────────
    // Vehicle Types
    // ─────────────────────────────────────────────────────────────────────

    /**
     * function loadVehicleTypes
     *
     * @param ObjectManager $manager
     *
     * @return array
     */
    private function loadVehicleTypes(ObjectManager $manager): array
    {
        $types = [];
        foreach (['Car', 'Motorcycle', 'Van', 'Truck', 'EV'] as $name) {
            $type = new VehicleType();
            $type->setName($name);
            $manager->persist($type);
            $types[$name] = $type;
        }
        $manager->flush();
        echo "✓ Created 5 vehicle types\n";

        return $types;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Users
    // ─────────────────────────────────────────────────────────────────────

    /**
     * function loadUsers
     *
     * @param ObjectManager $manager
     *
     * @return array
     */
    private function loadUsers(ObjectManager $manager): array
    {
        $usersData = [
            [
                'email'     => 'demo-admin@vehicle.local',
                'firstName' => 'Demo',
                'lastName'  => 'Admin',
                'password'  => 'DemoAdmin123!',
                'roles'     => ['ROLE_ADMIN', 'ROLE_USER'],
                'country'   => 'GB',
                'prefs'     => ['preferredLanguage' => 'en', 'distanceUnit' => 'mi', 'theme' => 'dark', 'pinnedNavMenu' => 'true'],
            ],
            [
                'email'     => 'john.smith@example.com',
                'firstName' => 'John',
                'lastName'  => 'Smith',
                'password'  => 'DemoUser123!',
                'roles'     => ['ROLE_USER'],
                'country'   => 'GB',
                'prefs'     => ['preferredLanguage' => 'en', 'distanceUnit' => 'mi', 'theme' => 'light'],
            ],
            [
                'email'     => 'sarah.jones@example.com',
                'firstName' => 'Sarah',
                'lastName'  => 'Jones',
                'password'  => 'DemoUser123!',
                'roles'     => ['ROLE_USER'],
                'country'   => 'GB',
                'prefs'     => ['preferredLanguage' => 'en', 'distanceUnit' => 'mi', 'theme' => 'dark'],
            ],
            [
                'email'     => 'mike.wilson@example.com',
                'firstName' => 'Mike',
                'lastName'  => 'Wilson',
                'password'  => 'DemoUser123!',
                'roles'     => ['ROLE_USER'],
                'country'   => 'US',
                'prefs'     => ['preferredLanguage' => 'en', 'distanceUnit' => 'mi', 'theme' => 'light'],
            ],
        ];

        $users = [];
        $preferences = [];

        foreach ($usersData as $data) {
            $user = new User();
            $user->setEmail($data['email']);
            $user->setFirstName($data['firstName']);
            $user->setLastName($data['lastName']);
            $user->setRoles($data['roles']);
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
            $user->setCountry($data['country']);
            $user->setPasswordChangeRequired(false);
            $user->setIsActive(true);
            $user->setIsVerified(true);
            $user->setLastLoginAt(new \DateTime('-' . rand(0, 48) . ' hours'));
            $manager->persist($user);
            $users[$data['email']] = $user;

            foreach ($data['prefs'] as $name => $value) {
                $pref = new UserPreference();
                $pref->setUser($user);
                $pref->setName($name);
                $pref->setValue($value);
                $manager->persist($pref);
                $preferences[] = $pref;
            }
        }

        $manager->flush();
        echo "✓ Created 4 demo users (1 admin + 3 standard)\n";

        return [$users, $preferences];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Consumable Types
    // ─────────────────────────────────────────────────────────────────────

    /**
     * function loadConsumableTypes
     *
     * @param ObjectManager $manager
     * @param array $vehicleTypes
     *
     * @return array
     */
    private function loadConsumableTypes(ObjectManager $manager, array $vehicleTypes): array
    {
        $items = [
            ['Engine Oil',       'litres', 'Engine lubricant',                   'Car'],
            ['Coolant',          'litres', 'Engine coolant / antifreeze',        'Car'],
            ['Brake Fluid',      'ml',     'DOT 4 brake fluid',                 'Car'],
            ['Windscreen Wash',  'litres', 'Screenwash concentrate',            'Car'],
            ['Engine Oil',       'litres', 'Engine lubricant',                   'Motorcycle'],
            ['Chain Lube',       'ml',     'O-ring chain lubricant',            'Motorcycle'],
            ['Coolant',          'litres', 'Engine coolant / antifreeze',        'Motorcycle'],
            ['Brake Fluid',      'ml',     'DOT 4 brake fluid',                 'Motorcycle'],
            ['Engine Oil',       'litres', 'Engine lubricant',                   'Van'],
            ['AdBlue',           'litres', 'Diesel exhaust fluid',              'Van'],
            ['Coolant',          'litres', 'Engine coolant / antifreeze',        'Van'],
            ['Battery Coolant',  'litres', 'Battery thermal management fluid',  'EV'],
            ['Windscreen Wash',  'litres', 'Screenwash concentrate',            'EV'],
        ];

        $types = [];
        foreach ($items as [$name, $unit, $desc, $vType]) {
            $ct = new ConsumableType();
            $ct->setName($name);
            $ct->setUnit($unit);
            $ct->setDescription($desc);
            $ct->setVehicleType($vehicleTypes[$vType]);
            $manager->persist($ct);
            $types[$vType . ':' . $name] = $ct;
        }

        $manager->flush();
        echo "✓ Created " . count($items) . " consumable types\n";

        return $types;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Part Categories
    // ─────────────────────────────────────────────────────────────────────

    /**
     * function loadPartCategories
     *
     * @param ObjectManager $manager
     * @param array $vehicleTypes
     *
     * @return array
     */
    private function loadPartCategories(ObjectManager $manager, array $vehicleTypes): array
    {
        $items = [
            ['Brakes',       'Brake pads, discs, calipers',                   'Car'],
            ['Filters',      'Oil, air, fuel, cabin filters',                 'Car'],
            ['Suspension',   'Shocks, springs, bushings',                     'Car'],
            ['Electrical',   'Bulbs, fuses, alternators, starters',           'Car'],
            ['Tyres',        'All season, summer, winter tyres',              'Car'],
            ['Exhaust',      'Catalytic converters, mufflers, pipes',         'Car'],
            ['Brakes',       'Brake pads, discs, levers',                     'Motorcycle'],
            ['Chain & Sprockets', 'Drive chain, front/rear sprockets',        'Motorcycle'],
            ['Filters',      'Oil, air filters',                              'Motorcycle'],
            ['Tyres',        'Front and rear motorcycle tyres',               'Motorcycle'],
            ['Brakes',       'Brake pads, discs, calipers',                   'Van'],
            ['Filters',      'Oil, air, fuel, cabin filters',                 'Van'],
            ['Battery',      'HV battery modules, 12V auxiliary',             'EV'],
            ['Brakes',       'Brake pads, discs, regenerative components',    'EV'],
        ];

        $categories = [];
        foreach ($items as [$name, $desc, $vType]) {
            $pc = new PartCategory();
            $pc->setName($name);
            $pc->setDescription($desc);
            $pc->setVehicleType($vehicleTypes[$vType]);
            $manager->persist($pc);
            $categories[$vType . ':' . $name] = $pc;
        }

        $manager->flush();
        echo "✓ Created " . count($items) . " part categories\n";

        return $categories;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Vehicles
    // ─────────────────────────────────────────────────────────────────────

    /**
     * function loadVehicles
     *
     * @param ObjectManager $manager
     * @param array $users
     * @param array $vehicleTypes
     *
     * @return array
     */
    private function loadVehicles(ObjectManager $manager, array $users, array $vehicleTypes): array
    {
        $admin = $users['demo-admin@vehicle.local'];
        $john  = $users['john.smith@example.com'];
        $sarah = $users['sarah.jones@example.com'];
        $mike  = $users['mike.wilson@example.com'];

        $vehiclesData = [
            // ── Admin's vehicles ──
            [
                'owner'       => $admin,
                'type'        => 'Car',
                'name'        => 'Daily Driver',
                'make'        => 'BMW',
                'model'       => '320d M Sport',
                'year'        => 2021,
                'reg'         => 'AB21 BMW',
                'vin'         => 'WBA8E9C50NCK12345',
                'colour'      => 'Alpine White',
                'purchaseCost'=> '28500.00',
                'purchaseDate'=> '2021-03-15',
                'purchaseMileage' => 5200,
                'currentMileage'  => 47800,
                'status'      => 'Live',
                'serviceIntervalMonths' => 12,
                'serviceIntervalMiles'  => 10000,
            ],
            [
                'owner'       => $admin,
                'type'        => 'Motorcycle',
                'name'        => 'Weekend Toy',
                'make'        => 'Kawasaki',
                'model'       => 'Z900',
                'year'        => 2022,
                'reg'         => 'KW22 ZED',
                'vin'         => 'JKAZR2C18NA012345',
                'colour'      => 'Metallic Spark Black',
                'purchaseCost'=> '8999.00',
                'purchaseDate'=> '2022-06-01',
                'purchaseMileage' => 120,
                'currentMileage'  => 12400,
                'status'      => 'Live',
                'serviceIntervalMonths' => 12,
                'serviceIntervalMiles'  => 6000,
            ],
            [
                'owner'       => $admin,
                'type'        => 'Car',
                'name'        => 'Old Banger (Sold)',
                'make'        => 'Ford',
                'model'       => 'Focus 1.6 Zetec',
                'year'        => 2014,
                'reg'         => 'FD14 OLD',
                'vin'         => 'WF0XXXGCDXEY12345',
                'colour'      => 'Deep Impact Blue',
                'purchaseCost'=> '6200.00',
                'purchaseDate'=> '2017-09-20',
                'purchaseMileage' => 38000,
                'currentMileage'  => 102000,
                'status'      => 'Sold',
                'serviceIntervalMonths' => 12,
                'serviceIntervalMiles'  => 10000,
            ],
            // ── John's vehicles ──
            [
                'owner'       => $john,
                'type'        => 'Car',
                'name'        => 'Family Car',
                'make'        => 'Volkswagen',
                'model'       => 'Tiguan R-Line',
                'year'        => 2023,
                'reg'         => 'VW73 TIG',
                'vin'         => 'WVGZZZ5NZPW012345',
                'colour'      => 'Oryx White',
                'purchaseCost'=> '35200.00',
                'purchaseDate'=> '2023-09-01',
                'purchaseMileage' => 12,
                'currentMileage'  => 18500,
                'status'      => 'Live',
                'serviceIntervalMonths' => 12,
                'serviceIntervalMiles'  => 10000,
            ],
            [
                'owner'       => $john,
                'type'        => 'Van',
                'name'        => 'Work Van',
                'make'        => 'Ford',
                'model'       => 'Transit Custom',
                'year'        => 2020,
                'reg'         => 'FT20 VAN',
                'vin'         => 'WF0XXXTTGXLY12345',
                'colour'      => 'Frozen White',
                'purchaseCost'=> '22800.00',
                'purchaseDate'=> '2020-02-14',
                'purchaseMileage' => 0,
                'currentMileage'  => 68200,
                'status'      => 'Live',
                'serviceIntervalMonths' => 12,
                'serviceIntervalMiles'  => 12000,
            ],
            // ── Sarah's vehicles ──
            [
                'owner'       => $sarah,
                'type'        => 'EV',
                'name'        => 'Electric Daily',
                'make'        => 'Tesla',
                'model'       => 'Model 3 Long Range',
                'year'        => 2024,
                'reg'         => 'TS24 EEV',
                'vin'         => '5YJ3E7EA1RF012345',
                'colour'      => 'Midnight Silver',
                'purchaseCost'=> '42990.00',
                'purchaseDate'=> '2024-01-10',
                'purchaseMileage' => 8,
                'currentMileage'  => 14200,
                'status'      => 'Live',
                'roadTaxExempt'  => true,
                'serviceIntervalMonths' => 24,
                'serviceIntervalMiles'  => 25000,
            ],
            [
                'owner'       => $sarah,
                'type'        => 'Motorcycle',
                'name'        => 'Summer Bike',
                'make'        => 'Honda',
                'model'       => 'CB650R',
                'year'        => 2023,
                'reg'         => 'HN23 CBR',
                'vin'         => 'JH2RC9720PK012345',
                'colour'      => 'Matt Gunpowder Black',
                'purchaseCost'=> '7499.00',
                'purchaseDate'=> '2023-04-15',
                'purchaseMileage' => 0,
                'currentMileage'  => 8300,
                'status'      => 'Live',
                'serviceIntervalMonths' => 12,
                'serviceIntervalMiles'  => 8000,
            ],
            // ── Mike's vehicle ──
            [
                'owner'       => $mike,
                'type'        => 'Car',
                'name'        => 'Project Car',
                'make'        => 'Mazda',
                'model'       => 'MX-5 RF',
                'year'        => 2019,
                'reg'         => 'MZ19 MXF',
                'vin'         => 'JM1NDAD76K0312345',
                'colour'      => 'Soul Red Crystal',
                'purchaseCost'=> '18500.00',
                'purchaseDate'=> '2022-03-10',
                'purchaseMileage' => 22000,
                'currentMileage'  => 41500,
                'status'      => 'Live',
                'serviceIntervalMonths' => 12,
                'serviceIntervalMiles'  => 8000,
            ],
        ];

        $vehicles = [];
        foreach ($vehiclesData as $data) {
            $vehicle = new Vehicle();
            $vehicle->setOwner($data['owner']);
            $vehicle->setVehicleType($vehicleTypes[$data['type']]);
            $vehicle->setName($data['name']);
            $vehicle->setMake($data['make']);
            $vehicle->setModel($data['model']);
            $vehicle->setYear($data['year']);
            $vehicle->setRegistrationNumber($data['reg']);
            $vehicle->setVin($data['vin']);
            $vehicle->setVehicleColor($data['colour']);
            $vehicle->setPurchaseCost($data['purchaseCost']);
            $vehicle->setPurchaseDate(new \DateTime($data['purchaseDate']));
            $vehicle->setPurchaseMileage($data['purchaseMileage']);
            $vehicle->setCurrentMileage($data['currentMileage']);
            $vehicle->setStatus($data['status']);
            $vehicle->setServiceIntervalMonths($data['serviceIntervalMonths']);
            $vehicle->setServiceIntervalMiles($data['serviceIntervalMiles']);

            if (isset($data['roadTaxExempt'])) {
                $vehicle->setRoadTaxExempt($data['roadTaxExempt']);
            }

            $manager->persist($vehicle);
            $vehicles[] = $vehicle;
        }

        $manager->flush();
        echo "✓ Created " . count($vehicles) . " demo vehicles\n";

        return $vehicles;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Specifications
    // ─────────────────────────────────────────────────────────────────────

    /**
     * function loadSpecifications
     *
     * @param ObjectManager $manager
     * @param array $vehicles
     *
     * @return void
     */
    private function loadSpecifications(ObjectManager $manager, array $vehicles): void
    {
        $specsData = [
            // BMW 320d M Sport
            0 => [
                'engineType'    => '2.0L Turbocharged Diesel (B47)',
                'displacement'  => '1995 cc',
                'power'         => '190 bhp @ 4000 rpm',
                'torque'        => '400 Nm @ 1750-2500 rpm',
                'fuelSystem'    => 'Common Rail Direct Injection',
                'cooling'       => 'Liquid Cooled',
                'gearbox'       => '8-speed Steptronic Sport Auto',
                'transmission'  => 'Rear Wheel Drive',
                'frontBrakes'   => '330mm Ventilated Discs',
                'rearBrakes'    => '300mm Solid Discs',
                'frontTyre'     => '225/45 R18',
                'rearTyre'      => '255/40 R18',
                'frontTyrePressure' => '2.3 bar',
                'rearTyrePressure'  => '2.5 bar',
                'engineOilType'     => 'BMW Longlife-04 5W-30',
                'engineOilCapacity' => '5.2 litres',
                'dryWeight'     => '1520 kg',
                'fuelCapacity'  => '59 litres',
                'topSpeed'      => '155 mph (limited)',
                'wheelbase'     => '2851 mm',
            ],
            // Kawasaki Z900
            1 => [
                'engineType'    => '948cc Inline-4, DOHC, 16-valve',
                'displacement'  => '948 cc',
                'power'         => '125 bhp @ 9500 rpm',
                'torque'        => '98.6 Nm @ 7700 rpm',
                'compression'   => '11.8:1',
                'bore'          => '73.4 mm',
                'stroke'        => '56.0 mm',
                'fuelSystem'    => 'Fuel Injection (Keihin 36mm x 4)',
                'cooling'       => 'Liquid Cooled',
                'gearbox'       => '6-speed',
                'transmission'  => 'Chain Drive',
                'finalDrive'    => '525 O-Ring Chain',
                'clutch'        => 'Wet Multi-disc, Assist & Slipper',
                'engineOilType' => 'Kawasaki 10W-40 Semi-Synthetic',
                'engineOilCapacity' => '3.8 litres',
                'frame'         => 'Trellis, High-Tensile Steel',
                'frontSuspension' => '41mm USD Forks',
                'rearSuspension'  => 'Horizontal Back-link, Adjustable Preload',
                'frontBrakes'   => '300mm Dual Petal Discs',
                'rearBrakes'    => '250mm Single Petal Disc',
                'frontTyre'     => '120/70 ZR17',
                'rearTyre'      => '180/55 ZR17',
                'frontTyrePressure' => '2.5 bar',
                'rearTyrePressure'  => '2.9 bar',
                'wheelbase'     => '1455 mm',
                'seatHeight'    => '795 mm',
                'groundClearance' => '130 mm',
                'dryWeight'     => '210 kg',
                'fuelCapacity'  => '17 litres',
                'topSpeed'      => '146 mph',
            ],
            // Ford Focus — skip (sold vehicle, minimal data)
            // VW Tiguan
            3 => [
                'engineType'    => '2.0L TSI Turbocharged Petrol',
                'displacement'  => '1984 cc',
                'power'         => '190 PS @ 4200 rpm',
                'torque'        => '320 Nm @ 1500-4100 rpm',
                'fuelSystem'    => 'Direct Injection',
                'cooling'       => 'Liquid Cooled',
                'gearbox'       => '7-speed DSG',
                'transmission'  => '4MOTION All-Wheel Drive',
                'frontBrakes'   => '340mm Ventilated Discs',
                'rearBrakes'    => '310mm Solid Discs',
                'frontTyre'     => '235/50 R19',
                'rearTyre'      => '235/50 R19',
                'engineOilType' => 'VW 508.00 0W-20',
                'engineOilCapacity' => '5.7 litres',
                'dryWeight'     => '1629 kg',
                'fuelCapacity'  => '58 litres',
                'topSpeed'      => '136 mph',
                'wheelbase'     => '2681 mm',
            ],
            // Ford Transit Custom
            4 => [
                'engineType'    => '2.0L EcoBlue Diesel',
                'displacement'  => '1996 cc',
                'power'         => '170 PS @ 3500 rpm',
                'torque'        => '405 Nm @ 1750-2000 rpm',
                'fuelSystem'    => 'Common Rail Direct Injection',
                'cooling'       => 'Liquid Cooled',
                'gearbox'       => '6-speed Manual',
                'transmission'  => 'Front Wheel Drive',
                'frontBrakes'   => '303mm Ventilated Discs',
                'rearBrakes'    => '280mm Solid Discs',
                'frontTyre'     => '215/65 R16C',
                'rearTyre'      => '215/65 R16C',
                'engineOilType' => 'Ford WSS-M2C913-D 5W-30',
                'engineOilCapacity' => '6.0 litres',
                'dryWeight'     => '1867 kg',
                'fuelCapacity'  => '70 litres',
                'topSpeed'      => '112 mph',
                'wheelbase'     => '2933 mm',
            ],
            // Tesla Model 3
            5 => [
                'engineType'    => 'Dual Motor Electric (Front + Rear)',
                'power'         => '366 PS combined',
                'torque'        => '493 Nm combined',
                'gearbox'       => 'Single-speed Fixed Gear',
                'transmission'  => 'All-Wheel Drive',
                'frontBrakes'   => '320mm Ventilated Discs',
                'rearBrakes'    => '335mm Ventilated Discs',
                'frontTyre'     => '235/40 R19',
                'rearTyre'      => '235/40 R19',
                'dryWeight'     => '1830 kg',
                'topSpeed'      => '145 mph',
                'wheelbase'     => '2875 mm',
                'additionalInfo' => 'EPA Range: 358 miles. Battery: 82 kWh. Supercharger V3 capable (250kW).',
            ],
            // Honda CB650R
            6 => [
                'engineType'    => '649cc Inline-4, DOHC, 16-valve',
                'displacement'  => '649 cc',
                'power'         => '95 bhp @ 12000 rpm',
                'torque'        => '64 Nm @ 8500 rpm',
                'compression'   => '11.6:1',
                'bore'          => '67.0 mm',
                'stroke'        => '46.0 mm',
                'fuelSystem'    => 'PGM-FI Electronic Fuel Injection',
                'cooling'       => 'Liquid Cooled',
                'gearbox'       => '6-speed',
                'transmission'  => 'Chain Drive',
                'finalDrive'    => '520 Chain',
                'clutch'        => 'Wet Multi-disc, Assist & Slipper',
                'engineOilType' => 'Honda Pro 10W-30',
                'engineOilCapacity' => '3.5 litres',
                'frame'         => 'Steel Diamond',
                'frontSuspension' => '41mm SFF-BP USD Forks',
                'rearSuspension'  => 'Pro-Link Monoshock, Adjustable Preload',
                'frontBrakes'   => '310mm Dual Wave Discs',
                'rearBrakes'    => '240mm Single Disc',
                'frontTyre'     => '120/70 ZR17',
                'rearTyre'      => '180/55 ZR17',
                'frontTyrePressure' => '2.5 bar',
                'rearTyrePressure'  => '2.9 bar',
                'wheelbase'     => '1450 mm',
                'seatHeight'    => '810 mm',
                'groundClearance' => '150 mm',
                'wetWeight'     => '202 kg',
                'fuelCapacity'  => '15.4 litres',
                'topSpeed'      => '137 mph',
            ],
            // Mazda MX-5 RF
            7 => [
                'engineType'    => '2.0L SKYACTIV-G Petrol',
                'displacement'  => '1998 cc',
                'power'         => '184 PS @ 7000 rpm',
                'torque'        => '205 Nm @ 4000 rpm',
                'compression'   => '13.0:1',
                'fuelSystem'    => 'Direct Injection',
                'cooling'       => 'Liquid Cooled',
                'gearbox'       => '6-speed Manual',
                'transmission'  => 'Rear Wheel Drive',
                'frontBrakes'   => '280mm Ventilated Discs',
                'rearBrakes'    => '280mm Solid Discs',
                'frontTyre'     => '205/45 R17',
                'rearTyre'      => '205/45 R17',
                'engineOilType' => 'Mazda Original Oil Supra 0W-20',
                'engineOilCapacity' => '4.2 litres',
                'dryWeight'     => '1111 kg',
                'fuelCapacity'  => '45 litres',
                'topSpeed'      => '136 mph',
                'wheelbase'     => '2310 mm',
                'seatHeight'    => 'N/A (coupe)',
                'groundClearance' => '140 mm',
            ],
        ];

        $count = 0;
        foreach ($specsData as $idx => $data) {
            $spec = new Specification();
            $spec->setVehicle($vehicles[$idx]);

            foreach ($data as $field => $value) {
                $setter = 'set' . ucfirst($field);
                if (method_exists($spec, $setter)) {
                    $spec->$setter($value);
                }
            }

            $manager->persist($spec);
            $count++;
        }

        $manager->flush();
        echo "✓ Created $count vehicle specifications\n";
    }

    // ─────────────────────────────────────────────────────────────────────
    // Fuel Records
    // ─────────────────────────────────────────────────────────────────────

    /**
     * function loadFuelRecords
     *
     * @param ObjectManager $manager
     * @param array $vehicles
     *
     * @return void
     */
    private function loadFuelRecords(ObjectManager $manager, array $vehicles): void
    {
        // Generate fuel records for non-EV vehicles
        $fuelData = [
            // BMW 320d (index 0) — diesel, ~50mpg
            0 => [
                'fuelType' => 'Diesel',
                'records'  => [
                    ['2025-11-15', '42.50', '68.00', 47200, 'Shell V-Power Diesel', 'Shell, M1 Services'],
                    ['2025-10-18', '45.20', '72.32', 46500, 'Diesel',               'Tesco, Northampton'],
                    ['2025-09-22', '38.90', '62.24', 45800, 'Diesel',               'BP, A45 Coventry Rd'],
                    ['2025-08-30', '44.10', '66.15', 45100, 'Diesel',               'Sainsburys, Daventry'],
                    ['2025-07-20', '40.80', '63.24', 44300, 'Diesel',               'Esso, M40 Services'],
                    ['2025-06-14', '43.60', '67.58', 43500, 'Diesel',               'Shell, Banbury'],
                    ['2025-05-10', '41.30', '64.02', 42800, 'Shell V-Power Diesel', 'Shell, M1 Services'],
                    ['2025-04-01', '39.70', '59.55', 42000, 'Diesel',               'Asda, Northampton'],
                    ['2025-02-25', '44.80', '67.20', 41200, 'Diesel',               'Costco, Coventry'],
                    ['2025-01-18', '42.10', '63.15', 40400, 'Diesel',               'Tesco, Northampton'],
                    ['2024-12-12', '46.30', '69.45', 39600, 'Diesel',               'Shell, M1 Services'],
                    ['2024-11-08', '40.50', '60.75', 38800, 'Diesel',               'Morrisons, Daventry'],
                ],
            ],
            // Kawasaki Z900 (index 1) — petrol
            1 => [
                'fuelType' => 'Petrol',
                'records'  => [
                    ['2025-09-14', '14.20', '23.54', 12200, 'Super Unleaded', 'Shell, A45'],
                    ['2025-08-10', '13.80', '22.08', 11600, 'E10 Unleaded',   'Tesco, Northampton'],
                    ['2025-07-05', '15.10', '24.16', 10900, 'Super Unleaded', 'BP, A5 Towcester'],
                    ['2025-06-22', '12.90', '20.64', 10200, 'E10 Unleaded',   'Sainsburys, Daventry'],
                    ['2025-05-18', '14.60', '23.36', 9500,  'E10 Unleaded',   'Esso, M1 Services'],
                    ['2025-04-12', '13.50', '21.60', 8800,  'Super Unleaded', 'Shell, A45'],
                ],
            ],
            // Ford Focus (index 2) — petrol, older records
            2 => [
                'fuelType' => 'Petrol',
                'records'  => [
                    ['2024-05-10', '38.20', '57.30', 101500, 'E10 Unleaded', 'Tesco, Northampton'],
                    ['2024-04-08', '41.00', '61.50', 100800, 'E10 Unleaded', 'Asda, Northampton'],
                    ['2024-03-02', '36.50', '54.75', 100000, 'E10 Unleaded', 'Morrisons, Daventry'],
                ],
            ],
            // VW Tiguan (index 3) — petrol
            3 => [
                'fuelType' => 'Petrol',
                'records'  => [
                    ['2025-12-01', '48.30', '77.28', 18200, 'Super Unleaded', 'Shell, M1 Services'],
                    ['2025-11-05', '45.10', '72.16', 17500, 'E10 Unleaded',   'Tesco, Northampton'],
                    ['2025-10-10', '50.20', '80.32', 16700, 'E10 Unleaded',   'Costco, Coventry'],
                    ['2025-09-08', '44.90', '71.84', 15800, 'E10 Unleaded',   'Sainsburys, Daventry'],
                    ['2025-08-01', '47.60', '76.16', 15000, 'E10 Unleaded',   'Asda, Northampton'],
                    ['2025-06-15', '46.80', '74.88', 14200, 'Super Unleaded', 'BP, A45 Coventry Rd'],
                ],
            ],
            // Ford Transit Custom (index 4) — diesel
            4 => [
                'fuelType' => 'Diesel',
                'records'  => [
                    ['2025-12-05', '55.20', '88.32',  67800, 'Diesel', 'Shell, Northampton'],
                    ['2025-11-20', '58.40', '93.44',  66500, 'Diesel', 'Tesco, Northampton'],
                    ['2025-11-02', '52.10', '83.36',  65200, 'Diesel', 'BP, A45 Coventry Rd'],
                    ['2025-10-14', '56.80', '90.88',  63800, 'Diesel', 'Esso, M1 Services'],
                    ['2025-09-28', '54.30', '86.88',  62400, 'Diesel', 'Costco, Coventry'],
                    ['2025-09-10', '57.60', '92.16',  61000, 'Diesel', 'Shell, Northampton'],
                    ['2025-08-22', '53.90', '86.24',  59500, 'Diesel', 'Tesco, Northampton'],
                    ['2025-07-30', '55.70', '89.12',  58000, 'Diesel', 'Morrisons, Daventry'],
                    ['2025-07-08', '51.80', '82.88',  56400, 'Diesel', 'BP, A5 Towcester'],
                    ['2025-06-15', '56.40', '90.24',  54800, 'Diesel', 'Asda, Northampton'],
                ],
            ],
            // Tesla (index 5) — skip (EV)
            // Honda CB650R (index 6) — petrol
            6 => [
                'fuelType' => 'Petrol',
                'records'  => [
                    ['2025-09-20', '12.80', '20.48', 8100, 'Super Unleaded', 'Shell, A45'],
                    ['2025-08-15', '13.50', '21.60', 7400, 'E10 Unleaded',   'BP, Northampton'],
                    ['2025-07-22', '11.90', '19.04', 6700, 'Super Unleaded', 'Tesco, Daventry'],
                    ['2025-06-10', '14.20', '22.72', 5900, 'E10 Unleaded',   'Esso, M1 Services'],
                ],
            ],
            // Mazda MX-5 RF (index 7) — petrol
            7 => [
                'fuelType' => 'Petrol',
                'records'  => [
                    ['2025-11-22', '35.40', '56.64', 41200, 'Super Unleaded', 'Shell, M1 Services'],
                    ['2025-10-15', '38.20', '61.12', 40400, 'E10 Unleaded',   'Tesco, Northampton'],
                    ['2025-09-08', '33.90', '54.24', 39500, 'Super Unleaded', 'BP, Banbury'],
                    ['2025-08-01', '36.70', '58.72', 38700, 'E10 Unleaded',   'Costco, Coventry'],
                    ['2025-06-20', '34.80', '55.68', 37800, 'Super Unleaded', 'Shell, A45'],
                    ['2025-05-10', '37.50', '60.00', 36900, 'E10 Unleaded',   'Asda, Northampton'],
                    ['2025-03-28', '35.60', '53.40', 36000, 'E10 Unleaded',   'Morrisons, Daventry'],
                    ['2025-02-14', '33.20', '49.80', 35100, 'Super Unleaded', 'Shell, M1 Services'],
                ],
            ],
        ];

        $count = 0;
        foreach ($fuelData as $idx => $data) {
            foreach ($data['records'] as [$date, $litres, $cost, $mileage, $fuelType, $station]) {
                $record = new FuelRecord();
                $record->setVehicle($vehicles[$idx]);
                $record->setDate(new \DateTime($date));
                $record->setLitres($litres);
                $record->setCost($cost);
                $record->setMileage($mileage);
                $record->setFuelType($fuelType);
                $record->setStation($station);
                $manager->persist($record);
                $count++;
            }
        }

        $manager->flush();
        echo "✓ Created $count fuel records\n";
    }

    // ─────────────────────────────────────────────────────────────────────
    // Service Records
    // ─────────────────────────────────────────────────────────────────────

    /**
     * function loadServiceRecords
     *
     * @param ObjectManager $manager
     * @param array $vehicles
     * @param array $consumableTypes
     * @param array $partCategories
     *
     * @return array
     */
    private function loadServiceRecords(ObjectManager $manager, array $vehicles, array $consumableTypes, array $partCategories): array
    {
        $servicesData = [
            // BMW 320d
            0 => [
                [
                    'date'     => '2025-03-20',
                    'type'     => 'Full Service',
                    'labor'    => '180.00',
                    'parts'    => '95.00',
                    'mileage'  => 42500,
                    'provider' => 'BMW Northampton',
                    'work'     => 'Full service including oil & filter change, air filter, cabin filter, brake fluid check, all-round inspection',
                    'nextDate' => '2026-03-20',
                    'nextMileage' => 52500,
                    'items'    => [
                        ['part', 'Oil filter (Genuine BMW)', '18.50', '1.00'],
                        ['part', 'Air filter element', '32.00', '1.00'],
                        ['part', 'Cabin pollen filter', '24.50', '1.00'],
                        ['consumable', 'Engine Oil 5W-30 (BMW LL-04)', '52.00', '5.20'],
                        ['labour', 'Labour — Full Service', '180.00', '1.00'],
                    ],
                ],
                [
                    'date'     => '2024-03-15',
                    'type'     => 'Full Service',
                    'labor'    => '165.00',
                    'parts'    => '78.00',
                    'mileage'  => 32800,
                    'provider' => 'BMW Northampton',
                    'work'     => 'Annual service — oil & filter, cabin filter, brake inspection, tyre rotation',
                    'nextDate' => '2025-03-15',
                    'nextMileage' => 42800,
                    'items'    => [
                        ['part', 'Oil filter (Genuine BMW)', '18.50', '1.00'],
                        ['part', 'Cabin pollen filter', '24.50', '1.00'],
                        ['consumable', 'Engine Oil 5W-30', '48.00', '5.20'],
                        ['labour', 'Labour — Annual Service', '165.00', '1.00'],
                    ],
                ],
            ],
            // Kawasaki Z900
            1 => [
                [
                    'date'     => '2025-04-10',
                    'type'     => 'Full Service',
                    'labor'    => '120.00',
                    'parts'    => '65.00',
                    'mileage'  => 9200,
                    'provider' => 'Kawasaki Dealer — Moto Rapido',
                    'work'     => 'Annual service: oil & filter, air filter, chain adjust & lube, brake pad check, coolant level',
                    'nextDate' => '2026-04-10',
                    'nextMileage' => 15200,
                    'items'    => [
                        ['part', 'Oil filter (Genuine Kawasaki)', '12.00', '1.00'],
                        ['part', 'Air filter element', '28.00', '1.00'],
                        ['consumable', 'Engine Oil 10W-40', '32.00', '3.80'],
                        ['consumable', 'Chain Lube', '8.50', '1.00'],
                        ['labour', 'Labour — Full Service', '120.00', '1.00'],
                    ],
                ],
            ],
            // VW Tiguan
            3 => [
                [
                    'date'     => '2025-09-05',
                    'type'     => 'First Service',
                    'labor'    => '0.00',
                    'parts'    => '0.00',
                    'mileage'  => 15500,
                    'provider' => 'VW Northampton (Warranty)',
                    'work'     => 'Complimentary first service under VW warranty. Oil & filter, multi-point check.',
                    'nextDate' => '2026-09-05',
                    'nextMileage' => 25500,
                    'notes'    => 'Covered under VW 3-year service plan',
                    'items'    => [
                        ['consumable', 'Engine Oil 0W-20', '0.00', '5.70'],
                        ['part', 'Oil filter (Genuine VW)', '0.00', '1.00'],
                        ['labour', 'Labour — First Service (Warranty)', '0.00', '1.00'],
                    ],
                ],
            ],
            // Transit Custom
            4 => [
                [
                    'date'     => '2025-07-15',
                    'type'     => 'Full Service',
                    'labor'    => '145.00',
                    'parts'    => '92.00',
                    'mileage'  => 56000,
                    'provider' => 'Quick-Fit, Northampton',
                    'work'     => 'Full service — oil & filter, fuel filter, air filter, AdBlue top-up, brake check',
                    'nextDate' => '2026-07-15',
                    'nextMileage' => 68000,
                    'items'    => [
                        ['part', 'Oil filter', '14.00', '1.00'],
                        ['part', 'Fuel filter', '38.00', '1.00'],
                        ['part', 'Air filter', '22.00', '1.00'],
                        ['consumable', 'Engine Oil 5W-30', '45.00', '6.00'],
                        ['consumable', 'AdBlue', '18.00', '10.00'],
                        ['labour', 'Labour — Full Service', '145.00', '1.00'],
                    ],
                ],
                [
                    'date'     => '2024-07-10',
                    'type'     => 'Full Service',
                    'labor'    => '135.00',
                    'parts'    => '68.00',
                    'mileage'  => 44000,
                    'provider' => 'Quick-Fit, Northampton',
                    'work'     => 'Annual service — oil & filter, air filter, brake check, tyre rotation',
                    'items'    => [
                        ['part', 'Oil filter', '14.00', '1.00'],
                        ['part', 'Air filter', '22.00', '1.00'],
                        ['consumable', 'Engine Oil 5W-30', '45.00', '6.00'],
                        ['labour', 'Labour — Annual Service', '135.00', '1.00'],
                    ],
                ],
            ],
            // Honda CB650R
            6 => [
                [
                    'date'     => '2025-03-20',
                    'type'     => 'Full Service',
                    'labor'    => '110.00',
                    'parts'    => '55.00',
                    'mileage'  => 5200,
                    'provider' => 'Honda Dealer — Blade Honda',
                    'work'     => 'First service: oil & filter, valve clearance check, chain adjust, coolant level',
                    'nextDate' => '2026-03-20',
                    'nextMileage' => 13200,
                    'items'    => [
                        ['part', 'Oil filter (Genuine Honda)', '10.00', '1.00'],
                        ['consumable', 'Engine Oil 10W-30', '28.00', '3.50'],
                        ['labour', 'Labour — Full Service', '110.00', '1.00'],
                    ],
                ],
            ],
            // Mazda MX-5 RF
            7 => [
                [
                    'date'     => '2025-10-05',
                    'type'     => 'Full Service',
                    'labor'    => '140.00',
                    'parts'    => '72.00',
                    'mileage'  => 40000,
                    'provider' => 'Mazda Northampton',
                    'work'     => 'Annual service — oil & filter, air filter, brake inspection, suspension check',
                    'nextDate' => '2026-10-05',
                    'nextMileage' => 48000,
                    'items'    => [
                        ['part', 'Oil filter (Genuine Mazda)', '12.00', '1.00'],
                        ['part', 'Air filter element', '28.00', '1.00'],
                        ['consumable', 'Engine Oil 0W-20', '38.00', '4.20'],
                        ['labour', 'Labour — Annual Service', '140.00', '1.00'],
                    ],
                ],
                [
                    'date'     => '2024-10-01',
                    'type'     => 'Full Service',
                    'labor'    => '135.00',
                    'parts'    => '60.00',
                    'mileage'  => 32500,
                    'provider' => 'Mazda Northampton',
                    'work'     => 'Annual service — oil change, cabin filter, brake pad measurement',
                    'items'    => [
                        ['part', 'Oil filter', '12.00', '1.00'],
                        ['consumable', 'Engine Oil 0W-20', '38.00', '4.20'],
                        ['labour', 'Labour — Annual Service', '135.00', '1.00'],
                    ],
                ],
            ],
        ];

        $serviceRecords = [];
        $count = 0;
        $itemCount = 0;

        foreach ($servicesData as $idx => $records) {
            foreach ($records as $data) {
                $sr = new ServiceRecord();
                $sr->setVehicle($vehicles[$idx]);
                $sr->setServiceDate(new \DateTime($data['date']));
                $sr->setServiceType($data['type']);
                $sr->setLaborCost($data['labor']);
                $sr->setPartsCost($data['parts']);
                $sr->setMileage($data['mileage']);
                $sr->setServiceProvider($data['provider']);
                $sr->setWorkPerformed($data['work']);

                if (isset($data['nextDate'])) {
                    $sr->setNextServiceDate(new \DateTime($data['nextDate']));
                }
                if (isset($data['nextMileage'])) {
                    $sr->setNextServiceMileage($data['nextMileage']);
                }
                if (isset($data['notes'])) {
                    $sr->setNotes($data['notes']);
                }

                $manager->persist($sr);
                $serviceRecords[] = $sr;
                $count++;

                // Service Items
                if (isset($data['items'])) {
                    foreach ($data['items'] as [$type, $desc, $cost, $qty]) {
                        $si = new ServiceItem();
                        $si->setServiceRecord($sr);
                        $si->setType($type);
                        $si->setDescription($desc);
                        $si->setCost($cost);
                        $si->setQuantity($qty);
                        $manager->persist($si);
                        $itemCount++;
                    }
                }
            }
        }

        $manager->flush();
        echo "✓ Created $count service records with $itemCount line items\n";

        return $serviceRecords;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Parts
    // ─────────────────────────────────────────────────────────────────────

    /**
     * function loadParts
     *
     * @param ObjectManager $manager
     * @param array $vehicles
     * @param array $partCategories
     *
     * @return void
     */
    private function loadParts(ObjectManager $manager, array $vehicles, array $partCategories): void
    {
        $partsData = [
            // BMW 320d
            0 => [
                [
                    'name'        => 'Front Brake Pads (Brembo)',
                    'description' => 'Brembo P06075 front brake pads — low-dust ceramic compound',
                    'partNumber'  => 'P06075',
                    'manufacturer'=> 'Brembo',
                    'supplier'    => 'EuroCarParts',
                    'cost'        => '62.50',
                    'purchaseDate'=> '2025-06-12',
                    'installDate' => '2025-06-14',
                    'installMileage' => 43800,
                    'warranty'    => 24,
                    'category'    => 'Car:Brakes',
                ],
                [
                    'name'        => 'Rear Brake Discs (Pair)',
                    'description' => 'Genuine BMW rear brake discs — 300mm solid',
                    'partNumber'  => '34216864900',
                    'manufacturer'=> 'BMW',
                    'supplier'    => 'BMW Northampton',
                    'cost'        => '148.00',
                    'purchaseDate'=> '2025-06-12',
                    'installDate' => '2025-06-14',
                    'installMileage' => 43800,
                    'warranty'    => 24,
                    'category'    => 'Car:Brakes',
                ],
                [
                    'name'        => 'Michelin Pilot Sport 5 (Front)',
                    'description' => '225/45 R18 95Y Michelin Pilot Sport 5 — front axle',
                    'partNumber'  => 'MPS5-225/45R18',
                    'manufacturer'=> 'Michelin',
                    'supplier'    => 'Black Circles',
                    'cost'        => '132.00',
                    'quantity'    => 2,
                    'purchaseDate'=> '2025-08-20',
                    'installDate' => '2025-08-22',
                    'installMileage' => 45200,
                    'warranty'    => 60,
                    'category'    => 'Car:Tyres',
                ],
            ],
            // Kawasaki Z900
            1 => [
                [
                    'name'        => 'EBC Double-H Sintered Front Pads',
                    'description' => 'EBC FA252HH sintered front brake pads — track & road',
                    'partNumber'  => 'FA252HH',
                    'manufacturer'=> 'EBC Brakes',
                    'supplier'    => 'Wemoto',
                    'cost'        => '34.99',
                    'purchaseDate'=> '2025-05-20',
                    'installDate' => '2025-05-20',
                    'installMileage' => 9800,
                    'warranty'    => 12,
                    'category'    => 'Motorcycle:Brakes',
                ],
                [
                    'name'        => 'DID 525VX3 Gold Chain',
                    'description' => '525VX3 X-Ring chain, 114 links — gold/black',
                    'partNumber'  => '525VX3-114G',
                    'manufacturer'=> 'DID',
                    'supplier'    => 'SportsBikeShop',
                    'cost'        => '89.99',
                    'purchaseDate'=> '2025-07-10',
                    'installDate' => '2025-07-12',
                    'installMileage' => 10500,
                    'warranty'    => 12,
                    'category'    => 'Motorcycle:Chain & Sprockets',
                ],
                [
                    'name'        => 'Bridgestone S22 Rear Tyre',
                    'description' => '180/55 ZR17 Bridgestone Battlax S22 — rear',
                    'partNumber'  => 'S22-180/55ZR17',
                    'manufacturer'=> 'Bridgestone',
                    'supplier'    => 'The Tyre Barn',
                    'cost'        => '129.00',
                    'purchaseDate'=> '2025-07-10',
                    'installDate' => '2025-07-12',
                    'installMileage' => 10500,
                    'category'    => 'Motorcycle:Tyres',
                ],
            ],
            // Mazda MX-5 RF
            7 => [
                [
                    'name'        => 'NGK Spark Plugs (Set of 4)',
                    'description' => 'NGK ILKAR7L11 Iridium spark plugs — MX-5 2.0 SKYACTIV',
                    'partNumber'  => 'ILKAR7L11',
                    'manufacturer'=> 'NGK',
                    'supplier'    => 'Halfords',
                    'cost'        => '48.00',
                    'quantity'    => 4,
                    'purchaseDate'=> '2025-10-05',
                    'installDate' => '2025-10-05',
                    'installMileage' => 40000,
                    'warranty'    => 12,
                    'category'    => 'Car:Electrical',
                    'notes'       => 'Replaced during annual service at 40k miles',
                ],
            ],
        ];

        $count = 0;
        foreach ($partsData as $idx => $parts) {
            foreach ($parts as $data) {
                $part = new Part();
                $part->setVehicle($vehicles[$idx]);
                $part->setName($data['name']);
                $part->setDescription($data['description']);
                $part->setPartNumber($data['partNumber']);
                $part->setManufacturer($data['manufacturer']);
                $part->setSupplier($data['supplier']);
                $part->setCost($data['cost']);
                $part->setPurchaseDate(new \DateTime($data['purchaseDate']));

                if (isset($data['installDate'])) {
                    $part->setInstallationDate(new \DateTime($data['installDate']));
                }
                if (isset($data['installMileage'])) {
                    $part->setMileageAtInstallation($data['installMileage']);
                }
                if (isset($data['warranty'])) {
                    $part->setWarranty($data['warranty']);
                }
                if (isset($data['quantity'])) {
                    $part->setQuantity($data['quantity']);
                }
                if (isset($data['notes'])) {
                    $part->setNotes($data['notes']);
                }
                if (isset($data['category']) && isset($partCategories[$data['category']])) {
                    $part->setPartCategory($partCategories[$data['category']]);
                }

                $manager->persist($part);
                $count++;
            }
        }

        $manager->flush();
        echo "✓ Created $count parts\n";
    }

    // ─────────────────────────────────────────────────────────────────────
    // Consumables
    // ─────────────────────────────────────────────────────────────────────

    /**
     * function loadConsumables
     *
     * @param ObjectManager $manager
     * @param array $vehicles
     * @param array $consumableTypes
     *
     * @return void
     */
    private function loadConsumables(ObjectManager $manager, array $vehicles, array $consumableTypes): void
    {
        $consumablesData = [
            // BMW 320d
            0 => [
                ['Car:Engine Oil',      'Castrol Edge 5W-30 LL (BMW approved)',       'Castrol',  '52.00', '5.20', '2025-03-20', 42500, 10000, 52500],
                ['Car:Coolant',         'BMW OE Coolant Antifreeze (Blue)',            'BMW',      '18.50', '1.00', '2025-03-20', 42500, null,  null],
                ['Car:Brake Fluid',     'ATE SL.6 DOT 4 Brake Fluid',                'ATE',      '12.00', '0.50', '2024-03-15', 32800, 20000, 52800],
                ['Car:Windscreen Wash', 'Autoglym Screen Wash Concentrate',           'Autoglym', '6.99',  '5.00', '2025-09-01', 45500, null,  null],
            ],
            // Kawasaki Z900
            1 => [
                ['Motorcycle:Engine Oil', 'Kawasaki 10W-40 Semi-Synthetic',           'Kawasaki', '32.00', '3.80', '2025-04-10', 9200,  6000,  15200],
                ['Motorcycle:Chain Lube', 'Motul C2 Chain Lube',                      'Motul',    '8.50',  '0.40', '2025-08-01', 11000, 500,   11500],
                ['Motorcycle:Coolant',    'Silkolene Pro Cool (Premixed)',             'Silkolene','14.00', '1.50', '2025-04-10', 9200,  null,  null],
                ['Motorcycle:Brake Fluid','Castrol React SRF DOT 4',                  'Castrol',  '22.00', '0.25', '2025-04-10', 9200,  12000, 21200],
            ],
            // Transit Custom
            4 => [
                ['Van:Engine Oil', 'Ford Motorcraft 5W-30 Formula F',                 'Ford',     '45.00', '6.00', '2025-07-15', 56000, 12000, 68000],
                ['Van:AdBlue',     'GreenChem AdBlue (10L)',                           'GreenChem','18.00', '10.00','2025-07-15', 56000, null,  null],
                ['Van:Coolant',    'Ford Motorcraft Coolant (Orange)',                 'Ford',     '15.00', '1.00', '2025-07-15', 56000, null,  null],
            ],
            // Tesla Model 3 — minimal consumables
            5 => [
                ['EV:Windscreen Wash', 'Tesla OE Screen Wash Concentrate',            'Tesla',    '8.00',  '3.00', '2025-06-01', 10000, null,  null],
            ],
            // Honda CB650R
            6 => [
                ['Motorcycle:Engine Oil', 'Honda Pro Honda GN4 10W-30',               'Honda',    '28.00', '3.50', '2025-03-20', 5200,  8000,  13200],
                ['Motorcycle:Chain Lube', 'Motul C4 Factory Line Chain Lube',          'Motul',    '9.50',  '0.40', '2025-07-01', 6500,  500,   7000],
            ],
            // Mazda MX-5 RF
            7 => [
                ['Car:Engine Oil',      'Mazda Original Oil Supra 0W-20',             'Mazda',    '38.00', '4.20', '2025-10-05', 40000, 8000,  48000],
                ['Car:Coolant',         'Mazda FL22 Coolant (Premixed)',               'Mazda',    '16.00', '1.00', '2025-10-05', 40000, null,  null],
                ['Car:Brake Fluid',     'Motul DOT 5.1 Brake Fluid',                  'Motul',    '14.00', '0.50', '2024-10-01', 32500, 16000, 48500],
            ],
        ];

        $count = 0;
        foreach ($consumablesData as $idx => $items) {
            foreach ($items as [$typeKey, $desc, $brand, $cost, $qty, $lastChanged, $mileageAtChange, $interval, $nextMileage]) {
                $consumable = new Consumable();
                $consumable->setVehicle($vehicles[$idx]);
                $consumable->setDescription($desc);
                $consumable->setBrand($brand);
                $consumable->setCost($cost);
                $consumable->setQuantity($qty);
                $consumable->setLastChanged(new \DateTime($lastChanged));
                $consumable->setMileageAtChange($mileageAtChange);

                if ($interval !== null) {
                    $consumable->setReplacementInterval($interval);
                }
                if ($nextMileage !== null) {
                    $consumable->setNextReplacement($nextMileage);
                }

                if (isset($consumableTypes[$typeKey])) {
                    $consumable->setConsumableType($consumableTypes[$typeKey]);
                }

                $manager->persist($consumable);
                $count++;
            }
        }

        $manager->flush();
        echo "✓ Created $count consumable records\n";
    }

    // ─────────────────────────────────────────────────────────────────────
    // MOT Records
    // ─────────────────────────────────────────────────────────────────────

    /**
     * function loadMotRecords
     *
     * @param ObjectManager $manager
     * @param array $vehicles
     *
     * @return void
     */
    private function loadMotRecords(ObjectManager $manager, array $vehicles): void
    {
        $motData = [
            // BMW 320d — first MOT at 3 years
            0 => [
                [
                    'date'    => '2024-03-12',
                    'result'  => 'Pass',
                    'cost'    => '54.85',
                    'mileage' => 32500,
                    'center'  => 'BMW Northampton',
                    'expiry'  => '2025-03-11',
                    'testNo'  => '4827619350',
                    'advisories' => json_encode([
                        'Front brake disc worn but not excessively (1.1.14)',
                        'Nearside rear tyre slightly worn on inner edge (5.2.3)',
                    ]),
                ],
                [
                    'date'    => '2025-03-10',
                    'result'  => 'Pass',
                    'cost'    => '54.85',
                    'mileage' => 42300,
                    'center'  => 'BMW Northampton',
                    'expiry'  => '2026-03-09',
                    'testNo'  => '5193847260',
                    'advisories' => json_encode([
                        'Offside front tyre approaching legal limit (5.2.3)',
                    ]),
                ],
            ],
            // Kawasaki Z900 — first MOT at 3 years (2025)
            1 => [
                [
                    'date'    => '2025-05-28',
                    'result'  => 'Pass',
                    'cost'    => '29.65',
                    'mileage' => 9800,
                    'center'  => 'Moto Rapido',
                    'expiry'  => '2026-05-27',
                    'testNo'  => '6482013579',
                    'advisories' => null,
                ],
            ],
            // Ford Focus — multiple MOTs (older vehicle)
            2 => [
                [
                    'date'    => '2023-09-18',
                    'result'  => 'Fail',
                    'cost'    => '54.85',
                    'repair'  => '185.00',
                    'mileage' => 95000,
                    'center'  => 'Halfords Autocentre, Northampton',
                    'testNo'  => '3847261590',
                    'failures' => json_encode([
                        'Nearside front suspension arm ball joint worn (5.3.4)',
                        'Exhaust emissions CO above permitted level (8.2.1.2)',
                    ]),
                    'repairDetails' => 'Replaced N/S lower arm ball joint. Replaced catalytic converter.',
                ],
                [
                    'date'    => '2023-09-22',
                    'result'  => 'Pass',
                    'cost'    => '0.00',
                    'mileage' => 95010,
                    'center'  => 'Halfords Autocentre, Northampton',
                    'expiry'  => '2024-09-21',
                    'testNo'  => '3847261598',
                    'isRetest' => true,
                    'advisories' => json_encode([
                        'Rear brake pads wearing thin but serviceable (1.1.13)',
                    ]),
                ],
                [
                    'date'    => '2024-09-19',
                    'result'  => 'Pass',
                    'cost'    => '54.85',
                    'mileage' => 101200,
                    'center'  => 'Halfords Autocentre, Northampton',
                    'expiry'  => '2025-09-18',
                    'testNo'  => '4918273650',
                    'advisories' => json_encode([
                        'Offside rear tyre worn close to legal limit (5.2.3)',
                        'Minor oil leak from rocker cover gasket (8.4.1)',
                    ]),
                ],
            ],
            // Transit Custom
            4 => [
                [
                    'date'    => '2023-02-10',
                    'result'  => 'Pass',
                    'cost'    => '54.85',
                    'mileage' => 38000,
                    'center'  => 'Quick-Fit, Northampton',
                    'expiry'  => '2024-02-09',
                    'testNo'  => '3659182740',
                    'advisories' => json_encode([
                        'Slight play in offside front track rod end (2.1.3)',
                    ]),
                ],
                [
                    'date'    => '2024-02-08',
                    'result'  => 'Pass',
                    'cost'    => '54.85',
                    'mileage' => 50200,
                    'center'  => 'Quick-Fit, Northampton',
                    'expiry'  => '2025-02-07',
                    'testNo'  => '4827361950',
                    'advisories' => null,
                ],
                [
                    'date'    => '2025-02-06',
                    'result'  => 'Pass',
                    'cost'    => '54.85',
                    'mileage' => 53500,
                    'center'  => 'Quick-Fit, Northampton',
                    'expiry'  => '2026-02-05',
                    'testNo'  => '5739284610',
                    'advisories' => json_encode([
                        'Front brake discs lightly corroded but within tolerance (1.1.14)',
                        'Slight oil mist on engine underside (8.4.1)',
                    ]),
                ],
            ],
            // Honda CB650R — not yet 3 years old, no MOT required
            // Mazda MX-5 RF — first MOT at 3 years (2022 registered)
            7 => [
                [
                    'date'    => '2025-03-08',
                    'result'  => 'Pass',
                    'cost'    => '54.85',
                    'mileage' => 36200,
                    'center'  => 'Mazda Northampton',
                    'expiry'  => '2026-03-07',
                    'testNo'  => '5482916370',
                    'advisories' => null,
                ],
            ],
        ];

        $count = 0;
        foreach ($motData as $idx => $records) {
            foreach ($records as $data) {
                $mot = new MotRecord();
                $mot->setVehicle($vehicles[$idx]);
                $mot->setTestDate(new \DateTime($data['date']));
                $mot->setResult($data['result']);
                $mot->setTestCost($data['cost']);
                $mot->setMileage($data['mileage']);
                $mot->setTestCenter($data['center']);
                $mot->setMotTestNumber($data['testNo'] ?? null);

                if (isset($data['expiry'])) {
                    $mot->setExpiryDate(new \DateTime($data['expiry']));
                }
                if (isset($data['repair'])) {
                    $mot->setRepairCost($data['repair']);
                }
                if (isset($data['advisories'])) {
                    $mot->setAdvisories($data['advisories']);
                }
                if (isset($data['failures'])) {
                    $mot->setFailures($data['failures']);
                }
                if (isset($data['repairDetails'])) {
                    $mot->setRepairDetails($data['repairDetails']);
                }
                if (isset($data['isRetest'])) {
                    $mot->setIsRetest($data['isRetest']);
                }

                $manager->persist($mot);
                $count++;
            }
        }

        $manager->flush();
        echo "✓ Created $count MOT records\n";
    }

    // ─────────────────────────────────────────────────────────────────────
    // Insurance Policies
    // ─────────────────────────────────────────────────────────────────────

    /**
     * function loadInsurancePolicies
     *
     * @param ObjectManager $manager
     * @param array $vehicles
     * @param array $users
     *
     * @return void
     */
    private function loadInsurancePolicies(ObjectManager $manager, array $vehicles, array $users): void
    {
        $admin = $users['demo-admin@vehicle.local'];
        $john  = $users['john.smith@example.com'];
        $sarah = $users['sarah.jones@example.com'];
        $mike  = $users['mike.wilson@example.com'];

        $policiesData = [
            // Admin — multi-vehicle policy covering BMW + Z900
            [
                'provider'     => 'Admiral MultiCar',
                'policyNumber' => 'ADM-MC-2025-48271',
                'annualCost'   => '1240.00',
                'ncdYears'     => 9,
                'startDate'    => '2025-03-15',
                'expiryDate'   => '2026-03-14',
                'coverageType' => 'Comprehensive',
                'excess'       => '350.00',
                'mileageLimit' => 12000,
                'holderId'     => null,
                'autoRenewal'  => true,
                'vehicles'     => [0, 1], // BMW + Z900
                'notes'        => 'Multi-car policy. NCD protected. Includes breakdown cover.',
            ],
            // John — family car
            [
                'provider'     => 'Direct Line',
                'policyNumber' => 'DL-2023-VW-91834',
                'annualCost'   => '680.00',
                'ncdYears'     => 6,
                'startDate'    => '2025-09-01',
                'expiryDate'   => '2026-08-31',
                'coverageType' => 'Comprehensive',
                'excess'       => '250.00',
                'mileageLimit' => 10000,
                'autoRenewal'  => true,
                'vehicles'     => [3], // Tiguan
                'notes'        => 'Includes spouse as named driver.',
            ],
            // John — work van (commercial)
            [
                'provider'     => 'Aviva Commercial',
                'policyNumber' => 'AV-COM-2020-FT-38492',
                'annualCost'   => '920.00',
                'ncdYears'     => 4,
                'startDate'    => '2025-02-14',
                'expiryDate'   => '2026-02-13',
                'coverageType' => 'Comprehensive',
                'excess'       => '500.00',
                'mileageLimit' => 20000,
                'autoRenewal'  => false,
                'vehicles'     => [4], // Transit
                'notes'        => 'Commercial vehicle policy. Includes goods in transit cover up to £10,000.',
            ],
            // Sarah — Tesla
            [
                'provider'     => 'Tesla Insurance',
                'policyNumber' => 'TSL-INS-2024-M3-72910',
                'annualCost'   => '890.00',
                'ncdYears'     => 5,
                'startDate'    => '2025-01-10',
                'expiryDate'   => '2026-01-09',
                'coverageType' => 'Comprehensive',
                'excess'       => '300.00',
                'mileageLimit' => 10000,
                'autoRenewal'  => true,
                'vehicles'     => [5], // Tesla
                'notes'        => 'Tesla Insurance — includes battery and charging equipment cover.',
            ],
            // Sarah — Honda bike
            [
                'provider'     => 'Bennetts',
                'policyNumber' => 'BEN-MC-2023-CB-54891',
                'annualCost'   => '420.00',
                'ncdYears'     => 3,
                'startDate'    => '2025-04-15',
                'expiryDate'   => '2026-04-14',
                'coverageType' => 'Comprehensive',
                'excess'       => '200.00',
                'mileageLimit' => 5000,
                'autoRenewal'  => true,
                'vehicles'     => [6], // CB650R
                'notes'        => 'Motorcycle policy — includes helmet & leathers cover up to £1,500.',
            ],
            // Mike — MX-5
            [
                'provider'     => 'Hastings Direct',
                'policyNumber' => 'HD-2022-MX5-63817',
                'annualCost'   => '520.00',
                'ncdYears'     => 7,
                'startDate'    => '2025-03-10',
                'expiryDate'   => '2026-03-09',
                'coverageType' => 'Comprehensive',
                'excess'       => '300.00',
                'mileageLimit' => 8000,
                'autoRenewal'  => false,
                'vehicles'     => [7], // MX-5
                'notes'        => 'Enthusiast policy. Includes track day cover (3 days/year).',
            ],
        ];

        $count = 0;
        foreach ($policiesData as $data) {
            $policy = new InsurancePolicy();
            $policy->setProvider($data['provider']);
            $policy->setPolicyNumber($data['policyNumber']);
            $policy->setAnnualCost($data['annualCost']);
            $policy->setNcdYears($data['ncdYears']);
            $policy->setStartDate(new \DateTime($data['startDate']));
            $policy->setExpiryDate(new \DateTime($data['expiryDate']));
            $policy->setCoverageType($data['coverageType']);
            $policy->setExcess($data['excess']);
            $policy->setMileageLimit($data['mileageLimit']);
            $policy->setAutoRenewal($data['autoRenewal']);
            $policy->setNotes($data['notes']);

            foreach ($data['vehicles'] as $vIdx) {
                $policy->addVehicle($vehicles[$vIdx]);
            }

            $manager->persist($policy);
            $count++;
        }

        $manager->flush();
        echo "✓ Created $count insurance policies\n";
    }

    // ─────────────────────────────────────────────────────────────────────
    // Road Tax
    // ─────────────────────────────────────────────────────────────────────

    /**
     * function loadRoadTax
     *
     * @param ObjectManager $manager
     * @param array $vehicles
     *
     * @return void
     */
    private function loadRoadTax(ObjectManager $manager, array $vehicles): void
    {
        $taxData = [
            // BMW 320d — standard VED
            [0, '2025-04-01', '2026-03-31', '180.00', 'annual', false, 'Band H — Diesel, 2021 registration'],
            [0, '2024-04-01', '2025-03-31', '180.00', 'annual', false, null],
            // Kawasaki Z900 — motorcycle VED
            [1, '2025-06-01', '2026-05-31', '107.00', 'annual', false, 'Motorcycle over 600cc'],
            // Ford Focus — SORN'd when sold
            [2, '2024-05-01', null, '0.00', 'annual', true, 'Vehicle SORN — declared off-road after sale'],
            // VW Tiguan — new, first year paid at purchase
            [3, '2025-09-01', '2026-08-31', '180.00', 'annual', false, 'Standard rate VED — paid at registration'],
            // Transit Custom — commercial
            [4, '2025-03-01', '2026-02-28', '290.00', 'annual', false, 'Light goods vehicle rate'],
            // Tesla Model 3 — zero emissions
            [5, '2025-01-10', '2026-01-09', '0.00', 'annual', false, 'Zero emissions — no VED payable'],
            // Honda CB650R
            [6, '2025-05-01', '2026-04-30', '107.00', 'annual', false, 'Motorcycle over 600cc'],
            // Mazda MX-5 RF
            [7, '2025-04-01', '2026-03-31', '165.00', 'annual', false, 'Band G — Petrol, 2019 registration'],
        ];

        $count = 0;
        foreach ($taxData as [$vIdx, $start, $expiry, $amount, $freq, $sorn, $notes]) {
            $tax = new RoadTax();
            $tax->setVehicle($vehicles[$vIdx]);
            $tax->setStartDate(new \DateTime($start));
            if ($expiry !== null) {
                $tax->setExpiryDate(new \DateTime($expiry));
            }
            $tax->setAmount($amount);
            $tax->setFrequency($freq);
            $tax->setSorn($sorn);
            if ($notes !== null) {
                $tax->setNotes($notes);
            }
            $manager->persist($tax);
            $count++;
        }

        $manager->flush();
        echo "✓ Created $count road tax records\n";
    }

    // ─────────────────────────────────────────────────────────────────────
    // Todos
    // ─────────────────────────────────────────────────────────────────────

    /**
     * function loadTodos
     *
     * @param ObjectManager $manager
     * @param array $vehicles
     *
     * @return void
     */
    private function loadTodos(ObjectManager $manager, array $vehicles): void
    {
        $todosData = [
            // BMW 320d
            [0, 'Replace front tyres', 'Offside front approaching legal limit — noted at last MOT. Need 255/40 R18.', false, '2026-02-01'],
            [0, 'Book annual service', 'Due March 2026 at BMW Northampton. Call to book.', false, '2026-02-15'],
            [0, 'Refill AdBlue', 'Dashboard warning appeared. Top up before next long journey.', true, null],
            // Kawasaki Z900
            [1, 'Lubricate chain', 'Chain needs lube after last wet ride — every 500 miles.', false, null],
            [1, 'Winter storage prep', 'Apply ACF-50, stabilise fuel, connect battery tender, cover.', false, '2025-11-01'],
            // VW Tiguan
            [3, 'Fit winter tyres', 'Swap to Continental WinterContact TS860 set — in garage.', false, '2025-11-15'],
            [3, 'Renew parking permit', 'Office parking permit expires end of month.', false, '2025-12-28'],
            // Transit Custom
            [4, 'Replace windscreen wipers', 'Streaking in rain — needs new Bosch Aerotwin pair.', false, null],
            [4, 'Fix sliding door rattle', 'Passenger-side sliding door rattles at speed. Check runner alignment.', false, '2026-01-15'],
            // Tesla Model 3
            [5, 'Book tyre rotation', 'Due at 20,000 miles. Tesla service centre or mobile service.', false, '2026-03-01'],
            // Honda CB650R
            [6, 'Adjust chain tension', 'Chain slightly loose — adjust to 25-35mm free play.', false, null],
            // Mazda MX-5
            [7, 'Inspect soft-top seals', 'Slight damp in boot after heavy rain — check rear window seal.', false, '2025-12-01'],
            [7, 'Touch up stone chips', 'Small chips on bonnet — order Soul Red Crystal touch-up paint from Mazda.', false, null],
        ];

        $count = 0;
        foreach ($todosData as [$vIdx, $title, $desc, $done, $dueDate]) {
            $todo = new Todo();
            $todo->setVehicle($vehicles[$vIdx]);
            $todo->setTitle($title);
            $todo->setDescription($desc);
            $todo->setDone($done);
            if ($dueDate !== null) {
                $todo->setDueDate(new \DateTime($dueDate));
            }
            if ($done) {
                $todo->setCompletedBy(new \DateTime('-' . rand(1, 30) . ' days'));
            }
            $manager->persist($todo);
            $count++;
        }

        $manager->flush();
        echo "✓ Created $count todos\n";
    }

    // ─────────────────────────────────────────────────────────────────────
    // Vehicle Status History
    // ─────────────────────────────────────────────────────────────────────

    /**
     * function loadStatusHistory
     *
     * @param ObjectManager $manager
     * @param array $vehicles
     * @param array $users
     *
     * @return void
     */
    private function loadStatusHistory(ObjectManager $manager, array $vehicles, array $users): void
    {
        $admin = $users['demo-admin@vehicle.local'];

        $historyData = [
            // Ford Focus — purchased → live → SORN → Sold
            [2, $admin, '',     'Live', '2017-09-20', 'Purchased from private seller'],
            [2, $admin, 'Live', 'SORN', '2024-06-01', 'Declared SORN — no longer in daily use'],
            [2, $admin, 'SORN', 'Sold', '2024-07-15', 'Sold via Auto Trader for £2,800'],
        ];

        $count = 0;
        foreach ($historyData as [$vIdx, $user, $oldStatus, $newStatus, $date, $notes]) {
            $history = new VehicleStatusHistory();
            $history->setVehicle($vehicles[$vIdx]);
            $history->setUser($user);
            $history->setOldStatus($oldStatus);
            $history->setNewStatus($newStatus);
            $history->setChangeDate(new \DateTime($date));
            $history->setNotes($notes);
            $manager->persist($history);
            $count++;
        }

        $manager->flush();
        echo "✓ Created $count status history entries\n";
    }

    // ─────────────────────────────────────────────────────────────────────
    // Feature Flags
    // ─────────────────────────────────────────────────────────────────────

    /**
     * function loadFeatureFlags
     *
     * @param ObjectManager $manager
     *
     * @return array
     */
    private function loadFeatureFlags(ObjectManager $manager): array
    {
        $flagsData = [
            // Dashboard
            ['dashboard.fuel_chart',          'Fuel Cost Chart',            'dashboard',    true,  10],
            ['dashboard.mileage_chart',       'Mileage Chart',             'dashboard',    true,  20],
            ['dashboard.expense_breakdown',   'Expense Breakdown Pie',     'dashboard',    true,  30],
            ['dashboard.upcoming_reminders',  'Upcoming Reminders',        'dashboard',    true,  40],
            ['dashboard.recent_activity',     'Recent Activity Feed',      'dashboard',    true,  50],
            // Vehicles
            ['vehicles.vin_decoder',          'VIN Decoder',               'vehicles',     true,  10],
            ['vehicles.dvla_lookup',          'DVLA Registration Lookup',  'vehicles',     true,  20],
            ['vehicles.spec_scraping',        'Specification Scraping',    'vehicles',     true,  30],
            ['vehicles.image_gallery',        'Image Gallery',             'vehicles',     true,  40],
            ['vehicles.depreciation',         'Depreciation Calculator',   'vehicles',     true,  50],
            ['vehicles.status_tracking',      'Status Change Tracking',    'vehicles',     true,  60],
            // Records
            ['records.fuel_tracking',         'Fuel Record Tracking',      'records',      true,  10],
            ['records.service_history',       'Service History',           'records',      true,  20],
            ['records.parts_inventory',       'Parts Inventory',           'records',      true,  30],
            ['records.consumables',           'Consumables Tracking',      'records',      true,  40],
            ['records.mot_history',           'MOT History',               'records',      true,  50],
            ['records.insurance',             'Insurance Policies',        'records',      true,  60],
            ['records.road_tax',              'Road Tax Tracking',         'records',      true,  70],
            ['records.todos',                 'Vehicle Todos',             'records',      true,  80],
            // Reports
            ['reports.pdf_export',            'PDF Report Export',         'reports',      true,  10],
            ['reports.excel_export',          'Excel Report Export',       'reports',      true,  20],
            ['reports.cost_analysis',         'Cost Analysis Report',      'reports',      true,  30],
            ['reports.vehicle_history',       'Vehicle History Report',    'reports',      true,  40],
            // Import / Export
            ['import.csv',                    'CSV Import',                'import_export',true,  10],
            ['import.json',                   'JSON Import',               'import_export',true,  20],
            ['export.csv',                    'CSV Export',                'import_export', true,  30],
            ['export.json',                   'JSON Export',               'import_export', true,  40],
            // Notifications
            ['notifications.mot_expiry',      'MOT Expiry Reminders',     'notifications',true,  10],
            ['notifications.insurance_expiry','Insurance Expiry Alerts',  'notifications',true,  20],
            ['notifications.tax_expiry',      'Road Tax Expiry Alerts',   'notifications',true,  30],
            ['notifications.service_due',     'Service Due Reminders',    'notifications',true,  40],
            // Mobile
            ['mobile.offline_mode',           'Offline Mode',              'mobile',       true,  10],
            ['mobile.push_notifications',     'Push Notifications',        'mobile',       false, 20],
            ['mobile.receipt_ocr',            'Receipt OCR Scanning',      'mobile',       false, 30],
            ['mobile.biometric_login',        'Biometric Login',           'mobile',       false, 40],
            // Admin
            ['admin.user_management',         'User Management',           'admin',        true,  10],
            ['admin.vehicle_assignments',     'Vehicle Assignments',       'admin',        true,  20],
            ['admin.feature_flags',           'Feature Flag Management',   'admin',        true,  30],
            ['admin.system_health',           'System Health Dashboard',   'admin',        true,  40],
            // Experimental
            ['experimental.ai_insights',      'AI Cost Insights',          'experimental', false, 10],
            ['experimental.route_tracking',   'Route Tracking (GPS)',      'experimental', false, 20],
            ['experimental.marketplace',      'Parts Marketplace',         'experimental', false, 30],
        ];

        $flags = [];
        foreach ($flagsData as [$key, $label, $category, $enabled, $sort]) {
            $flag = new FeatureFlag();
            $flag->setFeatureKey($key);
            $flag->setLabel($label);
            $flag->setCategory($category);
            $flag->setDefaultEnabled($enabled);
            $flag->setSortOrder($sort);
            $manager->persist($flag);
            $flags[$key] = $flag;
        }

        $manager->flush();
        echo "✓ Created " . count($flags) . " feature flags\n";

        return $flags;
    }

    // ─────────────────────────────────────────────────────────────────────
    // User Feature Overrides
    // ─────────────────────────────────────────────────────────────────────

    /**
     * function loadUserFeatureOverrides
     *
     * @param ObjectManager $manager
     * @param array $users
     * @param array $featureFlags
     *
     * @return void
     */
    private function loadUserFeatureOverrides(ObjectManager $manager, array $users, array $featureFlags): void
    {
        $admin = $users['demo-admin@vehicle.local'];

        // Admin gets all experimental features enabled
        $overrides = [
            ['demo-admin@vehicle.local', 'experimental.ai_insights',   true],
            ['demo-admin@vehicle.local', 'experimental.route_tracking',true],
            ['demo-admin@vehicle.local', 'experimental.marketplace',   true],
            ['demo-admin@vehicle.local', 'mobile.receipt_ocr',         true],
            // Disable push notifications for one user
            ['sarah.jones@example.com',  'mobile.push_notifications',  false],
            // Enable OCR for Mike
            ['mike.wilson@example.com',  'mobile.receipt_ocr',         true],
        ];

        $count = 0;
        foreach ($overrides as [$email, $flagKey, $enabled]) {
            if (!isset($users[$email]) || !isset($featureFlags[$flagKey])) {
                continue;
            }

            $override = new UserFeatureOverride();
            $override->setUser($users[$email]);
            $override->setFeatureFlag($featureFlags[$flagKey]);
            $override->setEnabled($enabled);
            $override->setSetBy($admin);
            $manager->persist($override);
            $count++;
        }

        $manager->flush();
        echo "✓ Created $count user feature overrides\n";
    }

    // ─────────────────────────────────────────────────────────────────────
    // Vehicle Assignments
    // ─────────────────────────────────────────────────────────────────────

    /**
     * function loadVehicleAssignments
     *
     * @param ObjectManager $manager
     * @param array $vehicles
     * @param array $users
     *
     * @return void
     */
    private function loadVehicleAssignments(ObjectManager $manager, array $vehicles, array $users): void
    {
        $admin = $users['demo-admin@vehicle.local'];
        $john  = $users['john.smith@example.com'];
        $sarah = $users['sarah.jones@example.com'];

        $assignments = [
            // Admin shares BMW view access with John
            [0, 'john.smith@example.com', true, false, false, false],
            // Admin shares Z900 with Sarah (view + add records)
            [1, 'sarah.jones@example.com', true, false, true, false],
            // John shares Tiguan with admin (full access)
            [3, 'demo-admin@vehicle.local', true, true, true, false],
            // Sarah shares Tesla with admin (view only)
            [5, 'demo-admin@vehicle.local', true, false, false, false],
        ];

        $count = 0;
        foreach ($assignments as [$vIdx, $assigneeEmail, $canView, $canEdit, $canAdd, $canDelete]) {
            $assignment = new VehicleAssignment();
            $assignment->setVehicle($vehicles[$vIdx]);
            $assignment->setAssignedTo($users[$assigneeEmail]);
            $assignment->setAssignedBy($vehicles[$vIdx]->getOwner());
            $assignment->setCanView($canView);
            $assignment->setCanEdit($canEdit);
            $assignment->setCanAddRecords($canAdd);
            $assignment->setCanDelete($canDelete);
            $manager->persist($assignment);
            $count++;
        }

        $manager->flush();
        echo "✓ Created $count vehicle assignments\n";
    }

    // ─────────────────────────────────────────────────────────────────────
    // Reports
    // ─────────────────────────────────────────────────────────────────────

    /**
     * function loadReports
     *
     * @param ObjectManager $manager
     * @param array $vehicles
     * @param array $users
     *
     * @return void
     */
    private function loadReports(ObjectManager $manager, array $vehicles, array $users): void
    {
        $admin = $users['demo-admin@vehicle.local'];
        $john  = $users['john.smith@example.com'];

        $reportsData = [
            [
                'user'     => $admin,
                'name'     => 'BMW 320d — Full Cost Analysis 2025',
                'template' => 'cost_analysis',
                'vehicleIdx' => 0,
                'payload'  => ['year' => 2025, 'includeDepreciation' => true],
            ],
            [
                'user'     => $admin,
                'name'     => 'BMW 320d — Vehicle History Report',
                'template' => 'vehicle_history',
                'vehicleIdx' => 0,
                'payload'  => ['includeSpecs' => true, 'includeMot' => true, 'includeServices' => true],
            ],
            [
                'user'     => $admin,
                'name'     => 'Kawasaki Z900 — Season Summary 2025',
                'template' => 'cost_analysis',
                'vehicleIdx' => 1,
                'payload'  => ['year' => 2025, 'includeDepreciation' => false],
            ],
            [
                'user'     => $john,
                'name'     => 'VW Tiguan — Running Costs 2025',
                'template' => 'cost_analysis',
                'vehicleIdx' => 3,
                'payload'  => ['year' => 2025, 'includeDepreciation' => true],
            ],
            [
                'user'     => $john,
                'name'     => 'Transit Custom — Business Mileage Report',
                'template' => 'vehicle_history',
                'vehicleIdx' => 4,
                'payload'  => ['year' => 2025, 'includeSpecs' => false, 'includeMot' => true, 'includeServices' => true],
            ],
        ];

        $count = 0;
        foreach ($reportsData as $data) {
            $report = new Report();
            $report->setUser($data['user']);
            $report->setName($data['name']);
            $report->setTemplateKey($data['template']);
            $report->setPayload($data['payload']);
            if (isset($vehicles[$data['vehicleIdx']])) {
                $report->setVehicleId($vehicles[$data['vehicleIdx']]->getId());
            }
            $report->setGeneratedAt(new \DateTime('-' . rand(1, 60) . ' days'));
            $manager->persist($report);
            $count++;
        }

        $manager->flush();
        echo "✓ Created $count saved reports\n";
    }
}
