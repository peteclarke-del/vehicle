<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Tests\TestCase\BaseWebTestCase;

/**
 * Attachment Controller Test
 * 
 * Integration tests for file attachment operations
 */
class AttachmentControllerTest extends BaseWebTestCase
{
    private string $token;

    protected function setUp(): void
    {
        $client = $this->client;
        $this->token = $this->getAuthToken($client);
    }


    public function testUploadAttachmentRequiresAuthentication(): void
    {
        $client = $this->client;

        $client->request('POST', '/api/attachments');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testUploadAttachment(): void
    {
        $client = $this->client;

        $uploadedFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
            __DIR__ . '/../fixtures/test-receipt.pdf',
            'test-receipt.pdf',
            'application/pdf',
            null,
            true
        );

        $client->request('POST', '/api/attachments', [
            'entityType' => 'service_record',
            'entityId' => 1,
        ], [
            'file' => $uploadedFile,
        ], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(201);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('filename', $data);
        $this->assertArrayHasKey('filepath', $data);
        $this->assertSame('application/pdf', $data['mimeType']);
    }

    public function testUploadImageAttachment(): void
    {
        $client = $this->client;

        $uploadedFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
            __DIR__ . '/../fixtures/test-image.jpg',
            'test-image.jpg',
            'image/jpeg',
            null,
            true
        );

        $client->request('POST', '/api/attachments', [
            'entityType' => 'mot_record',
            'entityId' => 1,
        ], [
            'file' => $uploadedFile,
        ], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('image/jpeg', $data['mimeType']);
    }

    public function testUploadAttachmentWithMissingFile(): void
    {
        $client = $this->client;

        $client->request('POST', '/api/attachments', [
            'entityType' => 'service_record',
            'entityId' => 1,
        ], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testUploadAttachmentWithInvalidFileType(): void
    {
        $client = $this->client;

        $uploadedFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
            __DIR__ . '/../fixtures/test-executable.exe',
            'test-executable.exe',
            'application/x-msdownload',
            null,
            true
        );

        $client->request('POST', '/api/attachments', [
            'entityType' => 'service_record',
            'entityId' => 1,
        ], [
            'file' => $uploadedFile,
        ], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testUploadAttachmentWithFileTooLarge(): void
    {
        $client = $this->client;

        // Create a file larger than max size (10MB)
        $largeFile = tempnam(sys_get_temp_dir(), 'large');
        file_put_contents($largeFile, str_repeat('A', 11 * 1024 * 1024)); // 11MB

        $uploadedFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
            $largeFile,
            'large-file.pdf',
            'application/pdf',
            null,
            true
        );

        $client->request('POST', '/api/attachments', [
            'entityType' => 'service_record',
            'entityId' => 1,
        ], [
            'file' => $uploadedFile,
        ], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $this->assertResponseStatusCodeSame(400);

        unlink($largeFile);
    }

    public function testDownloadAttachment(): void
    {
        $client = $this->client;

        // First upload an attachment
        $uploadedFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
            __DIR__ . '/../fixtures/test-receipt.pdf',
            'test-receipt.pdf',
            'application/pdf',
            null,
            true
        );

        $client->request('POST', '/api/attachments', [
            'entityType' => 'service_record',
            'entityId' => 1,
        ], [
            'file' => $uploadedFile,
        ], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $uploadData = json_decode($client->getResponse()->getContent(), true);
        $attachmentId = $uploadData['id'];

        // Now download it
        $client->request('GET', '/api/attachments/' . $attachmentId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame('application/pdf', $client->getResponse()->headers->get('Content-Type'));
    }

    public function testDownloadAttachmentRequiresAuthentication(): void
    {
        $client = $this->client;

        $client->request('GET', '/api/attachments/1');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteAttachment(): void
    {
        $client = $this->client;

        // First upload an attachment
        $uploadedFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
            __DIR__ . '/../fixtures/test-receipt.pdf',
            'test-receipt.pdf',
            'application/pdf',
            null,
            true
        );

        $client->request('POST', '/api/attachments', [
            'entityType' => 'service_record',
            'entityId' => 1,
        ], [
            'file' => $uploadedFile,
        ], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $uploadData = json_decode($client->getResponse()->getContent(), true);
        $attachmentId = $uploadData['id'];

        // Now delete it
        $client->request('DELETE', '/api/attachments/' . $attachmentId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $this->assertResponseStatusCodeSame(204);
    }

    public function testListAttachmentsForEntity(): void
    {
        $client = $this->client;

        $client->request('GET', '/api/attachments', [
            'entityType' => 'service_record',
            'entityId' => 1,
        ], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testGetAttachmentMetadata(): void
    {
        $client = $this->client;

        // Upload attachment
        $uploadedFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
            __DIR__ . '/../fixtures/test-receipt.pdf',
            'test-receipt.pdf',
            'application/pdf',
            null,
            true
        );

        $client->request('POST', '/api/attachments', [
            'entityType' => 'service_record',
            'entityId' => 1,
        ], [
            'file' => $uploadedFile,
        ], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $uploadData = json_decode($client->getResponse()->getContent(), true);
        $attachmentId = $uploadData['id'];

        // Get metadata
        $client->request('GET', '/api/attachments/' . $attachmentId . '/metadata', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('filename', $data);
        $this->assertArrayHasKey('mimeType', $data);
        $this->assertArrayHasKey('fileSize', $data);
        $this->assertArrayHasKey('uploadedAt', $data);
    }

    public function testUserCannotAccessOtherUsersAttachments(): void
    {
        $client = $this->client;

        // Try to download an attachment belonging to another user
        $client->request('GET', '/api/attachments/999', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testVirusScanningOnUpload(): void
    {
        // virus-scan removed: test no longer applicable
    }

    public function testGenerateThumbnailForImage(): void
    {
        $client = $this->client;

        $uploadedFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
            __DIR__ . '/../fixtures/test-image.jpg',
            'test-image.jpg',
            'image/jpeg',
            null,
            true
        );

        $client->request('POST', '/api/attachments', [
            'entityType' => 'service_record',
            'entityId' => 1,
        ], [
            'file' => $uploadedFile,
        ], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $uploadData = json_decode($client->getResponse()->getContent(), true);
        $attachmentId = $uploadData['id'];

        // Get thumbnail
        $client->request('GET', '/api/attachments/' . $attachmentId . '/thumbnail', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertStringStartsWith('image/', $client->getResponse()->headers->get('Content-Type'));
    }

    public function testGetStorageUsage(): void
    {
        $client = $this->client;

        $client->request('GET', '/api/attachments/storage-usage', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('used', $data);
        $this->assertArrayHasKey('limit', $data);
        $this->assertArrayHasKey('percentage', $data);
    }
}
