<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Auth Controller Test
 * 
 * Integration tests for authentication and authorization
 */
class AuthControllerTest extends WebTestCase
{
    public function testRegisterNewUser(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'newuser@example.com',
            'password' => 'SecureP@ssw0rd',
            'firstName' => 'John',
            'lastName' => 'Doe',
        ]));

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(201);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('user', $data);
        $this->assertSame('newuser@example.com', $data['user']['email']);
    }

    public function testRegisterWithExistingEmail(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'test@example.com',
            'password' => 'Password123',
            'firstName' => 'Test',
            'lastName' => 'User',
        ]));

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('already exists', $data['error']);
    }

    public function testRegisterWithWeakPassword(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'newuser@example.com',
            'password' => '123',
            'firstName' => 'John',
            'lastName' => 'Doe',
        ]));

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('password', strtolower($data['error']));
    }

    public function testRegisterWithInvalidEmail(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'not-an-email',
            'password' => 'SecureP@ssw0rd',
            'firstName' => 'John',
            'lastName' => 'Doe',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testLogin(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]));

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('expiresAt', $data);
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]));

        $this->assertResponseStatusCodeSame(401);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Invalid credentials', $data['error']);
    }

    public function testLoginWithNonExistentUser(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]));

        $this->assertResponseStatusCodeSame(401);
    }

    public function testRefreshToken(): void
    {
        $client = static::createClient();

        // First login to get a token
        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]));

        $loginData = json_decode($client->getResponse()->getContent(), true);
        $token = $loginData['token'];

        // Now refresh the token
        $client->request('POST', '/api/auth/refresh', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
        $this->assertNotSame($token, $data['token']); // Should be a new token
    }

    public function testRefreshTokenRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/refresh');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testRefreshTokenWithExpiredToken(): void
    {
        $client = static::createClient();

        // Use an expired token (mocked)
        $expiredToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE1MTYyMzkwMjJ9.expired';

        $client->request('POST', '/api/auth/refresh', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $expiredToken,
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testRequestPasswordReset(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/password-reset-request', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'test@example.com',
        ]));

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('email sent', strtolower($data['message']));
    }

    public function testRequestPasswordResetWithInvalidEmail(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/password-reset-request', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'nonexistent@example.com',
        ]));

        // Should still return success to prevent email enumeration
        $this->assertResponseIsSuccessful();
    }

    public function testResetPassword(): void
    {
        $client = static::createClient();

        // Request reset token
        $client->request('POST', '/api/auth/password-reset-request', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'test@example.com',
        ]));

        // Mock reset token (in real app, would be from email)
        $resetToken = 'mock-reset-token-123';

        // Reset password
        $client->request('POST', '/api/auth/password-reset', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'token' => $resetToken,
            'password' => 'NewSecureP@ssw0rd',
        ]));

        $this->assertResponseIsSuccessful();
    }

    public function testResetPasswordWithInvalidToken(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/password-reset', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'token' => 'invalid-token',
            'password' => 'NewSecureP@ssw0rd',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testResetPasswordWithExpiredToken(): void
    {
        $client = static::createClient();

        $expiredToken = 'expired-token-123';

        $client->request('POST', '/api/auth/password-reset', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'token' => $expiredToken,
            'password' => 'NewSecureP@ssw0rd',
        ]));

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('expired', strtolower($data['error']));
    }

    public function testLogout(): void
    {
        $client = static::createClient();

        // Login first
        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]));

        $loginData = json_decode($client->getResponse()->getContent(), true);
        $token = $loginData['token'];

        // Logout
        $client->request('POST', '/api/auth/logout', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();

        // Token should now be invalid
        $client->request('GET', '/api/vehicles', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetCurrentUser(): void
    {
        $client = static::createClient();

        // Login first
        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]));

        $loginData = json_decode($client->getResponse()->getContent(), true);
        $token = $loginData['token'];

        // Get current user
        $client->request('GET', '/api/auth/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('firstName', $data);
        $this->assertArrayHasKey('lastName', $data);
        $this->assertSame('test@example.com', $data['email']);
    }

    public function testUpdateProfile(): void
    {
        $client = static::createClient();

        // Login first
        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]));

        $loginData = json_decode($client->getResponse()->getContent(), true);
        $token = $loginData['token'];

        // Update profile
        $client->request('PUT', '/api/auth/profile', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'firstName' => 'Updated',
            'lastName' => 'Name',
        ]));

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Updated', $data['firstName']);
        $this->assertSame('Name', $data['lastName']);
    }

    public function testChangePassword(): void
    {
        $client = static::createClient();

        // Login first
        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]));

        $loginData = json_decode($client->getResponse()->getContent(), true);
        $token = $loginData['token'];

        // Change password
        $client->request('POST', '/api/auth/change-password', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'currentPassword' => 'password123',
            'newPassword' => 'NewSecureP@ssw0rd',
        ]));

        $this->assertResponseIsSuccessful();
    }

    public function testChangePasswordWithWrongCurrentPassword(): void
    {
        $client = static::createClient();

        // Login first
        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]));

        $loginData = json_decode($client->getResponse()->getContent(), true);
        $token = $loginData['token'];

        // Try to change password with wrong current password
        $client->request('POST', '/api/auth/change-password', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([
            'currentPassword' => 'wrongpassword',
            'newPassword' => 'NewSecureP@ssw0rd',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testRateLimitingOnLogin(): void
    {
        $client = static::createClient();

        // Attempt multiple failed logins
        for ($i = 0; $i < 10; $i++) {
            $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ]));
        }

        // Next attempt should be rate limited
        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]));

        $this->assertResponseStatusCodeSame(429);
    }
}
