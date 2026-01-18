<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\NotificationService;
use App\Service\EmailService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Notification Service Test
 * 
 * Unit tests for notification management service
 */
class NotificationServiceTest extends TestCase
{
    private NotificationService $service;
    private EmailService $emailService;

    protected function setUp(): void
    {
        $this->emailService = $this->createMock(EmailService::class);
        $this->service = new NotificationService($this->emailService, new NullLogger());
    }

    public function testSendsMotExpiryNotification(): void
    {
        $this->emailService->expects($this->once())
            ->method('sendMotReminderEmail');

        $expiryDate = new \DateTimeImmutable('+30 days');
        $this->service->notifyMotExpiry('user@example.com', 'AB12 CDE', $expiryDate);
    }

    public function testChecksUpcomingMotExpiries(): void
    {
        $notifications = $this->service->checkUpcomingMotExpiries();
        
        $this->assertIsArray($notifications);
    }

    public function testSendsInsuranceExpiryNotification(): void
    {
        $this->emailService->expects($this->once())
            ->method('sendInsuranceReminderEmail');

        $expiryDate = new \DateTimeImmutable('+14 days');
        $this->service->notifyInsuranceExpiry('user@example.com', 'AB12 CDE', $expiryDate);
    }

    public function testSendsServiceDueNotification(): void
    {
        $this->emailService->expects($this->once())
            ->method('sendServiceReminderEmail');

        $this->service->notifyServiceDue('user@example.com', 'AB12 CDE', 45000);
    }

    public function testBatchProcessesNotifications(): void
    {
        $count = $this->service->processPendingNotifications();
        
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testDoesNotSendDuplicateNotifications(): void
    {
        $expiryDate = new \DateTimeImmutable('+30 days');
        
        $this->emailService->expects($this->once())
            ->method('sendMotReminderEmail');

        // Send twice - should only send once
        $this->service->notifyMotExpiry('user@example.com', 'AB12 CDE', $expiryDate);
        $this->service->notifyMotExpiry('user@example.com', 'AB12 CDE', $expiryDate);
    }

    public function testSchedulesNotificationForFuture(): void
    {
        $sendAt = new \DateTimeImmutable('+1 day');
        
        $result = $this->service->scheduleNotification(
            'user@example.com',
            'MOT Reminder',
            'Your MOT is due soon',
            $sendAt
        );
        
        $this->assertTrue($result);
    }

    public function testMarksNotificationAsRead(): void
    {
        $result = $this->service->markAsRead(123);
        
        $this->assertTrue($result);
    }

    public function testGetsUnreadNotifications(): void
    {
        $notifications = $this->service->getUnreadNotifications('user@example.com');
        
        $this->assertIsArray($notifications);
    }

    public function testDeletesOldNotifications(): void
    {
        $cutoffDate = new \DateTimeImmutable('-90 days');
        $count = $this->service->deleteOldNotifications($cutoffDate);
        
        $this->assertIsInt($count);
    }

    public function testSendsDigestEmail(): void
    {
        $this->emailService->expects($this->once())
            ->method('send');

        $this->service->sendDailyDigest('user@example.com');
    }

    public function testRespectsUserPreferences(): void
    {
        // User has notifications disabled
        $this->emailService->expects($this->never())
            ->method('sendMotReminderEmail');

        $this->service->setUserPreference('user@example.com', 'mot_notifications', false);
        
        $expiryDate = new \DateTimeImmutable('+30 days');
        $this->service->notifyMotExpiry('user@example.com', 'AB12 CDE', $expiryDate);
    }

    public function testSendsConsumableNotification(): void
    {
        $this->emailService->expects($this->once())
            ->method('sendConsumableReminderEmail');

        $this->service->notifyConsumableDue('user@example.com', 'AB12 CDE', 'Engine Oil', 55000);
    }

    public function testCreatesInAppNotification(): void
    {
        $result = $this->service->createInAppNotification(
            'user@example.com',
            'MOT Reminder',
            'Your MOT expires in 30 days',
            'warning'
        );
        
        $this->assertTrue($result);
    }

    public function testCountsUnreadNotifications(): void
    {
        $count = $this->service->getUnreadCount('user@example.com');
        
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testSupportsDifferentNotificationTypes(): void
    {
        $types = ['mot', 'insurance', 'service', 'consumable', 'general'];
        
        foreach ($types as $type) {
            $result = $this->service->send('user@example.com', $type, 'Test message');
            $this->assertTrue($result);
        }
    }
}
