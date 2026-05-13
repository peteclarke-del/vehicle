<?php

declare(strict_types=1);

use App\Tests\TestCase\BaseWebTestCase;

class ClientLogControllerTest extends BaseWebTestCase
{
    public function testPostClientLog(): void
    {
        $this->client->request('POST', '/api/client-logs', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'level' => 'error',
            'message' => 'Test error from client',
            'context' => ['url' => '/test-page', 'user' => 'test-user'],
        ]));

        $this->assertResponseStatusCodeSame(204);
    }

    public function testPostClientLogWithInvalidJson(): void
    {
        $this->client->request('POST', '/api/client-logs', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'invalid json{');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testPostClientLogInfoLevel(): void
    {
        $this->client->request('POST', '/api/client-logs', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'level' => 'info',
            'message' => 'Test info',
        ]));

        $this->assertResponseStatusCodeSame(204);
    }
}
