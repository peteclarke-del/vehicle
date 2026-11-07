<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Attachment;
use App\Entity\StockItem;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Tests\TestCase\BaseWebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Full import (POST /api/vehicles/full-import) makes the account match the file.
 */
class FullImportApiTest extends BaseWebTestCase
{
    private string $email;
    private string $otherEmail;

    protected function setUp(): void
    {
        parent::setUp();
        $em = $this->getEntityManager();
        $this->email = 'full-import-' . uniqid() . '@example.com';
        $this->otherEmail = 'full-import-other-' . uniqid() . '@example.com';
        foreach ([$this->email, $this->otherEmail] as $email) {
            $u = (new User())->setEmail($email)->setPassword('x')->setRoles(['ROLE_USER'])->setFirstName('F')->setLastName('I');
            $em->persist($u);
        }
        $em->flush();
    }

    public function testExistingVehicleIsUpdatedFromFile(): void
    {
        $reg = 'FI' . random_int(10, 99) . ' ' . strtoupper(substr(uniqid(), -3));
        $this->createVehicle($this->user($this->email), $reg, 'Old name', 'Live');

        $response = $this->fullImport([$this->vehicleRow($reg, 'New name', 'Sold')]);

        self::assertResponseIsSuccessful();
        self::assertSame(1, $response['fullImport']['removedVehicles']);
        $vehicles = $this->vehiclesFor($this->email);
        self::assertCount(1, $vehicles);
        self::assertSame('New name', $vehicles[0]->getName());
        self::assertSame('Sold', $vehicles[0]->getStatus());
    }

    public function testRegularZipImportStillSkipsExistingVehicle(): void
    {
        $reg = 'ZI' . random_int(10, 99) . ' ' . strtoupper(substr(uniqid(), -3));
        $this->createVehicle($this->user($this->email), $reg, 'Old name', 'Live');

        $this->postZip('/api/vehicles/import-zip', [$this->vehicleRow($reg, 'New name', 'Sold')]);

        $vehicles = $this->vehiclesFor($this->email);
        self::assertCount(1, $vehicles);
        self::assertSame('Old name', $vehicles[0]->getName());
        self::assertSame('Live', $vehicles[0]->getStatus());
    }

    public function testVehiclesNotInFileAreKeptWithoutWipe(): void
    {
        $keep = 'KP' . random_int(10, 99) . ' ' . strtoupper(substr(uniqid(), -3));
        $this->createVehicle($this->user($this->email), $keep, 'Keep me', 'Live');

        $this->fullImport([$this->vehicleRow('NW' . random_int(10, 99) . ' ABC', 'New', 'Live')]);

        self::assertCount(2, $this->vehiclesFor($this->email));
    }

    public function testWipeDataRemovesOnlyThisUsersData(): void
    {
        $me = $this->user($this->email);
        $this->createVehicle($me, 'WP' . random_int(10, 99) . ' AAA', 'Gone', 'Live');
        $this->createStock($me, 'gone-' . uniqid());
        $other = $this->user($this->otherEmail);
        $this->createVehicle($other, 'OT' . random_int(10, 99) . ' BBB', 'Other user', 'Live');
        $this->createStock($other, 'other-' . uniqid());

        $response = $this->fullImport([$this->vehicleRow('IM' . random_int(10, 99) . ' CCC', 'Imported', 'Live')], ['wipeData' => '1']);

        self::assertResponseIsSuccessful();
        self::assertSame(1, $response['fullImport']['removedVehicles']);
        self::assertSame(1, $response['fullImport']['removedStockItems']);
        $mine = $this->vehiclesFor($this->email);
        self::assertCount(1, $mine);
        self::assertSame('Imported', $mine[0]->getName());
        self::assertCount(0, $this->getEntityManager()->getRepository(StockItem::class)->findBy(['user' => $this->user($this->email)]));
        self::assertCount(1, $this->vehiclesFor($this->otherEmail));
        self::assertCount(1, $this->getEntityManager()->getRepository(StockItem::class)->findBy(['user' => $this->user($this->otherEmail)]));
    }

    public function testWipeWithFileDeletionKeepsFilesReimportedAtSamePath(): void
    {
        $reg = 'FP' . random_int(10, 99) . ' ABC';
        $slug = 'full-import-test-' . uniqid();
        $keptPath = 'vehicles/' . $slug . '/kept.pdf';
        $stalePath = 'vehicles/' . $slug . '/stale.pdf';
        $uploads = static::getContainer()->getParameter('kernel.project_dir') . '/uploads/';
        @mkdir($uploads . 'vehicles/' . $slug, 0777, true);
        file_put_contents($uploads . $keptPath, 'old');
        file_put_contents($uploads . $stalePath, 'old');

        $em = $this->getEntityManager();
        $this->createVehicle($this->user($this->email), $reg, 'Old', 'Live');
        $vehicle = $this->vehiclesFor($this->email)[0];
        foreach ([$keptPath, $stalePath] as $path) {
            $a = (new Attachment())->setUser($vehicle->getOwner())->setVehicle($vehicle)
                ->setFilename(basename($path))->setOriginalName(basename($path))
                ->setMimeType('application/pdf')->setFileSize(3)->setStoragePath($path);
            $em->persist($a);
        }
        $em->flush();

        $row = $this->vehicleRow($reg, 'New', 'Live');
        $row['attachments'] = [[
            'filename' => 'kept.pdf', 'exportFilename' => 'kept.pdf', 'storagePath' => $keptPath,
            'mimeType' => 'application/pdf', 'fileSize' => 3,
        ]];

        try {
            $response = $this->postZip('/api/vehicles/full-import', [$row], ['wipeData' => '1', 'deleteFiles' => '1'], [
                'attachments/kept.pdf' => 'new',
            ]);
            self::assertResponseIsSuccessful();
            self::assertSame(1, $response['fullImport']['deletedFiles']);
            self::assertFileExists($uploads . $keptPath, 'Re-imported file must survive post-import cleanup');
            self::assertSame('new', file_get_contents($uploads . $keptPath));
            self::assertFileDoesNotExist($uploads . $stalePath);
        } finally {
            @unlink($uploads . $keptPath);
            @unlink($uploads . $stalePath);
            @rmdir($uploads . 'vehicles/' . $slug);
        }
    }

    public function testDeleteFilesIgnoredWithoutWipe(): void
    {
        $response = $this->fullImport([$this->vehicleRow('DF' . random_int(10, 99) . ' ABC', 'X', 'Live')], ['deleteFiles' => '1']);
        self::assertResponseIsSuccessful();
        self::assertSame(0, $response['fullImport']['deletedFiles']);
    }

    public function testEmptyFileNeverWipes(): void
    {
        $this->createVehicle($this->user($this->email), 'EM' . random_int(10, 99) . ' ABC', 'Keep', 'Live');

        $this->fullImport([], ['wipeData' => '1']);

        self::assertResponseStatusCodeSame(400);
        self::assertCount(1, $this->vehiclesFor($this->email));
    }

    public function testFailedImportRollsBackWipe(): void
    {
        $this->createVehicle($this->user($this->email), 'RB' . random_int(10, 99) . ' ABC', 'Keep', 'Live');

        // A row with no registration fails validation in the importer.
        $this->fullImport([['name' => 'Broken', 'make' => 'Ford']], ['wipeData' => '1']);

        self::assertResponseStatusCodeSame(400);
        $mine = $this->vehiclesFor($this->email);
        self::assertCount(1, $mine);
        self::assertSame('Keep', $mine[0]->getName());
    }

    /** @return array<string, mixed> */
    private function vehicleRow(string $reg, string $name, string $status): array
    {
        return ['name' => $name, 'registrationNumber' => $reg, 'make' => 'Ford', 'model' => 'Focus', 'year' => 2015, 'status' => $status, 'vehicleType' => 'Car', 'purchaseCost' => '5000.00', 'purchaseDate' => '2024-01-01'];
    }

    /**
     * @param list<array<string, mixed>> $vehicles
     * @param array<string, string> $fields
     * @return array<string, mixed>
     */
    private function fullImport(array $vehicles, array $fields = []): array
    {
        return $this->postZip('/api/vehicles/full-import', $vehicles, $fields);
    }

    /**
     * @param list<array<string, mixed>> $vehicles
     * @param array<string, string> $fields
     * @param array<string, string> $extraFiles
     * @return array<string, mixed>
     */
    private function postZip(string $url, array $vehicles, array $fields = [], array $extraFiles = []): array
    {
        $zipPath = sys_get_temp_dir() . '/full-import-' . uniqid() . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('vehicles.json', json_encode($vehicles));
        foreach ($extraFiles as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        $this->client->request('POST', $url, $fields, [
            'file' => new UploadedFile($zipPath, 'import.zip', 'application/zip', null, true),
        ], ['HTTP_X_TEST_MOCK_AUTH' => $this->email]);
        @unlink($zipPath);
        $this->getEntityManager()->clear();

        return json_decode((string) $this->client->getResponse()->getContent(), true) ?? [];
    }

    private function user(string $email): User
    {
        $u = $this->getEntityManager()->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $u);

        return $u;
    }

    /** @return list<Vehicle> */
    private function vehiclesFor(string $email): array
    {
        return $this->getEntityManager()->getRepository(Vehicle::class)->findBy(['owner' => $this->user($email)]);
    }

    private function createVehicle(User $owner, string $reg, string $name, string $status): void
    {
        $v = new Vehicle();
        $v->setOwner($owner);
        $v->setVehicleType($this->getVehicleType('Car'));
        $v->setPurchaseCost('5000.00');
        $v->setPurchaseDate(new \DateTime('-1 year'));
        $v->setName($name);
        $v->setRegistrationNumber($reg);
        $v->setMake('Ford');
        $v->setModel('Focus');
        $v->setYear(2015);
        $v->setStatus($status);
        $this->getEntityManager()->persist($v);
        $this->getEntityManager()->flush();
    }

    private function createStock(User $user, string $description): void
    {
        $s = new StockItem();
        $s->setUser($user);
        $s->setItemType('Part');
        $s->setCategory('General');
        $s->setQuantity('1');
        $s->setDescription($description);
        $this->getEntityManager()->persist($s);
        $this->getEntityManager()->flush();
    }
}
