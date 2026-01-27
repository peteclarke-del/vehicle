<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ClientLogController extends AbstractController
{
    #[Route('/api/client-logs', name: 'api_client_logs', methods: ['POST'])]
    public function post(Request $request, LoggerInterface $logger): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $level = $payload['level'] ?? 'info';
        $message = $payload['message'] ?? 'client-log';
        $context = $payload['context'] ?? [];

        switch (strtolower((string) $level)) {
            case 'error':
                $logger->error($message, (array) $context);
                break;
            case 'warning':
            case 'warn':
                $logger->warning($message, (array) $context);
                break;
            case 'debug':
                $logger->debug($message, (array) $context);
                break;
            default:
                $logger->info($message, (array) $context);
        }

        return new JsonResponse(null, 204);
    }
}
