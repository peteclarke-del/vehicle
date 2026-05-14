<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Validates file uploads by magic bytes (file signatures) instead of extensions.
 * Prevents file type spoofing attacks.
 *
 * @category Service
 * @package  App\Service
 * @author   Vehicle Team <devnull@example.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class FileValidationService
{
    /**
     * File magic byte signatures for supported types.
     * Maps MIME type to magic bytes signature.
     */
    private const MAGIC_BYTES = [
        // Images
        'image/jpeg' => [0xFF, 0xD8, 0xFF],
        'image/png'  => [0x89, 0x50, 0x4E, 0x47],
        'image/gif'  => [0x47, 0x49, 0x46],  // GIF87a or GIF89a
        'image/webp' => [0x52, 0x49, 0x46, 0x46], // RIFF header (followed by WEBP)
        // Documents
        'application/pdf' => [0x25, 0x50, 0x44, 0x46], // %PDF
        // Office documents (ZIP-based)
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => [
            0x50, 0x4B, 0x03, 0x04  // PK (ZIP)
        ],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => [
            0x50, 0x4B, 0x03, 0x04  // PK (ZIP)
        ],
        'application/msword' => [
            0xD0, 0xCF, 0x11, 0xE0  // Microsoft Office document
        ],
        'application/vnd.ms-excel' => [
            0xD0, 0xCF, 0x11, 0xE0  // Microsoft Office document
        ],
    ];

    /**
     * Allowed MIME types with file size limits (in bytes).
     */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 10_485_760,    // 10 MB
        'image/png'  => 10_485_760,    // 10 MB
        'image/gif'  => 5_242_880,     // 5 MB
        'image/webp' => 10_485_760,    // 10 MB
        'application/pdf' => 52_428_800,  // 50 MB
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 52_428_800,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 52_428_800,
        'application/msword' => 52_428_800,
        'application/vnd.ms-excel' => 52_428_800,
    ];

    /**
     * Validate an uploaded file by checking magic bytes and size.
     *
     * @param UploadedFile $file           The uploaded file.
     * @param string|null  $expectedMime   Optional expected MIME type to validate against.
     * @param string|null  $errorMessage   Reference to store error message.
     *
     * @return bool True if file is valid, false otherwise.
     */
    public function validateFile(
        UploadedFile $file,
        ?string $expectedMime = null,
        ?string &$errorMessage = null
    ): bool {
        // Check file size against max upload
        $maxBytes = $_ENV['UPLOAD_MAX_BYTES'] ?? (512 * 1024 * 1024);  // Default 512 MB
        if ($file->getSize() > $maxBytes) {
            $errorMessage = sprintf(
                'File size (%d bytes) exceeds maximum allowed (%d bytes)',
                $file->getSize(),
                $maxBytes
            );
            return false;
        }

        // Get actual MIME type from magic bytes
        $actualMime = $this->detectMimeTypeByMagicBytes($file);

        if ($actualMime === null) {
            $errorMessage = 'Unable to determine file type. File may be corrupted or unsupported.';
            return false;
        }

        // Check if MIME type is allowed
        if (!isset(self::ALLOWED_MIME_TYPES[$actualMime])) {
            $errorMessage = sprintf('File type "%s" is not allowed', $actualMime);
            return false;
        }

        // Check size limit for this MIME type
        if ($file->getSize() > self::ALLOWED_MIME_TYPES[$actualMime]) {
            $errorMessage = sprintf(
                'File size (%d bytes) exceeds limit for %s (%d bytes)',
                $file->getSize(),
                $actualMime,
                self::ALLOWED_MIME_TYPES[$actualMime]
            );
            return false;
        }

        // If expected MIME was provided, verify it matches
        if ($expectedMime !== null && $actualMime !== $expectedMime) {
            $errorMessage = sprintf(
                'File type mismatch. Expected %s but detected %s',
                $expectedMime,
                $actualMime
            );
            return false;
        }

        return true;
    }

    /**
     * Detect MIME type by reading file magic bytes.
     *
     * @param UploadedFile $file The uploaded file.
     *
     * @return string|null Detected MIME type or null if not recognized.
     */
    public function detectMimeTypeByMagicBytes(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();
        if (!is_file($path)) {
            return null;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            // Read first few bytes to check against magic signatures
            $bytes = fread($handle, 12);  // Read up to 12 bytes for detection
            if ($bytes === false) {
                return null;
            }

            $hexBytes = array_map('ord', str_split($bytes));

            // Check each MIME type's magic bytes
            foreach (self::MAGIC_BYTES as $mime => $signature) {
                if ($this->_matchesSignature($hexBytes, $signature)) {
                    return $mime;
                }
            }

            // Special cases: distinguish between ZIP-based formats
            if ($this->_matchesSignature($hexBytes, [0x50, 0x4B, 0x03, 0x04])) {
                // This is a ZIP file - need to check internal structure for Office formats
                return $this->_detectOfficeFormat($path);
            }

            return null;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Check if file bytes match a signature pattern.
     *
     * @param array $fileBytes    Bytes read from file.
     * @param array $signature    Expected signature bytes.
     *
     * @return bool True if bytes match signature.
     */
    private function _matchesSignature(array $fileBytes, array $signature): bool
    {
        if (count($fileBytes) < count($signature)) {
            return false;
        }

        for ($i = 0; $i < count($signature); ++$i) {
            if ($fileBytes[$i] !== $signature[$i]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Detect Office format (DOCX, XLSX, etc.) from ZIP content.
     *
     * @param string $filePath Path to ZIP file.
     *
     * @return string|null Detected MIME type.
     */
    private function _detectOfficeFormat(string $filePath): ?string
    {
        try {
            $zip = new \ZipArchive();
            if ($zip->open($filePath) !== true) {
                return null;
            }

            // Check for Office document markers
            // DOCX: contains word/document.xml
            if ($zip->locateName('word/document.xml') !== false) {
                $zip->close();
                return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            }

            // XLSX: contains xl/workbook.xml
            if ($zip->locateName('xl/workbook.xml') !== false) {
                $zip->close();
                return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            }

            $zip->close();
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get list of allowed MIME types.
     *
     * @return array<string, int> Associative array of MIME type => max size in bytes.
     */
    public function getAllowedMimeTypes(): array
    {
        return self::ALLOWED_MIME_TYPES;
    }

    /**
     * Check if a MIME type is allowed.
     *
     * @param string $mime MIME type to check.
     *
     * @return bool True if MIME type is allowed.
     */
    public function isAllowedMimeType(string $mime): bool
    {
        return isset(self::ALLOWED_MIME_TYPES[$mime]);
    }
}
