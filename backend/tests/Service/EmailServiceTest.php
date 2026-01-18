<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\EmailService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\NullLogger;

/**
 * Email Service Test
 * 
 * Unit tests for email sending service
 */
class EmailServiceTest extends TestCase
{
    private EmailService $service;
    private MailerInterface $mailer;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->service = new EmailService($this->mailer, new NullLogger());
    }

    public function testSendsWelcomeEmail(): void
    {
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return $email->getTo()[0]->getAddress() === 'user@example.com'
                    && str_contains($email->getSubject(), 'Welcome');
            }));

        $this->service->sendWelcomeEmail('user@example.com', 'John Doe');
    }

    public function testSendsPasswordResetEmail(): void
    {
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return $email->getTo()[0]->getAddress() === 'user@example.com'
                    && str_contains($email->getHtmlBody(), 'reset');
            }));

        $this->service->sendPasswordResetEmail('user@example.com', 'reset-token-123');
    }

    public function testSendsMotReminderEmail(): void
    {
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return str_contains($email->getSubject(), 'MOT')
                    && str_contains($email->getSubject(), 'Reminder');
            }));

        $expiryDate = new \DateTimeImmutable('+30 days');
        $this->service->sendMotReminderEmail('user@example.com', 'AB12 CDE', $expiryDate);
    }

    public function testSendsServiceReminderEmail(): void
    {
        $this->mailer->expects($this->once())
            ->method('send');

        $this->service->sendServiceReminderEmail('user@example.com', 'AB12 CDE', 45000);
    }

    public function testSendsInsuranceReminderEmail(): void
    {
        $this->mailer->expects($this->once())
            ->method('send');

        $expiryDate = new \DateTimeImmutable('+14 days');
        $this->service->sendInsuranceReminderEmail('user@example.com', 'AB12 CDE', $expiryDate);
    }

    public function testValidatesEmailAddress(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $this->service->sendWelcomeEmail('invalid-email', 'John Doe');
    }

    public function testHandlesMailerException(): void
    {
        $this->mailer->expects($this->once())
            ->method('send')
            ->willThrowException(new \Exception('SMTP error'));

        $result = $this->service->sendWelcomeEmail('user@example.com', 'John Doe');
        
        $this->assertFalse($result);
    }

    public function testSetsFromAddress(): void
    {
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return $email->getFrom()[0]->getAddress() === 'noreply@vehicle-tracker.com';
            }));

        $this->service->sendWelcomeEmail('user@example.com', 'John Doe');
    }

    public function testSendsHtmlEmail(): void
    {
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return !empty($email->getHtmlBody());
            }));

        $this->service->sendWelcomeEmail('user@example.com', 'John Doe');
    }

    public function testSendsPlainTextFallback(): void
    {
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return !empty($email->getTextBody());
            }));

        $this->service->sendWelcomeEmail('user@example.com', 'John Doe');
    }

    public function testSendsBulkEmails(): void
    {
        $recipients = [
            'user1@example.com',
            'user2@example.com',
            'user3@example.com'
        ];

        $this->mailer->expects($this->exactly(3))
            ->method('send');

        $this->service->sendBulkEmail($recipients, 'Subject', 'Message');
    }

    public function testQueuesEmailForLaterDelivery(): void
    {
        $this->mailer->expects($this->once())
            ->method('send');

        $this->service->queueEmail('user@example.com', 'Subject', 'Body');
    }

    public function testAttachesFileToEmail(): void
    {
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return count($email->getAttachments()) > 0;
            }));

        $this->service->sendEmailWithAttachment(
            'user@example.com',
            'Subject',
            'Body',
            '/path/to/file.pdf'
        );
    }

    public function testLogsEmailSending(): void
    {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Sending email'));

        $service = new EmailService($this->mailer, $logger);
        $service->sendWelcomeEmail('user@example.com', 'John Doe');
    }

    public function testRetriesOnTransientFailure(): void
    {
        $this->mailer->expects($this->exactly(3))
            ->method('send')
            ->willThrowException(new \Exception('Temporary failure'));

        $result = $this->service->sendWithRetry('user@example.com', 'Subject', 'Body');
        
        $this->assertFalse($result);
    }

    public function testTracksEmailDeliveryStatus(): void
    {
        $this->mailer->expects($this->once())
            ->method('send');

        $messageId = $this->service->sendTrackedEmail('user@example.com', 'Subject', 'Body');
        
        $this->assertNotEmpty($messageId);
    }

    public function testUsesEmailTemplate(): void
    {
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return str_contains($email->getHtmlBody(), 'template');
            }));

        $this->service->sendTemplatedEmail('user@example.com', 'welcome', [
            'name' => 'John Doe'
        ]);
    }

    public function testSendsConsumableReminderEmail(): void
    {
        $this->mailer->expects($this->once())
            ->method('send');

        $this->service->sendConsumableReminderEmail(
            'user@example.com',
            'AB12 CDE',
            'Engine Oil',
            55000
        );
    }

    public function testBatchesEmailsForPerformance(): void
    {
        $recipients = array_fill(0, 100, 'user@example.com');

        // Should batch into multiple sends
        $this->mailer->expects($this->atLeast(1))
            ->method('send');

        $this->service->sendBulkEmail($recipients, 'Subject', 'Message');
    }
}
