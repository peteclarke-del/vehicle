<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\TestCase\BaseWebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AttachmentControllerTest extends BaseWebTestCase
{
    public function testUploadAttachmentRequiresAuthentication(): void
    {
        $this->client->request('POST', '/api/attachments');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testUploadAttachmentAndFetchList(): void
    {
        $em = $this->getEntityManager();
        $user = $em->getRepository(User::class)
            ->findOneBy(['email' => 'test@example.com']);
        $vehicle = $this->createTestVehicle($user, 'ATT-' . uniqid());

        $tmpFile = tempnam(sys_get_temp_dir(), 'att_');
        file_put_contents($tmpFile, '%PDF-1.4 test');

        $uploadedFile = new UploadedFile(
            $tmpFile,
            'test-receipt.pdf',
            'application/pdf',
            null,
            true
        );

        $this->client->request(
            'POST',
            '/api/attachments',
            [
                'entityType' => 'vehicle',
                'entityId' => $vehicle->getId(),
            ],
            [
                'file' => $uploadedFile,
            ],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
            ]
        );

        $this->assertSame(201, $this->client->getResponse()->getStatusCode());
        $data = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true
        );
        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);

        $this->client->request(
            'GET',
            '/api/attachments',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
            ]
        );
        $this->assertResponseIsSuccessful();
    }
}
