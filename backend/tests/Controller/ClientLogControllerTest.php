<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\TestCase\BaseWebTestCase;

/**
 * class ClientLogControllerTest
 *
 * Client Log Controller Test
 * Integration tests for client-side logging endpoint
 */
class ClientLogControllerTest extends BaseWebTestCase
{
    /**
     * function testPostClientLog
     *
     * @return void
     */
    public function testPostClientLog(): void
    {
        $client = $this->client;

        $client->request('POST', '/api/client-logs', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'level' => 'error',
            'message' => 'Test error from client',
            'context' => ['url' => '/test-page', 'user' => 'test-user']
        ]));

        $this->assertResponseStatusCodeSame(204);
    }

    /**
     * function testPostClientLogWithInvalidJson
     *
     * @return void
     */
    public function testPostClientLogWithInvalidJson(): void
    {
        $client = $this->client;

        $client->request('POST', '/api/client-logs', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'invalid json{');

        $this->assertResponseStatusCodeSame(400);
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Invalid JSON', $data['error']);
    }

    /**
     * function testPostClientLogWarningLevel
     *
     * @return void
     */
    public function testPostClientLogWarningLevel(): void
    {
        $client = $this->client;

        $client->request('POST', '/api/client-logs', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'level' => 'warning',
            'message' => 'Test warning'
        ]));

        $this->assertResponseStatusCodeSame(204);
    }

    /**
     * function testPostClientLogInfoLevel
     *
     * @return void
     */
    public function testPostClientLogInfoLevel(): void
    {
        $client = $this->client;

        $client->request('POST', '/api/client-logs', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'level' => 'info',
            'message' => 'Test info'
        ]));

        $this->assertResponseStatusCodeSame(204);
    }

    /**
     * function testPostClientLogDebugLevel
     *
     * @return void
     */
    public function testPostClientLogDebugLevel(): void
    {
        $client = $this->client;

        $client->request('POST', '/api/client-logs', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'level' => 'debug',
            'message' => 'Test debug'
        ]));

        $this->assertResponseStatusCodeSame(204);
    }

    /**
     * function testPostClientLogDefaultsToInfo
     *
     * @return void
     */
    public function testPostClientLogDefaultsToInfo(): void
    {
        $client = $this->client;

        $client->request('POST', '/api/client-logs', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'message' => 'Test without level'
        ]));

        $this->assertResponseStatusCodeSame(204);
    }

    /**
     * function testPostClientLogWithUnknownLevel
     *
     * @return void
     */
    public function testPostClientLogWithUnknownLevel(): void
    {
        $client = $this->client;

        $client->request('POST', '/api/client-logs', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'level' => 'unknown-level',
            'message' => 'Test unknown level'
        ]));

        $this->assertResponseStatusCodeSame(204);
    }
}
