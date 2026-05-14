<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Audit logging service for security-sensitive operations.
 * Logs all admin actions for compliance and security monitoring.
 *
 * @category Service
 * @package  App\Service
 * @author   Vehicle Team <devnull@example.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class AuditLogService
{
    /**
     * Build audit logger.
     *
     * @param LoggerInterface $logger Logger instance for audit channel.
     *
     * @return void
     */
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * Log administrative action.
     *
     * @param User        $actor     The user performing the action.
     * @param string      $action    Description of action (e.g., 'user.created', 'user.password_reset').
     * @param mixed|null  $subject   Entity or resource affected by action.
     * @param array       $details   Additional details about the action.
     * @param string      $level     Log level (info, warning, error).
     * @param Request|null $request  Optional request object for context.
     *
     * @return void
     */
    public function logAdminAction(
        User $actor,
        string $action,
        mixed $subject = null,
        array $details = [],
        string $level = 'info',
        ?Request $request = null
    ): void {
        $context = [
            'audit' => true,
            'actor_id' => $actor->getId(),
            'actor_email' => $actor->getEmail(),
            'action' => $action,
            'timestamp' => date('Y-m-d H:i:s'),
            'ip_address' => $request?->getClientIp() ?? 'unknown',
        ];

        if ($subject !== null) {
            $context['subject_type'] = $this->_getSubjectType($subject);
            $context['subject_id'] = $this->_getSubjectId($subject);
        }

        // Add additional details
        $context['details'] = $details;

        // Log with appropriate level
        $message = sprintf('AUDIT: %s by %s (%d)', $action, $actor->getEmail(), $actor->getId());
        match (strtolower($level)) {
            'error' => $this->logger->error($message, $context),
            'warning' => $this->logger->warning($message, $context),
            default => $this->logger->info($message, $context),
        };
    }

    /**
     * Log user authentication event.
     *
     * @param User         $user    Authenticated user.
     * @param string       $action  Auth action (login, logout, password_change, etc.).
     * @param Request|null $request Optional request for context.
     *
     * @return void
     */
    public function logAuthEvent(User $user, string $action, ?Request $request = null): void
    {
        $this->logAdminAction(
            $user,
            sprintf('auth.%s', $action),
            subject: $user,
            request: $request
        );
    }

    /**
     * Log failed authentication attempt.
     *
     * @param string       $email       Email address attempted.
     * @param string       $reason      Reason for failure (invalid_password, user_not_found, etc.).
     * @param Request|null $request     Optional request for context.
     *
     * @return void
     */
    public function logFailedAuth(string $email, string $reason, ?Request $request = null): void
    {
        $this->logger->warning(
            sprintf('AUDIT: Failed authentication attempt for email: %s (reason: %s)', $email, $reason),
            [
                'audit' => true,
                'action' => 'auth.failed_login',
                'email' => $email,
                'reason' => $reason,
                'ip_address' => $request?->getClientIp() ?? 'unknown',
                'timestamp' => date('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * Log data access event.
     *
     * @param User        $user     User accessing data.
     * @param string      $resource Resource type (vehicles, vehicles.details, records, etc.).
     * @param mixed|null  $subject  Subject entity.
     * @param Request|null $request Optional request for context.
     *
     * @return void
     */
    public function logDataAccess(User $user, string $resource, mixed $subject = null, ?Request $request = null): void
    {
        $this->logger->debug(
            sprintf('Data access: %s accessed %s', $user->getEmail(), $resource),
            [
                'audit' => true,
                'actor_id' => $user->getId(),
                'action' => sprintf('access.%s', $resource),
                'subject_type' => $subject ? $this->_getSubjectType($subject) : null,
                'subject_id' => $subject ? $this->_getSubjectId($subject) : null,
                'ip_address' => $request?->getClientIp() ?? 'unknown',
            ]
        );
    }

    /**
     * Get subject type name from entity.
     *
     * @param mixed $subject Entity or object.
     *
     * @return string Type name.
     */
    private function _getSubjectType(mixed $subject): string
    {
        return (new \ReflectionClass($subject))->getShortName();
    }

    /**
     * Get subject ID/identifier from entity.
     *
     * @param mixed $subject Entity or object.
     *
     * @return mixed Entity ID or null.
     */
    private function _getSubjectId(mixed $subject): mixed
    {
        if (method_exists($subject, 'getId')) {
            return $subject->getId();
        }

        if (is_array($subject) && isset($subject['id'])) {
            return $subject['id'];
        }

        if (is_object($subject) && property_exists($subject, 'id')) {
            return $subject->id;
        }

        return null;
    }
}
