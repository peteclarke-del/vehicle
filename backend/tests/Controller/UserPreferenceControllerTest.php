<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\TestCase\BaseWebTestCase;

class UserPreferenceControllerTest extends BaseWebTestCase
{
    public function testGetPreferencesWithoutAuthentication(): void
    {
        $this->client->request('GET', '/api/user/preferences');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetPreferencesAsAuthenticatedUser(): void
    {
        $token = $this->getAuthToken();

        $this->client->request('GET', '/api/user/preferences', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($responseData);
    }

    public function testSavePreferenceWithoutAuthentication(): void
    {
        $this->client->request('POST', '/api/user/preferences', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['key' => 'test', 'value' => 'value']));

        $this->assertResponseStatusCodeSame(401);
    }

    public function testSavePreferenceAsAuthenticatedUser(): void
    {
        $token = $this->getAuthToken();

        $this->client->request('POST', '/api/user/preferences', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['key' => 'test_pref', 'value' => 'test_value']));

        $this->assertResponseIsSuccessful();
    }

    public function testSavePreferenceWithMissingKey(): void
    {
        $token = $this->getAuthToken();

        $this->client->request('POST', '/api/user/preferences', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['value' => 'test_value']));

        $this->assertResponseStatusCodeSame(400);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
    }

    public function testSavePreferenceWithInvalidJson(): void
    {
        $token = $this->getAuthToken();

        $this->client->request('POST', '/api/user/preferences', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], '{invalid json}');

        $this->assertResponseStatusCodeSame(400);
    }
}
