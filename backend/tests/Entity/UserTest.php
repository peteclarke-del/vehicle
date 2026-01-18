<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * User Entity Test
 * 
 * Unit tests for User entity
 * 
 * @coversDefaultClass \App\Entity\User
 */
class UserTest extends TestCase
{
    public function testUserCreation(): void
    {
        $user = new User();
        
        $this->assertInstanceOf(User::class, $user);
        $this->assertNull($user->getId());
    }

    public function testSetAndGetEmail(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        
        $this->assertSame('test@example.com', $user->getEmail());
    }

    public function testSetAndGetPassword(): void
    {
        $user = new User();
        $user->setPassword('hashed_password');
        
        $this->assertSame('hashed_password', $user->getPassword());
    }

    public function testSetAndGetFirstName(): void
    {
        $user = new User();
        $user->setFirstName('John');
        
        $this->assertSame('John', $user->getFirstName());
    }

    public function testSetAndGetLastName(): void
    {
        $user = new User();
        $user->setLastName('Doe');
        
        $this->assertSame('Doe', $user->getLastName());
    }

    public function testGetFullName(): void
    {
        $user = new User();
        $user->setFirstName('John');
        $user->setLastName('Doe');
        
        $this->assertSame('John Doe', $user->getFullName());
    }

    public function testGetUserIdentifier(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        
        $this->assertSame('test@example.com', $user->getUserIdentifier());
    }

    public function testGetRoles(): void
    {
        $user = new User();
        
        $roles = $user->getRoles();
        
        $this->assertContains('ROLE_USER', $roles);
    }

    public function testSetRoles(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);
        
        $roles = $user->getRoles();
        
        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_USER', $roles); // Should always include ROLE_USER
    }

    public function testEraseCredentials(): void
    {
        $user = new User();
        $user->eraseCredentials();
        
        // This method should not throw any exceptions
        $this->assertTrue(true);
    }

    public function testVehiclesCollection(): void
    {
        $user = new User();
        
        $this->assertCount(0, $user->getVehicles());
    }

    public function testCreatedAtTimestamp(): void
    {
        $user = new User();
        $user->setCreatedAt(new \DateTime());
        
        $this->assertInstanceOf(\DateTime::class, $user->getCreatedAt());
    }

    public function testUpdatedAtTimestamp(): void
    {
        $user = new User();
        $user->setUpdatedAt(new \DateTime());
        
        $this->assertInstanceOf(\DateTime::class, $user->getUpdatedAt());
    }

    public function testSetAndGetLastLoginAt(): void
    {
        $user = new User();
        $date = new \DateTime();
        $user->setLastLoginAt($date);
        
        $this->assertSame($date, $user->getLastLoginAt());
    }

    public function testIsActive(): void
    {
        $user = new User();
        $user->setIsActive(true);
        
        $this->assertTrue($user->isActive());
        
        $user->setIsActive(false);
        $this->assertFalse($user->isActive());
    }

    public function testIsVerified(): void
    {
        $user = new User();
        $user->setIsVerified(true);
        
        $this->assertTrue($user->isVerified());
        
        $user->setIsVerified(false);
        $this->assertFalse($user->isVerified());
    }
}
