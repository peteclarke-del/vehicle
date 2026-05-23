<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\TestCase\BaseWebTestCase;

class SystemCheckControllerTest extends BaseWebTestCase
{
    public function testHealthEndpoint(): void
    {
        $this->client->request('GET', '/health');

        $this->assertResponseIsSuccessful();
        $this->assertEquals('OK', $this->client->getResponse()->getContent());
    }

    public function testSystemCheck(): void
    {
        $this->client->request('GET', '/api/system-check');

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('backend', $responseData);
        $this->assertArrayHasKey('db', $responseData);
        $this->assertArrayHasKey('paths', $responseData);
        
        $this->assertTrue($responseData['backend']['ok']);
        $this->assertTrue($responseData['db']['ok']);
    }

    public function testSystemCheckPathsValidation(): void
    {
        $this->client->request('GET', '/api/system-check');

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertIsArray($responseData['paths']);
        $this->assertArrayHasKey('uploads', $responseData['paths']);
        $this->assertArrayHasKey('cache', $responseData['paths']);
        $this->assertArrayHasKey('logs', $responseData['paths']);
    }

    public function testAppCompatibilityEndpoint(): void
    {
        $this->client->request('GET', '/api/app-compatibility');

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertIsArray($responseData);
        $this->assertSame('0.96.0', $responseData['server']['releaseVersion'] ?? null);
        $this->assertSame('1.0.0', $responseData['mobile']['minimumSupportedVersion'] ?? null);
        $this->assertSame(1, $responseData['compatibility']['apiCompatibilityVersion'] ?? null);
        $this->assertSame('8d148cf', $responseData['server']['compatibilityBaselineCommit'] ?? null);
    }
}
