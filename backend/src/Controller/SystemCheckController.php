<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SystemCheckController extends AbstractController
{
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): Response
    {
        return new Response('OK', Response::HTTP_OK);
    }

    #[Route('/api/system-check', name: 'api_system_check', methods: ['GET'])]
    public function check(EntityManagerInterface $em): JsonResponse
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        if (!is_string($projectDir)) {
            return new JsonResponse(
                ['error' => 'Project directory not configured'],
                500
            );
        }

        $results = [
            'backend' => ['ok' => false, 'message' => 'Backend check failed'],
            'db' => ['ok' => false, 'message' => 'Unknown'],
            'paths' => [],
        ];

        // Backend check: always ok if we reach here (no exception thrown)
        $results['backend'] = ['ok' => true, 'message' => 'Backend running'];

        // DB check
        try {
            $conn = $em->getConnection();
            $conn->connect();
            if ($conn->isConnected()) {
                $results['db'] = ['ok' => true, 'message' => 'Connected'];
            } else {
                $results['db'] = ['ok' => false, 'message' => 'Not connected'];
            }
        } catch (\Throwable $e) {
            $results['db'] = ['ok' => false, 'message' => $e->getMessage()];
        }

        // Paths to check
        $paths = [
            'uploads' => $projectDir . DIRECTORY_SEPARATOR . 'uploads',
            'cache' => $projectDir . DIRECTORY_SEPARATOR . 'var'
                . DIRECTORY_SEPARATOR . 'cache',
            'logs' => $projectDir . DIRECTORY_SEPARATOR . 'var'
                . DIRECTORY_SEPARATOR . 'log',
        ];

        foreach ($paths as $key => $path) {
            $exists = is_dir($path) || is_file($path);
            $writable = is_writable($path);
            $results['paths'][$key] = [
                'path' => $path,
                'exists' => $exists,
                'writable' => $writable,
            ];
        }

        $ok = $results['db']['ok'];
        foreach ($results['paths'] as $p) {
            if (!$p['exists'] || !$p['writable']) {
                $ok = false;
                break;
            }
        }

        return $this->json(
            $results,
            $ok ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE
        );
    }

    #[Route(
        '/api/app-compatibility',
        name: 'api_app_compatibility',
        methods: ['GET']
    )]
    public function appCompatibility(): JsonResponse
    {
        return $this->json(
            [
                'server' => [
                    'releaseVersion' => (string) $this->getParameter(
                        'app.release_version'
                    ),
                    'internalVersion' => (string) $this->getParameter(
                        'app.internal_version'
                    ),
                    'compatibilityBaselineCommit' => (string) $this->getParameter(
                        'app.mobile_compatibility_baseline_commit'
                    ),
                    'compatibilityBaselineLabel' => (string) $this->getParameter(
                        'app.mobile_compatibility_baseline_label'
                    ),
                ],
                'mobile' => [
                    'minimumSupportedVersion' => (string) $this->getParameter(
                        'app.mobile_min_supported_version'
                    ),
                    'latestSupportedVersion' => (string) $this->getParameter(
                        'app.mobile_latest_supported_version'
                    ),
                    'minimumSupportedServerReleaseVersion' => (string) $this->getParameter(
                        'app.mobile_min_supported_server_release_version'
                    ),
                ],
                'compatibility' => [
                    'apiCompatibilityVersion' => (int) $this->getParameter(
                        'app.mobile_api_compatibility_version'
                    ),
                    'checkedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                ],
            ]
        );
    }
}
