<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use DateInterval;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

class ExportZipJobService
{
    private const JOB_TTL_HOURS = 24;

    public function __construct(
        private readonly string $projectDir,
        private readonly LoggerInterface $logger
    ) {
    }

    public function createJob(User $user, bool $includeGlobalState, bool $includeImages): array
    {
        $this->pruneExpiredJobs();

        $jobId = bin2hex(random_bytes(16));
        $jobDir = $this->getJobDirectory($jobId);
        $workDir = $this->getWorkDirectory($jobId);

        $this->ensureDirectory($jobDir);
        $this->ensureDirectory($workDir);

        $now = $this->nowIso();
        $job = [
            'id' => $jobId,
            'ownerUserId' => $user->getId(),
            'status' => 'queued',
            'stage' => 'queued',
            'workerPid' => null,
            'progress' => 5,
            'message' => 'Export queued',
            'error' => null,
            'options' => [
                'includeGlobalState' => $includeGlobalState,
                'includeImages' => $includeImages,
            ],
            'output' => [
                'zipRelativePath' => null,
                'zipSize' => null,
                'downloadFilename' => 'vehicles_full_export_' . (new DateTimeImmutable())->format('Y-m-d') . '.zip',
            ],
            'createdAt' => $now,
            'updatedAt' => $now,
            'completedAt' => null,
        ];

        $this->writeJob($jobId, $job);

        return $job;
    }

    public function getJob(string $jobId): ?array
    {
        if (!$this->isValidJobId($jobId)) {
            return null;
        }

        $jobFile = $this->getJobFilePath($jobId);
        if (!is_file($jobFile)) {
            return null;
        }

        $contents = file_get_contents($jobFile);
        if (!is_string($contents) || $contents === '') {
            return null;
        }

        try {
            $job = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->logger->error('[export-zip-job] Failed to decode job file', [
                'jobId' => $jobId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return is_array($job) ? $job : null;
    }

    public function updateJob(string $jobId, array $changes): ?array
    {
        $job = $this->getJob($jobId);
        if (!is_array($job)) {
            return null;
        }

        $updated = array_replace_recursive($job, $changes);
        $updated['updatedAt'] = $this->nowIso();

        $this->writeJob($jobId, $updated);

        return $updated;
    }

    public function markRunning(string $jobId, string $message, int $progress, ?string $stage = null): ?array
    {
        return $this->updateJob($jobId, [
            'status' => 'running',
            'stage' => $stage ?? 'running',
            'message' => $message,
            'progress' => max(0, min(95, $progress)),
            'error' => null,
        ]);
    }

    public function setWorkerPid(string $jobId, int $workerPid): ?array
    {
        return $this->updateJob($jobId, [
            'workerPid' => $workerPid > 0 ? $workerPid : null,
        ]);
    }

    public function markCancelling(string $jobId): ?array
    {
        return $this->updateJob($jobId, [
            'status' => 'running',
            'stage' => 'cancelling',
            'message' => 'Cancelling export...',
            'error' => null,
        ]);
    }

    public function markCancelled(string $jobId, string $message = 'Export cancelled by user'): ?array
    {
        return $this->updateJob($jobId, [
            'status' => 'cancelled',
            'stage' => 'cancelled',
            'message' => $message,
            'progress' => 0,
            'error' => null,
            'completedAt' => $this->nowIso(),
        ]);
    }

    public function isCancelled(string $jobId): bool
    {
        $job = $this->getJob($jobId);
        if (!is_array($job)) {
            return false;
        }

        return in_array((string)($job['status'] ?? ''), ['cancelled'], true)
            || in_array((string)($job['stage'] ?? ''), ['cancelling', 'cancelled'], true);
    }

    public function markFailed(string $jobId, string $errorMessage): ?array
    {
        return $this->updateJob($jobId, [
            'status' => 'failed',
            'stage' => 'failed',
            'message' => 'Export failed',
            'progress' => 0,
            'error' => $errorMessage,
            'completedAt' => $this->nowIso(),
        ]);
    }

    public function markCompleted(string $jobId, int $zipSize): ?array
    {
        $relativePath = $this->getArchiveRelativePath($jobId);

        return $this->updateJob($jobId, [
            'status' => 'completed',
            'stage' => 'completed',
            'message' => 'Export ready for download',
            'progress' => 100,
            'error' => null,
            'output' => [
                'zipRelativePath' => $relativePath,
                'zipSize' => $zipSize,
            ],
            'completedAt' => $this->nowIso(),
        ]);
    }

    public function getArchivePath(string $jobId): string
    {
        return $this->getJobDirectory($jobId) . '/export.zip';
    }

    public function getWorkDirectory(string $jobId): string
    {
        return $this->getJobDirectory($jobId) . '/work';
    }

    public function cleanupWorkDirectory(string $jobId): void
    {
        $this->removeDirectory($this->getWorkDirectory($jobId));
    }

    public function cleanupJobArtifacts(string $jobId): void
    {
        $this->removeDirectory($this->getJobDirectory($jobId));
    }

    public function getArchivePathFromJob(array $job): ?string
    {
        $relativePath = $job['output']['zipRelativePath'] ?? null;
        if (!is_string($relativePath) || $relativePath === '') {
            return null;
        }

        $absolutePath = $this->projectDir . '/' . ltrim($relativePath, '/');

        return is_file($absolutePath) ? $absolutePath : null;
    }

    public function canAccessJob(array $job, User $user, bool $isAdmin): bool
    {
        if ($isAdmin) {
            return true;
        }

        return (int) ($job['ownerUserId'] ?? 0) === (int) $user->getId();
    }

    public function getPublicPayload(array $job, string $statusUrl, string $downloadUrl): array
    {
        return [
            'jobId' => $job['id'] ?? null,
            'status' => $job['status'] ?? 'unknown',
            'stage' => $job['stage'] ?? 'unknown',
            'workerPid' => $job['workerPid'] ?? null,
            'progress' => (int) ($job['progress'] ?? 0),
            'message' => $job['message'] ?? '',
            'error' => $job['error'] ?? null,
            'createdAt' => $job['createdAt'] ?? null,
            'updatedAt' => $job['updatedAt'] ?? null,
            'completedAt' => $job['completedAt'] ?? null,
            'statusUrl' => $statusUrl,
            'downloadUrl' => ($job['status'] ?? '') === 'completed' ? $downloadUrl : null,
            'pollIntervalMs' => 2000,
        ];
    }

    public function pruneExpiredJobs(): void
    {
        $jobsRoot = $this->getJobsRoot();
        if (!is_dir($jobsRoot)) {
            return;
        }

        $entries = scandir($jobsRoot);
        if (!is_array($entries)) {
            return;
        }

        $now = new DateTimeImmutable();
        $cutoff = $now->sub(new DateInterval('PT' . self::JOB_TTL_HOURS . 'H'));

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $jobId = $entry;
            if (!$this->isValidJobId($jobId)) {
                continue;
            }

            $job = $this->getJob($jobId);
            if (!is_array($job)) {
                $this->removeDirectory($this->getJobDirectory($jobId));
                continue;
            }

            $updatedAt = $job['updatedAt'] ?? null;
            if (!is_string($updatedAt)) {
                continue;
            }

            try {
                $updatedDate = new DateTimeImmutable($updatedAt);
            } catch (\Throwable $e) {
                continue;
            }

            if ($updatedDate < $cutoff) {
                $this->removeDirectory($this->getJobDirectory($jobId));
            }
        }
    }

    private function writeJob(string $jobId, array $job): void
    {
        $filePath = $this->getJobFilePath($jobId);
        $json = json_encode($job, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (file_put_contents($filePath, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Failed to write export job state: ' . $jobId);
        }
    }

    private function getJobsRoot(): string
    {
        return $this->projectDir . '/var/tmp/export-jobs';
    }

    private function getJobDirectory(string $jobId): string
    {
        return $this->getJobsRoot() . '/' . $jobId;
    }

    private function getJobFilePath(string $jobId): string
    {
        return $this->getJobDirectory($jobId) . '/job.json';
    }

    private function getArchiveRelativePath(string $jobId): string
    {
        return 'var/tmp/export-jobs/' . $jobId . '/export.zip';
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Failed to create directory: ' . $directory);
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = scandir($directory);
        if (!is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $directory . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }

    private function nowIso(): string
    {
        return (new DateTimeImmutable())->format(DATE_ATOM);
    }

    private function isValidJobId(string $jobId): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32}$/', $jobId);
    }
}
