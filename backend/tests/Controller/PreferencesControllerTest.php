<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\UserPreference;
use App\Entity\User;
use App\Tests\TestCase\BaseWebTestCase;

class PreferencesControllerTest extends BaseWebTestCase
{
    public function testGetPreferencesWithoutAuthentication(): void
    {
        $this->client->request('GET', '/api/user/preferences');

        $this->assertResponseStatusCodeSame(401);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
    }

    public function testGetAllPreferences(): void
    {
        $token = $this->getAuthToken();

        $this->client->request('GET', '/api/user/preferences', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('preferredLanguage', $responseData['data']);
        $this->assertArrayHasKey('distanceUnit', $responseData['data']);
        $this->assertArrayHasKey('sessionTimeout', $responseData['data']);
        $this->assertArrayHasKey('theme', $responseData['data']);
    }

    public function testGetSpecificPreferenceByKey(): void
    {
        $token = $this->getAuthToken();
        $em = $this->getEntityManager();

        // Create a test preference
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $pref = new UserPreference();
        $pref->setUser($user);
        $pref->setName('distanceUnit');
        $pref->setValue('km');
        $em->persist($pref);
        $em->flush();

        $this->client->request('GET', '/api/user/preferences?key=distanceUnit', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('key', $responseData);
        $this->assertArrayHasKey('value', $responseData);
        $this->assertEquals('distanceUnit', $responseData['key']);
    }

    public function testSetPreference(): void
    {
        $token = $this->getAuthToken();

        $payload = [
            'key' => 'theme',
            'value' => 'dark',
        ];

        $this->client->request('POST', '/api/user/preferences', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('key', $responseData);
        $this->assertArrayHasKey('value', $responseData);
        $this->assertEquals('theme', $responseData['key']);
        $this->assertEquals('dark', $responseData['value']);
    }

    public function testSetPreferenceWithoutKey(): void
    {
        $token = $this->getAuthToken();

        $payload = [
            'value' => 'dark',
        ];

        $this->client->request('POST', '/api/user/preferences', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testUpdatePreference(): void
    {
        $token = $this->getAuthToken();
        $em = $this->getEntityManager();

        // Create an existing preference
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $pref = new UserPreference();
        $pref->setUser($user);
        $pref->setName('preferredLanguage');
        $pref->setValue('en');
        $em->persist($pref);
        $em->flush();

        $payload = [
            'key' => 'preferredLanguage',
            'value' => 'es',
        ];

        $this->client->request('POST', '/api/user/preferences', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertResponseIsSuccessful();

        // Verify the preference was updated
        $em->clear();
        $updatedPref = $em->getRepository(UserPreference::class)->findOneBy([
            'user' => $user,
            'name' => 'preferredLanguage'
        ]);
        $this->assertNotNull($updatedPref);
        $this->assertEquals('es', $updatedPref->getValue());
    }
}
