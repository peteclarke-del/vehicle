<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Attachment Controller Test
 * 
 * Integration tests for file attachment operations
 */
class AttachmentControllerTest extends WebTestCase
{
    private string $token;

    protected function setUp(): void
    {
        $client = static::createClient();
        $this->token = $this->getAuthToken($client);
    }

    private function getAuthToken($client): string
    {
        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]));

        return json_decode($client->getResponse()->getContent(), true)['token'];
    }

    public function testUploadAttachmentRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/attachments');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testUploadAttachment(): void
    {
        $client = static::createClient();

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
        $client = static::createClient();

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
        $client = static::createClient();

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
        $client = static::createClient();

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
        $client = static::createClient();

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
        $client = static::createClient();

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
        $client = static::createClient();

        $client->request('GET', '/api/attachments/1');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteAttachment(): void
    {
        $client = static::createClient();

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
        $client = static::createClient();

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
        $client = static::createClient();

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
        $client = static::createClient();

        // Try to download an attachment belonging to another user
        $client->request('GET', '/api/attachments/999', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testVirusScanningOnUpload(): void
    {
        $client = static::createClient();

        // Upload suspicious file (mock virus scanner should flag this)
        $uploadedFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
            __DIR__ . '/../fixtures/eicar-test-file.txt',
            'test-virus.txt',
            'text/plain',
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

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('virus', strtolower($data['error']));
    }

    public function testGenerateThumbnailForImage(): void
    {
        $client = static::createClient();

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
        $client = static::createClient();

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
