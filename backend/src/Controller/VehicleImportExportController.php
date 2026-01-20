<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Vehicle;
use App\Entity\VehicleType;
use App\Entity\VehicleMake;
use App\Entity\VehicleModel;
use App\Entity\FuelRecord;
use App\Entity\Part;
use App\Entity\VehicleImage;
use App\Entity\Attachment;
use App\Entity\Consumable;
use App\Entity\ConsumableType;
use App\Entity\ServiceRecord;
use App\Entity\MotRecord;
use App\Entity\RoadTax;
use App\Entity\Specification;
use App\Entity\InsurancePolicy;
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
    public function export(Request $request, EntityManagerInterface $entityManager)
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

            // Export insurance policy records linked to this vehicle
            $insuranceRecords = $entityManager->getRepository(InsurancePolicy::class)
                ->createQueryBuilder('p')
                ->innerJoin('p.vehicles', 'v')
                ->where('v = :vehicle')
                ->setParameter('vehicle', $vehicle)
                ->orderBy('p.startDate', 'ASC')
                ->getQuery()
                ->getResult();
            $insuranceRecordsData = [];
            foreach ($insuranceRecords as $insurance) {
                $insuranceRecordsData[] = [
                    'provider' => $insurance->getProvider(),
                    'policyNumber' => $insurance->getPolicyNumber(),
                    'coverageType' => $insurance->getCoverageType(),
                    'annualCost' => $insurance->getAnnualCost(),
                    'startDate' => $insurance->getStartDate()?->format('Y-m-d'),
                    'expiryDate' => $insurance->getExpiryDate()?->format('Y-m-d'),
                    'notes' => $insurance->getNotes(),
                ];
            }

            $images = [];
            foreach ($vehicle->getImages() as $image) {
                $images[] = [
                    'path' => $image->getPath(),
                    'caption' => $image->getCaption(),
                    'isPrimary' => $image->getIsPrimary(),
                    'displayOrder' => $image->getDisplayOrder(),
                    'isScraped' => $image->getIsScraped(),
                    'sourceUrl' => $image->getSourceUrl(),
                    'uploadedAt' => $image->getUploadedAt()?->format('Y-m-d H:i:s'),
                ];
            }

            $attachments = [];
            $vehicleAttachments = $entityManager->getRepository(\App\Entity\Attachment::class)
                ->findBy(['vehicle' => $vehicle], ['uploadedAt' => 'ASC']);
            foreach ($vehicleAttachments as $att) {
                $attachments[] = [
                    'filename' => $att->getFilename(),
                    'originalName' => $att->getOriginalName(),
                    'mimeType' => $att->getMimeType(),
                    'fileSize' => $att->getFileSize(),
                    'storagePath' => $att->getStoragePath(),
                    'category' => $att->getCategory(),
                    'virusScanStatus' => $att->getVirusScanStatus(),
                    'virusScanDate' => $att->getVirusScanDate()?->format('Y-m-d H:i:s'),
                    'uploadedAt' => $att->getUploadedAt()?->format('Y-m-d H:i:s'),
                    'description' => $att->getDescription(),
                ];
            }

            $vehicleData = [
                'name' => $vehicle->getName(),
                'vehicleType' => $vehicle->getVehicleType()->getName(),
                'make' => $vehicle->getMake(),
                'model' => $vehicle->getModel(),
                'year' => $vehicle->getYear(),
                'vin' => $vehicle->getVin(),
                'vinDecodedData' => $vehicle->getVinDecodedData(),
                'vinDecodedAt' => $vehicle->getVinDecodedAt()?->format('Y-m-d H:i:s'),
                'registrationNumber' => $vehicle->getRegistrationNumber(),
                'engineNumber' => $vehicle->getEngineNumber(),
                'v5DocumentNumber' => $vehicle->getV5DocumentNumber(),
                'purchaseCost' => $vehicle->getPurchaseCost(),
                'purchaseDate' => $vehicle->getPurchaseDate()?->format('Y-m-d'),
                'purchaseMileage' => $vehicle->getPurchaseMileage(),
                // Note: computed fields (current mileage, last service, MOT/road tax)
                // are display-only and derived from related records. They are not
                // emitted here to avoid storing derived state in the import/export
                // manifest; consumers should compute them from the related records.
                // Insurance expiry is derived from related `Insurance` records;
                // do not include as a vehicle-level field in the export manifest.
                'securityFeatures' => $vehicle->getSecurityFeatures(),
                'vehicleColor' => $vehicle->getVehicleColor(),
                'serviceIntervalMonths' => $vehicle->getServiceIntervalMonths(),
                'serviceIntervalMiles' => $vehicle->getServiceIntervalMiles(),
                'depreciationMethod' => $vehicle->getDepreciationMethod(),
                'depreciationYears' => $vehicle->getDepreciationYears(),
                'depreciationRate' => $vehicle->getDepreciationRate(),
                'createdAt' => $vehicle->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updatedAt' => $vehicle->getUpdatedAt()?->format('Y-m-d H:i:s'),
                'fuelRecords' => $fuelRecords,
                'parts' => $parts,
                'consumables' => $consumables,
                'serviceRecords' => $serviceRecordsData,
                'motRecords' => $motRecordsData,
                'roadTaxRecords' => (function() use ($vehicle) {
                    $rtData = [];
                    foreach ($vehicle->getRoadTaxRecords() as $rt) {
                        $rtData[] = [
                            'startDate' => $rt->getStartDate()?->format('Y-m-d'),
                            'expiryDate' => $rt->getExpiryDate()?->format('Y-m-d'),
                            'amount' => $rt->getAmount(),
                            'frequency' => $rt->getFrequency(),
                            'notes' => $rt->getNotes(),
                            'createdAt' => $rt->getCreatedAt()?->format('Y-m-d H:i:s'),
                        ];
                    }
                    return $rtData;
                })(),
                'motExempt' => $vehicle->getMotExempt(),
                'roadTaxExempt' => $vehicle->getRoadTaxExempt(),
                'insuranceRecords' => $insuranceRecordsData,
                'images' => $images,
                'attachments' => $attachments,
                // Export vehicle technical specifications (one-to-one)
                'specifications' => (function() use ($entityManager, $vehicle) {
                    $spec = $entityManager->getRepository(Specification::class)->findOneBy(['vehicle' => $vehicle]);
                    if (!$spec) {
                        return null;
                    }
                    return [
                        'engineType' => $spec->getEngineType(),
                        'displacement' => $spec->getDisplacement(),
                        'power' => $spec->getPower(),
                        'torque' => $spec->getTorque(),
                        'compression' => $spec->getCompression(),
                        'bore' => $spec->getBore(),
                        'stroke' => $spec->getStroke(),
                        'fuelSystem' => $spec->getFuelSystem(),
                        'cooling' => $spec->getCooling(),
                        'gearbox' => $spec->getGearbox(),
                        'transmission' => $spec->getTransmission(),
                        'clutch' => $spec->getClutch(),
                        'frame' => $spec->getFrame(),
                        'frontSuspension' => $spec->getFrontSuspension(),
                        'rearSuspension' => $spec->getRearSuspension(),
                        'frontBrakes' => $spec->getFrontBrakes(),
                        'rearBrakes' => $spec->getRearBrakes(),
                        'frontTyre' => $spec->getFrontTyre(),
                        'rearTyre' => $spec->getRearTyre(),
                        'frontWheelTravel' => $spec->getFrontWheelTravel(),
                        'rearWheelTravel' => $spec->getRearWheelTravel(),
                        'wheelbase' => $spec->getWheelbase(),
                        'seatHeight' => $spec->getSeatHeight(),
                        'groundClearance' => $spec->getGroundClearance(),
                        'dryWeight' => $spec->getDryWeight(),
                        'wetWeight' => $spec->getWetWeight(),
                        'fuelCapacity' => $spec->getFuelCapacity(),
                        'topSpeed' => $spec->getTopSpeed(),
                        'additionalInfo' => $spec->getAdditionalInfo(),
                        'scrapedAt' => $spec->getScrapedAt()?->format('Y-m-d H:i:s'),
                        'sourceUrl' => $spec->getSourceUrl(),
                    ];
                })(),
            ];
            $data[] = $vehicleData;
        }

        // If archive requested, build ZIP with manifest + files
        if ($request->query->get('archive')) {
            $tmpFile = sys_get_temp_dir() . '/vehicle_export_' . uniqid() . '.zip';
            $zip = new \ZipArchive();
            if ($zip->open($tmpFile, \ZipArchive::CREATE) === true) {
                $zip->addFromString('manifest.json', json_encode($data, JSON_PRETTY_PRINT));

                // Add attachment files (backend uploads directory)
                foreach ($data as $vehicleEntry) {
                    if (!empty($vehicleEntry['attachments'])) {
                        foreach ($vehicleEntry['attachments'] as $att) {
                            $attPath = __DIR__ . '/../../../uploads/' . ($att['filename'] ?? '');
                            if ($att['filename'] && file_exists($attPath)) {
                                $zip->addFile($attPath, 'files/attachments/' . basename($attPath));
                            }
                        }
                    }
                    if (!empty($vehicleEntry['images'])) {
                        foreach ($vehicleEntry['images'] as $img) {
                            if (!empty($img['path'])) {
                                $imgPath = $this->getParameter('kernel.project_dir') . '/public' . $img['path'];
                                if (file_exists($imgPath)) {
                                    $zip->addFile($imgPath, 'files/images/' . basename($imgPath));
                                }
                            }
                        }
                    }
                }

                $zip->close();

                $response = new \Symfony\Component\HttpFoundation\BinaryFileResponse($tmpFile);
                $response->setContentDisposition(\Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'vehicles_export_' . date('Ymd') . '.zip');
                $response->deleteFileAfterSend(true);
                return $response;
            }
            // Failed to create zip, fall through to JSON response
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
        $extractedDir = null;

        // Support archive upload (multipart/form-data with file field 'archive')
        if ($request->files->get('archive')) {
            $archive = $request->files->get('archive');
            if ($archive instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                $tmpPath = sys_get_temp_dir() . '/vehicle_import_' . uniqid() . '.zip';
                $archive->move(sys_get_temp_dir(), basename($tmpPath));
                $zip = new \ZipArchive();
                $extractedDir = sys_get_temp_dir() . '/vehicle_import_' . uniqid();
                if ($zip->open($tmpPath) === true) {
                    @mkdir($extractedDir, 0755, true);
                    $zip->extractTo($extractedDir);
                    $zip->close();
                    $manifestPath = $extractedDir . '/manifest.json';
                    if (!file_exists($manifestPath)) {
                        return new JsonResponse(['error' => 'manifest.json not found in archive'], 400);
                    }
                    $data = json_decode(file_get_contents($manifestPath), true);
                    // remove uploaded zip
                    @unlink($tmpPath);
                } else {
                    return new JsonResponse(['error' => 'Invalid ZIP file'], 400);
                }
            } else {
                return new JsonResponse(['error' => 'Invalid archive upload'], 400);
            }
        } else {
            $data = json_decode($request->getContent(), true);
        }

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
                if (isset($vehicleData['vinDecodedData'])) {
                    $vehicle->setVinDecodedData($vehicleData['vinDecodedData']);
                }
                if (!empty($vehicleData['vinDecodedAt'])) {
                    $vehicle->setVinDecodedAt(new \DateTime($vehicleData['vinDecodedAt']));
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
                    $vehicle->setPurchaseMileage($vehicleData['purchaseMileage'] !== null ? (int) $vehicleData['purchaseMileage'] : null);
                }

                // Import explicit exemption flags when provided
                if (array_key_exists('motExempt', $vehicleData)) {
                    $vehicle->setMotExempt($vehicleData['motExempt'] !== null ? (bool)$vehicleData['motExempt'] : null);
                }
                if (array_key_exists('roadTaxExempt', $vehicleData)) {
                    $vehicle->setRoadTaxExempt($vehicleData['roadTaxExempt'] !== null ? (bool)$vehicleData['roadTaxExempt'] : null);
                }

                // Computed/display-only fields (currentMileage, lastServiceDate,
                // motExpiryDate, roadTaxExpiryDate) are intentionally ignored on
                // import. Importers should provide detailed related records
                // (fuelRecords, serviceRecords, motRecords) rather than vehicle-
                // level derived values.
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
                // Insurance expiry is derived from `insuranceRecords`; ignore
                // any vehicle-level insuranceExpiryDate supplied in the manifest.
                if (!empty($vehicleData['depreciationMethod'])) {
                    $vehicle->setDepreciationMethod($vehicleData['depreciationMethod']);
                }
                if (isset($vehicleData['depreciationYears'])) {
                    $vehicle->setDepreciationYears($vehicleData['depreciationYears']);
                }
                if (isset($vehicleData['depreciationRate'])) {
                    $vehicle->setDepreciationRate($vehicleData['depreciationRate']);
                }

                if (!empty($vehicleData['createdAt'])) {
                    $vehicle->setCreatedAt(new \DateTime($vehicleData['createdAt']));
                }
                if (!empty($vehicleData['updatedAt'])) {
                    $vehicle->setUpdatedAt(new \DateTime($vehicleData['updatedAt']));
                }

                $entityManager->persist($vehicle);
                $entityManager->flush(); // Flush to get vehicle ID

                // Import fuel records
                $importedFuelCount = 0;
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
                        $importedFuelCount++;
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
                $importedServiceCount = 0;
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
                        $importedServiceCount++;
                    }
                }

                // Import MOT records
                $importedMotCount = 0;
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
                        $importedMotCount++;
                    }
                }

                // Import road tax records
                if (!empty($vehicleData['roadTaxRecords'])) {
                    foreach ($vehicleData['roadTaxRecords'] as $rtData) {
                        $rt = new RoadTax();
                        $rt->setVehicle($vehicle);

                        if (!empty($rtData['startDate'])) {
                            $rt->setStartDate(new \DateTime($rtData['startDate']));
                        }
                        if (!empty($rtData['expiryDate'])) {
                            $rt->setExpiryDate(new \DateTime($rtData['expiryDate']));
                        }
                        if (isset($rtData['amount'])) {
                            $rt->setAmount($rtData['amount']);
                        }
                        if (!empty($rtData['frequency'])) {
                            $rt->setFrequency($rtData['frequency']);
                        }
                        if (!empty($rtData['notes'])) {
                            $rt->setNotes($rtData['notes']);
                        }
                        if (!empty($rtData['createdAt'])) {
                            $rt->setCreatedAt(new \DateTime($rtData['createdAt']));
                        }

                        $entityManager->persist($rt);
                    }
                }

                // Vehicle-level derived values are ignored; import only creates
                // detailed related records supplied in the manifest.

                // Import insurance policy records
                if (!empty($vehicleData['insuranceRecords'])) {
                    foreach ($vehicleData['insuranceRecords'] as $insuranceData) {
                        $policy = new InsurancePolicy();
                        // Link policy to vehicle via many-to-many relation
                        $policy->addVehicle($vehicle);

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

                        // Set holder to importing user
                        $policy->setHolderId($user->getId());

                        $entityManager->persist($policy);
                    }
                }

                // Import images (and copy files from archive if present)
                if (!empty($vehicleData['images'])) {
                    foreach ($vehicleData['images'] as $imgData) {
                        $image = new VehicleImage();
                        $image->setVehicle($vehicle);

                        // If archive provided, look for file in extracted dir
                        if (!empty($extractedDir) && !empty($imgData['path'])) {
                            $basename = basename($imgData['path']);
                            $candidate = $extractedDir . '/files/images/' . $basename;
                            if (file_exists($candidate)) {
                                $targetDir = $this->getParameter('kernel.project_dir') . '/public/uploads/vehicles/' . $vehicle->getId();
                                if (!is_dir($targetDir)) {
                                    mkdir($targetDir, 0755, true);
                                }
                                $newFilename = uniqid() . '_' . $basename;
                                copy($candidate, $targetDir . '/' . $newFilename);
                                $image->setPath('/uploads/vehicles/' . $vehicle->getId() . '/' . $newFilename);
                            } else {
                                // fallback to provided path
                                $image->setPath($imgData['path']);
                            }
                        } else {
                            if (!empty($imgData['path'])) {
                                $image->setPath($imgData['path']);
                            }
                        }

                        if (array_key_exists('caption', $imgData)) {
                            $image->setCaption($imgData['caption']);
                        }
                        if (isset($imgData['isPrimary'])) {
                            $image->setIsPrimary((bool)$imgData['isPrimary']);
                        }
                        if (isset($imgData['displayOrder'])) {
                            $image->setDisplayOrder((int)$imgData['displayOrder']);
                        }
                        if (isset($imgData['isScraped'])) {
                            $image->setIsScraped((bool)$imgData['isScraped']);
                        }
                        if (!empty($imgData['sourceUrl'])) {
                            $image->setSourceUrl($imgData['sourceUrl']);
                        }
                        if (!empty($imgData['uploadedAt'])) {
                            $image->setUploadedAt(new \DateTime($imgData['uploadedAt']));
                        }

                        $entityManager->persist($image);
                    }
                }

                // Import attachments (and copy files from archive if present)
                if (!empty($vehicleData['attachments'])) {
                    foreach ($vehicleData['attachments'] as $attData) {
                        $att = new Attachment();
                        $att->setVehicle($vehicle);

                        // Try to locate file in extracted archive
                        $foundFile = null;
                        if (!empty($extractedDir)) {
                            if (!empty($attData['filename'])) {
                                $candidate = $extractedDir . '/files/attachments/' . basename($attData['filename']);
                                if (file_exists($candidate)) {
                                    $foundFile = $candidate;
                                }
                            }
                            if (!$foundFile && !empty($attData['storagePath'])) {
                                $candidate = $extractedDir . '/files/attachments/' . basename($attData['storagePath']);
                                if (file_exists($candidate)) {
                                    $foundFile = $candidate;
                                }
                            }
                        }

                        if ($foundFile) {
                            $uploadsDir = __DIR__ . '/../../../uploads';
                            if (!is_dir($uploadsDir)) {
                                mkdir($uploadsDir, 0755, true);
                            }
                            $newFilename = uniqid() . '_' . basename($foundFile);
                            copy($foundFile, $uploadsDir . '/' . $newFilename);
                            $att->setFilename($newFilename);
                            $att->setStoragePath($uploadsDir . '/' . $newFilename);
                        } else {
                            if (!empty($attData['filename'])) {
                                $att->setFilename($attData['filename']);
                            }
                            if (!empty($attData['storagePath'])) {
                                $att->setStoragePath($attData['storagePath']);
                            }
                        }

                        if (!empty($attData['originalName'])) {
                            $att->setOriginalName($attData['originalName']);
                        }
                        if (!empty($attData['mimeType'])) {
                            $att->setMimeType($attData['mimeType']);
                        }
                        if (isset($attData['fileSize'])) {
                            $att->setFileSize((int)$attData['fileSize']);
                        }
                        if (!empty($attData['category'])) {
                            $att->setCategory($attData['category']);
                        }
                        if (!empty($attData['virusScanStatus'])) {
                            $att->setVirusScanStatus($attData['virusScanStatus']);
                        }
                        if (!empty($attData['virusScanDate'])) {
                            $att->setVirusScanDate(new \DateTime($attData['virusScanDate']));
                        }
                        if (!empty($attData['uploadedAt'])) {
                            $att->setUploadedAt(new \DateTime($attData['uploadedAt']));
                        }
                        if (!empty($attData['description'])) {
                            $att->setDescription($attData['description']);
                        }

                        // Assign uploadedBy to importing user
                        $att->setUploadedBy($user);
                        $entityManager->persist($att);
                    }
                }

                // Import vehicle specifications (one-to-one) — merge with existing when present
                if (!empty($vehicleData['specifications']) && is_array($vehicleData['specifications'])) {
                    $specData = $vehicleData['specifications'];
                    // Try to find existing specification for this vehicle and update it
                    $spec = $entityManager->getRepository(Specification::class)->findOneBy(['vehicle' => $vehicle]);
                    if (!$spec) {
                        $spec = new Specification();
                        $spec->setVehicle($vehicle);
                    }

                    if (!empty($specData['engineType'])) {
                        $spec->setEngineType($specData['engineType']);
                    }
                    if (!empty($specData['displacement'])) {
                        $spec->setDisplacement($specData['displacement']);
                    }
                    if (!empty($specData['power'])) {
                        $spec->setPower($specData['power']);
                    }
                    if (!empty($specData['torque'])) {
                        $spec->setTorque($specData['torque']);
                    }
                    if (!empty($specData['compression'])) {
                        $spec->setCompression($specData['compression']);
                    }
                    if (!empty($specData['bore'])) {
                        $spec->setBore($specData['bore']);
                    }
                    if (!empty($specData['stroke'])) {
                        $spec->setStroke($specData['stroke']);
                    }
                    if (!empty($specData['fuelSystem'])) {
                        $spec->setFuelSystem($specData['fuelSystem']);
                    }
                    if (!empty($specData['cooling'])) {
                        $spec->setCooling($specData['cooling']);
                    }
                    if (!empty($specData['gearbox'])) {
                        $spec->setGearbox($specData['gearbox']);
                    }
                    if (!empty($specData['transmission'])) {
                        $spec->setTransmission($specData['transmission']);
                    }
                    if (!empty($specData['clutch'])) {
                        $spec->setClutch($specData['clutch']);
                    }
                    if (!empty($specData['frame'])) {
                        $spec->setFrame($specData['frame']);
                    }
                    if (!empty($specData['frontSuspension'])) {
                        $spec->setFrontSuspension($specData['frontSuspension']);
                    }
                    if (!empty($specData['rearSuspension'])) {
                        $spec->setRearSuspension($specData['rearSuspension']);
                    }
                    if (!empty($specData['frontBrakes'])) {
                        $spec->setFrontBrakes($specData['frontBrakes']);
                    }
                    if (!empty($specData['rearBrakes'])) {
                        $spec->setRearBrakes($specData['rearBrakes']);
                    }
                    if (!empty($specData['frontTyre'])) {
                        $spec->setFrontTyre($specData['frontTyre']);
                    }
                    if (!empty($specData['rearTyre'])) {
                        $spec->setRearTyre($specData['rearTyre']);
                    }
                    if (!empty($specData['frontWheelTravel'])) {
                        $spec->setFrontWheelTravel($specData['frontWheelTravel']);
                    }
                    if (!empty($specData['rearWheelTravel'])) {
                        $spec->setRearWheelTravel($specData['rearWheelTravel']);
                    }
                    if (!empty($specData['wheelbase'])) {
                        $spec->setWheelbase($specData['wheelbase']);
                    }
                    if (!empty($specData['seatHeight'])) {
                        $spec->setSeatHeight($specData['seatHeight']);
                    }
                    if (!empty($specData['groundClearance'])) {
                        $spec->setGroundClearance($specData['groundClearance']);
                    }
                    if (!empty($specData['dryWeight'])) {
                        $spec->setDryWeight($specData['dryWeight']);
                    }
                    if (!empty($specData['wetWeight'])) {
                        $spec->setWetWeight($specData['wetWeight']);
                    }
                    if (!empty($specData['fuelCapacity'])) {
                        $spec->setFuelCapacity($specData['fuelCapacity']);
                    }
                    if (!empty($specData['topSpeed'])) {
                        $spec->setTopSpeed($specData['topSpeed']);
                    }
                    if (!empty($specData['additionalInfo'])) {
                        $spec->setAdditionalInfo($specData['additionalInfo']);
                    }
                    if (!empty($specData['scrapedAt'])) {
                        $spec->setScrapedAt(new \DateTime($specData['scrapedAt']));
                    }
                    if (!empty($specData['sourceUrl'])) {
                        $spec->setSourceUrl($specData['sourceUrl']);
                    }

                    $entityManager->persist($spec);
                }
                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Vehicle at index $index: " . $e->getMessage();
            }
        }

        $entityManager->flush();

        // Cleanup extracted directory if used
        if (!empty($extractedDir) && is_dir($extractedDir)) {
            $it = new \RecursiveDirectoryIterator($extractedDir, \FilesystemIterator::SKIP_DOTS);
            $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                if ($file->isDir()) {
                    @rmdir($file->getRealPath());
                } else {
                    @unlink($file->getRealPath());
                }
            }
            @rmdir($extractedDir);
        }

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
