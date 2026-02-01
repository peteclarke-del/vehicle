<?php

declare(strict_types=1);

namespace App\Controller\Trait;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * trait JsonValidationTrait
 *
 * Provides safe JSON decoding with validation
 */
trait JsonValidationTrait
{
    /**
     * function decodeJsonRequest
     *
     * Safely decode JSON request content with validation
     *
     * @param Request $request
     * @param bool $assoc
     *
     * @return array|object|null
     */
    private function decodeJsonRequest(Request $request, bool $assoc = true): array|object|null
    {
        $content = $request->getContent();
        
        if (empty($content)) {
            return $assoc ? [] : (object)[];
        }

        try {
            $data = json_decode($content, $assoc, 512, JSON_THROW_ON_ERROR);
            return $data;
        } catch (\JsonException) {
            return null;
        }
    }

    /**
     * function jsonError
     *
     * Create a JSON error response for invalid JSON input
     *
     * @param string $message
     * @param int $statusCode
     *
     * @return JsonResponse
     */
    private function jsonError(string $message = 'Invalid JSON', int $statusCode = 400): JsonResponse
    {
        return new JsonResponse(['error' => $message], $statusCode);
    }

    /**
     * function validateJsonRequest
     *
     * Validate and decode JSON request, returning error response if invalid
     *
     * @param Request $request
     * @param bool $assoc
     *
     * @return array
     */
    private function validateJsonRequest(Request $request, bool $assoc = true): array
    {
        $data = $this->decodeJsonRequest($request, $assoc);
        
        if ($data === null && !empty($request->getContent())) {
            return ['data' => null, 'error' => $this->jsonError('Invalid JSON format')];
        }

        return ['data' => $data, 'error' => null];
    }
}
