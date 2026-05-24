<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ExportZipJobRunner
{
    public function __construct(
        private readonly ExportZipJobService $jobService,
        private readonly ExportZipArchiveBuilder $archiveBuilder,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function run(string $jobId): void
    {
        $job = $this->jobService->getJob($jobId);
        if (!is_array($job)) {
            $this->logger->error('[export-zip-job] Job not found', ['jobId' => $jobId]);
            return;
        }

        if (in_array((string)($job['status'] ?? null), ['completed', 'cancelled'], true)) {
            return;
        }

        if ($this->jobService->isCancelled($jobId)) {
            return;
        }

        $this->jobService->markRunning($jobId, 'Preparing export', 15, 'prepare');

        $userId = (int) ($job['ownerUserId'] ?? 0);
        $user = $this->entityManager->getRepository(User::class)->find($userId);

        if (!$user instanceof User) {
            $this->jobService->markFailed($jobId, 'Unable to resolve export user');
            return;
        }

        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);
        $includeGlobalState = (bool) ($job['options']['includeGlobalState'] ?? false);
        $includeImages = (bool) ($job['options']['includeImages'] ?? false);

        $workDir = $this->jobService->getWorkDirectory($jobId);
        $zipPath = $this->jobService->getArchivePath($jobId);

        try {
            $this->jobService->markRunning($jobId, 'Building export archive', 35, 'prepare');

            $lastProgress = -1;
            $lastMessage = '';
            $lastStage = '';
            $progressReporter = function (int $progress, string $message, ?string $stage = null) use ($jobId, &$lastProgress, &$lastMessage, &$lastStage): void {
                if ($this->jobService->isCancelled($jobId)) {
                    throw new \RuntimeException('Export cancelled by user');
                }

                $safeProgress = max(0, min(95, $progress));
                $safeStage = is_string($stage) && $stage !== '' ? $stage : 'running';
                if ($safeProgress === $lastProgress && $message === $lastMessage && $safeStage === $lastStage) {
                    return;
                }

                $lastProgress = $safeProgress;
                $lastMessage = $message;
                $lastStage = $safeStage;
                $this->jobService->markRunning($jobId, $message, $safeProgress, $safeStage);
            };

            $result = $this->archiveBuilder->build(
                $user,
                $isAdmin,
                $includeGlobalState,
                $includeImages,
                $workDir,
                $zipPath,
                $progressReporter,
                fn (): bool => $this->jobService->isCancelled($jobId)
            );

            $this->jobService->cleanupWorkDirectory($jobId);
            $this->jobService->markCompleted($jobId, (int) ($result['zipSize'] ?? 0));

            $this->logger->info('[export-zip-job] Completed', [
                'jobId' => $jobId,
                'vehicleCount' => $result['vehicleCount'] ?? 0,
                'stockItemCount' => $result['stockItemCount'] ?? 0,
                'zipSize' => $result['zipSize'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            @unlink($zipPath);
            $this->jobService->cleanupWorkDirectory($jobId);

            if ($this->jobService->isCancelled($jobId)) {
                $this->jobService->markCancelled($jobId, 'Export cancelled by user');
                $this->logger->info('[export-zip-job] Cancelled', ['jobId' => $jobId]);
                return;
            }

            $this->jobService->markFailed($jobId, $e->getMessage());

            $this->logger->error('[export-zip-job] Failed', [
                'jobId' => $jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
