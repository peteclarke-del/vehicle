<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Vehicle;
use App\Entity\VehicleType;
use App\Entity\VehicleMake;
use App\Entity\VehicleModel;
use App\Entity\FuelRecord;
use App\Entity\Part;
use App\Entity\Consumable;
use App\Entity\ConsumableType;
use App\Entity\ServiceRecord;
use App\Entity\MotRecord;
use App\Entity\InsurancePolicy;
use App\Entity\RoadTax;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/vehicles')]
#[IsGranted('ROLE_USER')]
class VehicleImportExportController extends AbstractController
{
    private function getUserEntity(): ?\App\Entity\User
    {
        $user = $this->getUser();
        return $user instanceof \App\Entity\User ? $user : null;
    }

    #[Route('/export', name: 'vehicles_export', methods: ['GET'])]
    public function export(EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUserEntity();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }
        $vehicles = $entityManager->getRepository(Vehicle::class)->findBy(['owner' => $user]);

        $data = [];
        foreach ($vehicles as $vehicle) {
            // Export fuel records
            $fuelRecords = [];
            foreach ($vehicle->getFuelRecords() as $fuelRecord) {
                $fuelRecords[] = [
                    'date' => $fuelRecord->getDate()?->format('Y-m-d'),
                    'litres' => $fuelRecord->getLitres(),
                    'cost' => $fuelRecord->getCost(),
                    'mileage' => $fuelRecord->getMileage(),
                    'fuelType' => $fuelRecord->getFuelType(),
                    'station' => $fuelRecord->getStation(),
                    'notes' => $fuelRecord->getNotes(),
                ];
            }

            // Export parts
            $parts = [];
            foreach ($vehicle->getParts() as $part) {
                $parts[] = [
                    'purchaseDate' => $part->getPurchaseDate()?->format('Y-m-d'),
                    'description' => $part->getDescription(),
                    'partNumber' => $part->getPartNumber(),
                    'manufacturer' => $part->getManufacturer(),
                    'cost' => $part->getCost(),
                    'category' => $part->getCategory(),
                    'installationDate' => $part->getInstallationDate()?->format('Y-m-d'),
                    'mileageAtInstallation' => $part->getMileageAtInstallation(),
                    'notes' => $part->getNotes(),
                ];
            }

            // Export consumables
            $consumables = [];
            foreach ($vehicle->getConsumables() as $consumable) {
                $consumables[] = [
                    'consumableType' => $consumable->getConsumableType()->getName(),
                    'specification' => $consumable->getSpecification(),
                    'quantity' => $consumable->getQuantity(),
                    'lastChanged' => $consumable->getLastChanged()?->format('Y-m-d'),
                    'mileageAtChange' => $consumable->getMileageAtChange(),
                    'cost' => $consumable->getCost(),
                    'notes' => $consumable->getNotes(),
                ];
            }

            // Export service records
            $serviceRecords = $entityManager->getRepository(ServiceRecord::class)
                ->findBy(['vehicle' => $vehicle], ['serviceDate' => 'ASC']);
            $serviceRecordsData = [];
            foreach ($serviceRecords as $serviceRecord) {
                $serviceRecordsData[] = [
                    'serviceDate' => $serviceRecord->getServiceDate()?->format('Y-m-d'),
                    'serviceType' => $serviceRecord->getServiceType(),
                    'laborCost' => $serviceRecord->getLaborCost(),
                    'partsCost' => $serviceRecord->getPartsCost(),
                    'mileage' => $serviceRecord->getMileage(),
                    'serviceProvider' => $serviceRecord->getServiceProvider(),
                    'workPerformed' => $serviceRecord->getWorkPerformed(),
                    'notes' => $serviceRecord->getNotes(),
                ];
            }

            // Export MOT records
            $motRecords = $entityManager->getRepository(MotRecord::class)
                ->findBy(['vehicle' => $vehicle], ['testDate' => 'ASC']);
            $motRecordsData = [];
            foreach ($motRecords as $motRecord) {
                $motRecordsData[] = [
                    'testDate' => $motRecord->getTestDate()?->format('Y-m-d'),
                    'result' => $motRecord->getResult(),
                    'testCost' => $motRecord->getTestCost(),
                    'repairCost' => $motRecord->getRepairCost(),
                    'mileage' => $motRecord->getMileage(),
                    'testCenter' => $motRecord->getTestCenter(),
                    'advisories' => $motRecord->getAdvisories(),
                    'failures' => $motRecord->getFailures(),
                    'repairDetails' => $motRecord->getRepairDetails(),
                    'notes' => $motRecord->getNotes(),
                ];
            }

            // Export insurance records
            $insurancePolicies = $entityManager->getRepository(InsurancePolicy::class)->findAll();
            $insuranceRecordsData = [];
            foreach ($insurancePolicies as $policy) {
                if ($policy->getVehicles()->contains($vehicle)) {
                    // Only export the policy under the vehicle with the smallest ID to avoid duplicates
                    $vehicleIds = array_map(fn($v) => $v->getId(), $policy->getVehicles()->toArray());
                    sort($vehicleIds);
                    if ($vehicle->getId() === $vehicleIds[0]) {
                        $insuranceRecordsData[] = [
                            'provider' => $policy->getProvider(),
                            'policyNumber' => $policy->getPolicyNumber(),
                            'coverageType' => $policy->getCoverageType(),
                            'annualCost' => $policy->getAnnualCost(),
                            'startDate' => $policy->getStartDate()?->format('Y-m-d'),
                            'expiryDate' => $policy->getExpiryDate()?->format('Y-m-d'),
                            'notes' => $policy->getNotes(),
                            'vehicleIds' => $vehicleIds,
                        ];
                    }
                }
            }

            // Export road tax records
            $roadTaxRecords = $entityManager->getRepository(RoadTax::class)
                ->findBy(['vehicle' => $vehicle], ['startDate' => 'ASC']);
            $roadTaxRecordsData = [];
            foreach ($roadTaxRecords as $roadTax) {
                $roadTaxRecordsData[] = [
                    'startDate' => $roadTax->getStartDate()?->format('Y-m-d'),
                    'expiryDate' => $roadTax->getExpiryDate()?->format('Y-m-d'),
                    'amount' => $roadTax->getAmount(),
                    'frequency' => $roadTax->getFrequency(),
                    'notes' => $roadTax->getNotes(),
                ];
            }

            $vehicleData = [
                'name' => $vehicle->getName(),
                'vehicleType' => $vehicle->getVehicleType()->getName(),
                'make' => $vehicle->getMake(),
                'model' => $vehicle->getModel(),
                'year' => $vehicle->getYear(),
                'vin' => $vehicle->getVin(),
                'registrationNumber' => $vehicle->getRegistrationNumber(),
                'engineNumber' => $vehicle->getEngineNumber(),
                'v5DocumentNumber' => $vehicle->getV5DocumentNumber(),
                'purchaseCost' => $vehicle->getPurchaseCost(),
                'purchaseDate' => $vehicle->getPurchaseDate()?->format('Y-m-d'),
                'purchaseMileage' => $vehicle->getPurchaseMileage(),
                'currentMileage' => $vehicle->getCurrentMileage(),
                'lastServiceDate' => $vehicle->getLastServiceDate()?->format('Y-m-d'),
                'motExpiryDate' => $vehicle->getMotExpiryDate()?->format('Y-m-d'),
                'roadTaxExpiryDate' => $vehicle->getRoadTaxExpiryDate()?->format('Y-m-d'),
                'insuranceExpiryDate' => $vehicle->getInsuranceExpiryDate()?->format('Y-m-d'),
                'roadTaxExempt' => $vehicle->getRoadTaxExempt(),
                'motExempt' => $vehicle->getMotExempt(),
                'securityFeatures' => $vehicle->getSecurityFeatures(),
                'vehicleColor' => $vehicle->getVehicleColor(),
                'serviceIntervalMonths' => $vehicle->getServiceIntervalMonths(),
                'serviceIntervalMiles' => $vehicle->getServiceIntervalMiles(),
                'depreciationMethod' => $vehicle->getDepreciationMethod(),
                'depreciationYears' => $vehicle->getDepreciationYears(),
                'depreciationRate' => $vehicle->getDepreciationRate(),
                'fuelRecords' => $fuelRecords,
                'parts' => $parts,
                'consumables' => $consumables,
                'serviceRecords' => $serviceRecordsData,
                'motRecords' => $motRecordsData,
                'insuranceRecords' => $insuranceRecordsData,
                'roadTaxRecords' => $roadTaxRecordsData,
            ];
            $data[] = $vehicleData;
        }

        return new JsonResponse($data);
    }

    #[Route('/import', name: 'vehicles_import', methods: ['POST'])]
    public function import(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUserEntity();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON format'], Response::HTTP_BAD_REQUEST);
        }

        $imported = 0;
        $errors = [];

        foreach ($data as $index => $vehicleData) {
            try {
                // Validate required fields
                if (empty($vehicleData['name'])) {
                    $errors[] = "Vehicle at index $index: name is required";
                    continue;
                }
                if (empty($vehicleData['vehicleType'])) {
                    $errors[] = "Vehicle at index $index: vehicleType is required";
                    continue;
                }
                if (empty($vehicleData['purchaseCost'])) {
                    $errors[] = "Vehicle at index $index: purchaseCost is required";
                    continue;
                }
                if (empty($vehicleData['purchaseDate'])) {
                    $errors[] = "Vehicle at index $index: purchaseDate is required";
                    continue;
                }

                // Get vehicle type
                $vehicleType = $entityManager->getRepository(VehicleType::class)
                    ->findOneBy(['name' => $vehicleData['vehicleType']]);

                if (!$vehicleType) {
                    $errors[] = "Vehicle at index $index: invalid vehicleType '{$vehicleData['vehicleType']}'";
                    continue;
                }

                // Get or create vehicle make if provided
                if (!empty($vehicleData['make'])) {
                    $vehicleMake = $entityManager->getRepository(VehicleMake::class)
                        ->findOneBy(['name' => $vehicleData['make'], 'vehicleType' => $vehicleType]);

                    if (!$vehicleMake) {
                        $vehicleMake = new VehicleMake();
                        $vehicleMake->setName($vehicleData['make']);
                        $vehicleMake->setVehicleType($vehicleType);
                        $entityManager->persist($vehicleMake);
                        $entityManager->flush();
                    }

                    // Get or create vehicle model if provided
                    if (!empty($vehicleData['model']) && !empty($vehicleData['year'])) {
                        $vehicleModel = $entityManager->getRepository(VehicleModel::class)
                            ->findOneBy([
                                'name' => $vehicleData['model'],
                                'make' => $vehicleMake,
                                'startYear' => $vehicleData['year']
                            ]);

                        if (!$vehicleModel) {
                            $vehicleModel = new VehicleModel();
                            $vehicleModel->setName($vehicleData['model']);
                            $vehicleModel->setMake($vehicleMake);
                            $vehicleModel->setStartYear($vehicleData['year']);
                            $vehicleModel->setEndYear($vehicleData['year']);
                            $entityManager->persist($vehicleModel);
                            $entityManager->flush();
                        }
                    }
                }

                // Create vehicle
                $vehicle = new Vehicle();
                $vehicle->setOwner($user);
                $vehicle->setName($vehicleData['name']);
                $vehicle->setVehicleType($vehicleType);

                if (!empty($vehicleData['make'])) {
                    $vehicle->setMake($vehicleData['make']);
                }
                if (!empty($vehicleData['model'])) {
                    $vehicle->setModel($vehicleData['model']);
                }
                if (!empty($vehicleData['year'])) {
                    $vehicle->setYear($vehicleData['year']);
                }
                if (!empty($vehicleData['vin'])) {
                    $vehicle->setVin($vehicleData['vin']);
                }
                if (!empty($vehicleData['registrationNumber'])) {
                    $vehicle->setRegistrationNumber($vehicleData['registrationNumber']);
                }
                if (!empty($vehicleData['engineNumber'])) {
                    $vehicle->setEngineNumber($vehicleData['engineNumber']);
                }
                if (!empty($vehicleData['v5DocumentNumber'])) {
                    $vehicle->setV5DocumentNumber($vehicleData['v5DocumentNumber']);
                }

                $vehicle->setPurchaseCost($vehicleData['purchaseCost']);
                $vehicle->setPurchaseDate(new \DateTime($vehicleData['purchaseDate']));

                if (isset($vehicleData['purchaseMileage'])) {
                    $vehicle->setPurchaseMileage($vehicleData['purchaseMileage']);
                }
                if (isset($vehicleData['currentMileage'])) {
                    $vehicle->setCurrentMileage($vehicleData['currentMileage']);
                }
                if (!empty($vehicleData['lastServiceDate'])) {
                    $vehicle->setLastServiceDate(new \DateTime($vehicleData['lastServiceDate']));
                }
                if (!empty($vehicleData['motExpiryDate'])) {
                    $vehicle->setMotExpiryDate(new \DateTime($vehicleData['motExpiryDate']));
                }
                if (!empty($vehicleData['roadTaxExpiryDate'])) {
                    $vehicle->setRoadTaxExpiryDate(new \DateTime($vehicleData['roadTaxExpiryDate']));
                }
                if (isset($vehicleData['roadTaxExempt'])) {
                    $vehicle->setRoadTaxExempt($vehicleData['roadTaxExempt']);
                }
                if (isset($vehicleData['motExempt'])) {
                    $vehicle->setMotExempt($vehicleData['motExempt']);
                }
                if (!empty($vehicleData['securityFeatures'])) {
                    $vehicle->setSecurityFeatures($vehicleData['securityFeatures']);
                }
                if (!empty($vehicleData['vehicleColor'])) {
                    $vehicle->setVehicleColor($vehicleData['vehicleColor']);
                }
                if (isset($vehicleData['serviceIntervalMonths'])) {
                    $vehicle->setServiceIntervalMonths($vehicleData['serviceIntervalMonths']);
                }
                if (isset($vehicleData['serviceIntervalMiles'])) {
                    $vehicle->setServiceIntervalMiles($vehicleData['serviceIntervalMiles']);
                }
                if (!empty($vehicleData['insuranceExpiryDate'])) {
                    $vehicle->setInsuranceExpiryDate(
                        new \DateTime($vehicleData['insuranceExpiryDate'])
                    );
                }
                if (!empty($vehicleData['depreciationMethod'])) {
                    $vehicle->setDepreciationMethod($vehicleData['depreciationMethod']);
                }
                if (isset($vehicleData['depreciationYears'])) {
                    $vehicle->setDepreciationYears($vehicleData['depreciationYears']);
                }
                if (isset($vehicleData['depreciationRate'])) {
                    $vehicle->setDepreciationRate($vehicleData['depreciationRate']);
                }

                $entityManager->persist($vehicle);
                $entityManager->flush(); // Flush to get vehicle ID

                // Import fuel records
                if (!empty($vehicleData['fuelRecords'])) {
                    foreach ($vehicleData['fuelRecords'] as $fuelData) {
                        $fuelRecord = new FuelRecord();
                        $fuelRecord->setVehicle($vehicle);

                        if (!empty($fuelData['date'])) {
                            $fuelRecord->setDate(new \DateTime($fuelData['date']));
                        }
                        if (isset($fuelData['litres'])) {
                            $fuelRecord->setLitres($fuelData['litres']);
                        }
                        if (isset($fuelData['cost'])) {
                            $fuelRecord->setCost($fuelData['cost']);
                        }
                        if (isset($fuelData['mileage'])) {
                            $fuelRecord->setMileage($fuelData['mileage']);
                        }
                        if (!empty($fuelData['fuelType'])) {
                            $fuelRecord->setFuelType($fuelData['fuelType']);
                        }
                        if (!empty($fuelData['station'])) {
                            $fuelRecord->setStation($fuelData['station']);
                        }
                        if (!empty($fuelData['notes'])) {
                            $fuelRecord->setNotes($fuelData['notes']);
                        }

                        $entityManager->persist($fuelRecord);
                    }
                }

                // Import parts
                if (!empty($vehicleData['parts'])) {
                    foreach ($vehicleData['parts'] as $partData) {
                        $part = new Part();
                        $part->setVehicle($vehicle);

                        if (!empty($partData['purchaseDate'])) {
                            $part->setPurchaseDate(new \DateTime($partData['purchaseDate']));
                        }
                        if (!empty($partData['description'])) {
                            $part->setDescription($partData['description']);
                        }
                        if (!empty($partData['partNumber'])) {
                            $part->setPartNumber($partData['partNumber']);
                        }
                        if (!empty($partData['manufacturer'])) {
                            $part->setManufacturer($partData['manufacturer']);
                        }
                        if (isset($partData['cost'])) {
                            $part->setCost($partData['cost']);
                        }
                        if (!empty($partData['category'])) {
                            $part->setCategory($partData['category']);
                        }
                        if (!empty($partData['installationDate'])) {
                            $part->setInstallationDate(new \DateTime($partData['installationDate']));
                        }
                        if (isset($partData['mileageAtInstallation'])) {
                            $part->setMileageAtInstallation($partData['mileageAtInstallation']);
                        }
                        if (!empty($partData['notes'])) {
                            $part->setNotes($partData['notes']);
                        }

                        $entityManager->persist($part);
                    }
                }

                // Import consumables
                if (!empty($vehicleData['consumables'])) {
                    foreach ($vehicleData['consumables'] as $consumableData) {
                        if (empty($consumableData['consumableType'])) {
                            continue;
                        }

                        $consumableType = $entityManager->getRepository(ConsumableType::class)
                            ->findOneBy(['name' => $consumableData['consumableType']]);

                        if (!$consumableType) {
                            // Create consumable type if it doesn't exist
                            $consumableType = new ConsumableType();
                            $consumableType->setName($consumableData['consumableType']);
                            $entityManager->persist($consumableType);
                            $entityManager->flush();
                        }

                        $consumable = new Consumable();
                        $consumable->setVehicle($vehicle);
                        $consumable->setConsumableType($consumableType);

                        if (!empty($consumableData['specification'])) {
                            $consumable->setSpecification($consumableData['specification']);
                        }
                        if (isset($consumableData['quantity'])) {
                            $consumable->setQuantity($consumableData['quantity']);
                        }
                        if (!empty($consumableData['lastChanged'])) {
                            $consumable->setLastChanged(new \DateTime($consumableData['lastChanged']));
                        }
                        if (isset($consumableData['mileageAtChange'])) {
                            $consumable->setMileageAtChange($consumableData['mileageAtChange']);
                        }
                        if (isset($consumableData['cost'])) {
                            $consumable->setCost($consumableData['cost']);
                        }
                        if (!empty($consumableData['notes'])) {
                            $consumable->setNotes($consumableData['notes']);
                        }

                        $entityManager->persist($consumable);
                    }
                }

                // Import service records
                if (!empty($vehicleData['serviceRecords'])) {
                    foreach ($vehicleData['serviceRecords'] as $serviceData) {
                        $serviceRecord = new ServiceRecord();
                        $serviceRecord->setVehicle($vehicle);

                        if (!empty($serviceData['serviceDate'])) {
                            $serviceRecord->setServiceDate(new \DateTime($serviceData['serviceDate']));
                        }
                        if (!empty($serviceData['serviceType'])) {
                            $serviceRecord->setServiceType($serviceData['serviceType']);
                        }
                        if (isset($serviceData['laborCost'])) {
                            $serviceRecord->setLaborCost($serviceData['laborCost']);
                        }
                        if (isset($serviceData['partsCost'])) {
                            $serviceRecord->setPartsCost($serviceData['partsCost']);
                        }
                        if (isset($serviceData['mileage'])) {
                            $serviceRecord->setMileage($serviceData['mileage']);
                        }
                        if (!empty($serviceData['serviceProvider'])) {
                            $serviceRecord->setServiceProvider($serviceData['serviceProvider']);
                        }
                        if (!empty($serviceData['workPerformed'])) {
                            $serviceRecord->setWorkPerformed($serviceData['workPerformed']);
                        }
                        if (!empty($serviceData['notes'])) {
                            $serviceRecord->setNotes($serviceData['notes']);
                        }

                        $entityManager->persist($serviceRecord);
                    }
                }

                // Import MOT records
                if (!empty($vehicleData['motRecords'])) {
                    foreach ($vehicleData['motRecords'] as $motData) {
                        $motRecord = new MotRecord();
                        $motRecord->setVehicle($vehicle);

                        if (!empty($motData['testDate'])) {
                            $motRecord->setTestDate(new \DateTime($motData['testDate']));
                        }
                        if (!empty($motData['result'])) {
                            $motRecord->setResult($motData['result']);
                        }
                        if (isset($motData['testCost'])) {
                            $motRecord->setTestCost($motData['testCost']);
                        }
                        if (isset($motData['repairCost'])) {
                            $motRecord->setRepairCost($motData['repairCost']);
                        }
                        if (isset($motData['mileage'])) {
                            $motRecord->setMileage($motData['mileage']);
                        }
                        if (!empty($motData['testCenter'])) {
                            $motRecord->setTestCenter($motData['testCenter']);
                        }
                        if (!empty($motData['advisories'])) {
                            $motRecord->setAdvisories($motData['advisories']);
                        }
                        if (!empty($motData['failures'])) {
                            $motRecord->setFailures($motData['failures']);
                        }
                        if (!empty($motData['repairDetails'])) {
                            $motRecord->setRepairDetails($motData['repairDetails']);
                        }
                        if (!empty($motData['notes'])) {
                            $motRecord->setNotes($motData['notes']);
                        }

                        $entityManager->persist($motRecord);
                    }
                }

                // Import insurance records
                if (!empty($vehicleData['insuranceRecords'])) {
                    foreach ($vehicleData['insuranceRecords'] as $insuranceData) {
                        $policy = new InsurancePolicy();
                        $policy->setHolderId($user->getId());

                        if (!empty($insuranceData['provider'])) {
                            $policy->setProvider($insuranceData['provider']);
                        }
                        if (!empty($insuranceData['policyNumber'])) {
                            $policy->setPolicyNumber($insuranceData['policyNumber']);
                        }
                        if (!empty($insuranceData['coverageType'])) {
                            $policy->setCoverageType($insuranceData['coverageType']);
                        }
                        if (isset($insuranceData['annualCost'])) {
                            $policy->setAnnualCost($insuranceData['annualCost']);
                        }
                        if (!empty($insuranceData['startDate'])) {
                            $policy->setStartDate(new \DateTime($insuranceData['startDate']));
                        }
                        if (!empty($insuranceData['expiryDate'])) {
                            $policy->setExpiryDate(new \DateTime($insuranceData['expiryDate']));
                        }
                        if (!empty($insuranceData['notes'])) {
                            $policy->setNotes($insuranceData['notes']);
                        }

                        // If vehicleIds are provided, add all those vehicles
                        if (!empty($insuranceData['vehicleIds'])) {
                            foreach ($insuranceData['vehicleIds'] as $vid) {
                                $v = $entityManager->getRepository(Vehicle::class)->find($vid);
                                if ($v && $v->getOwner()->getId() === $user->getId()) {
                                    $policy->addVehicle($v);
                                }
                            }
                        } else {
                            // Fallback: add current vehicle
                            $policy->addVehicle($vehicle);
                        }

                        $entityManager->persist($policy);
                    }
                }

                // Import road tax records
                if (!empty($vehicleData['roadTaxRecords'])) {
                    foreach ($vehicleData['roadTaxRecords'] as $roadTaxData) {
                        $roadTax = new RoadTax();
                        $roadTax->setVehicle($vehicle);

                        if (!empty($roadTaxData['startDate'])) {
                            $roadTax->setStartDate(new \DateTime($roadTaxData['startDate']));
                        }
                        if (!empty($roadTaxData['expiryDate'])) {
                            $roadTax->setExpiryDate(new \DateTime($roadTaxData['expiryDate']));
                        }
                        if (isset($roadTaxData['amount'])) {
                            $roadTax->setAmount($roadTaxData['amount']);
                        }
                        if (!empty($roadTaxData['frequency'])) {
                            $roadTax->setFrequency($roadTaxData['frequency']);
                        }
                        if (!empty($roadTaxData['notes'])) {
                            $roadTax->setNotes($roadTaxData['notes']);
                        }

                        $entityManager->persist($roadTax);
                    }
                }

                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Vehicle at index $index: " . $e->getMessage();
            }
        }

        $entityManager->flush();

        $response = [
            'imported' => $imported,
            'total' => count($data),
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return new JsonResponse($response);
    }

    #[Route('/purge-all', name: 'vehicles_purge_all', methods: ['DELETE'])]
    public function purgeAll(EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUserEntity();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }
        $vehicles = $entityManager->getRepository(Vehicle::class)->findBy(['owner' => $user]);

        $count = count($vehicles);

        foreach ($vehicles as $vehicle) {
            $entityManager->remove($vehicle);
        }

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'deleted' => $count,
            'message' => "Successfully deleted $count vehicle(s)"
        ]);
    }
}
