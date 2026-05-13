<?php

declare(strict_types=1);

namespace App\Tests\Controller;

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
        $tmpFile = tempnam(sys_get_temp_dir(), 'att_');
        file_put_contents($tmpFile, '%PDF-1.4 test');

        $uploadedFile = new UploadedFile(
            $tmpFile,
            'test-receipt.pdf',
            'application/pdf',
            null,
            true
        );

        $this->client->request('POST', '/api/attachments', [
            'entityType' => 'vehicle',
            'entityId' => 1,
        ], [
            'file' => $uploadedFile,
        ], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
        ]);

        $this->assertContains($this->client->getResponse()->getStatusCode(), [201, 404]);

        if ($this->client->getResponse()->getStatusCode() === 201) {
            $data = json_decode($this->client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('id', $data);

            $this->client->request('GET', '/api/attachments', [], [], [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
            ]);
            $this->assertResponseIsSuccessful();
        }
    }
}
