<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use thiagoalessio\TesseractOCR\TesseractOCR;

/**
 * Enhanced OCR service with image preprocessing, multi-page support,
 * and template-based smart extraction.
 *
 * Pipeline: Image(s) -> Preprocess -> Tesseract OCR -> Template Engine -> Structured Data
 */
class ReceiptOcrService
{
    public function __construct(
        private LoggerInterface $logger,
        private ReceiptTemplateService $templateService,
        private string $uploadDirectory
    ) {
    }

    /**
     * Extract receipt data from a single file using the smart template engine.
     *
     * @param string $filePath Absolute path to the image/PDF
     * @param string $category Entity category (fuel, part, consumable, service, mot)
     * @return array Extracted fields with metadata
     */
    public function extractReceiptData(string $filePath, string $category = 'fuel'): array
    {
        try {
            $text = $this->performOcr($filePath);
            $this->logger->info('OCR extracted text', ['text' => $text, 'category' => $category]);

            return $this->templateService->extract($text, $category);
        } catch (\Exception $e) {
            $this->logger->error('OCR processing failed', [
                'error' => $e->getMessage(),
                'file' => $filePath
            ]);

            return ['_meta' => ['error' => $e->getMessage(), 'category' => $category]];
        }
    }

    /**
     * Extract parts/consumables receipt data (legacy compatibility wrapper).
     */
    public function extractPartReceiptData(string $filePath): array
    {
        return $this->extractReceiptData($filePath, 'part');
    }

    /**
     * Extract service record receipt data (legacy compatibility wrapper).
     */
    public function extractServiceReceiptData(string $filePath): array
    {
        return $this->extractReceiptData($filePath, 'service');
    }

    /**
     * Process multiple images/pages and extract combined data.
     *
     * @param string[] $filePaths Array of absolute paths to images/PDFs
     * @param string   $category  Entity category
     * @return array Merged extracted fields from all pages
     */
    public function extractFromMultipleFiles(array $filePaths, string $category): array
    {
        $pageTexts = [];

        foreach ($filePaths as $i => $filePath) {
            try {
                $text = $this->performOcr($filePath);
                $this->logger->info("OCR page {$i} extracted", [
                    'textLength' => strlen($text),
                    'file' => basename($filePath),
                ]);
                $pageTexts[] = $text;
            } catch (\Exception $e) {
                $this->logger->warning("OCR failed for page {$i}", [
                    'error' => $e->getMessage(),
                    'file' => $filePath,
                ]);
                $pageTexts[] = '';
            }
        }

        $nonEmpty = array_filter($pageTexts, fn($t) => trim($t) !== '');
        if (empty($nonEmpty)) {
            return ['_meta' => ['error' => 'OCR failed on all pages', 'category' => $category]];
        }

        return $this->templateService->extractFromMultiplePages(array_values($nonEmpty), $category);
    }

    /**
     * Get raw OCR text from a file (useful for debugging / raw text display).
     */
    public function getRawText(string $filePath): string
    {
        return $this->performOcr($filePath);
    }

    /**
     * Perform OCR on a single file with preprocessing.
     */
    private function performOcr(string $filePath): string
    {
        if ($this->isPdf($filePath)) {
            $imagePath = $this->convertPdfToImage($filePath);
            $text = $this->runTesseract($imagePath);
            if ($imagePath !== $filePath && file_exists($imagePath)) {
                @unlink($imagePath);
            }
            return $text;
        }

        $preprocessed = $this->preprocessImage($filePath);
        $text = $this->runTesseract($preprocessed);

        if ($preprocessed !== $filePath && file_exists($preprocessed)) {
            @unlink($preprocessed);
        }

        return $text;
    }

    /**
     * Run Tesseract OCR on an image file.
     */
    private function runTesseract(string $imagePath): string
    {
        try {
            $ocr = new TesseractOCR($imagePath);
            $ocr->lang('eng');
            $ocr->psm(3); // Fully automatic page segmentation
            return $ocr->run();
        } catch (\Exception $e) {
            $this->logger->error('Tesseract execution failed', [
                'error' => $e->getMessage(),
                'file' => $imagePath,
            ]);
            throw $e;
        }
    }

    /**
     * Preprocess an image for better OCR accuracy.
     * Uses ImageMagick convert if available, falls back to GD.
     */
    private function preprocessImage(string $filePath): string
    {
        $tmpDir = $this->uploadDirectory . '/../var/tmp';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }
        $outputPath = $tmpDir . '/ocr_' . uniqid() . '.png';

        if ($this->commandExists('convert')) {
            $escapedInput = escapeshellarg($filePath);
            $escapedOutput = escapeshellarg($outputPath);

            $cmd = "convert {$escapedInput} "
                . "-resize '2000x2000>' "
                . "-colorspace Gray "
                . "-contrast-stretch 1%x1% "
                . "-sharpen 0x1 "
                . "-deskew 40% "
                . $escapedOutput;

            exec($cmd . ' 2>&1', $output, $returnCode);

            if ($returnCode === 0 && file_exists($outputPath)) {
                return $outputPath;
            }

            $this->logger->warning('ImageMagick preprocessing failed, using GD fallback', [
                'returnCode' => $returnCode,
                'output' => implode("\n", $output),
            ]);
        }

        return $this->preprocessWithGd($filePath, $outputPath);
    }

    /**
     * Basic image preprocessing using PHP GD extension.
     */
    private function preprocessWithGd(string $filePath, string $outputPath): string
    {
        $image = $this->createGdFromFile($filePath);
        if (!$image) {
            return $filePath;
        }

        imagefilter($image, IMG_FILTER_GRAYSCALE);
        imagefilter($image, IMG_FILTER_CONTRAST, -20);
        imagefilter($image, IMG_FILTER_BRIGHTNESS, 10);
        imagepng($image, $outputPath);
        imagedestroy($image);

        return file_exists($outputPath) ? $outputPath : $filePath;
    }

    private function createGdFromFile(string $filePath): \GdImage|false
    {
        $mime = mime_content_type($filePath) ?: '';
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($filePath),
            'image/png' => @imagecreatefrompng($filePath),
            'image/gif' => @imagecreatefromgif($filePath),
            'image/webp' => @imagecreatefromwebp($filePath),
            default => false,
        };
    }

    /**
     * Convert a PDF to a PNG image for OCR processing.
     */
    private function convertPdfToImage(string $pdfPath): string
    {
        $tmpDir = $this->uploadDirectory . '/../var/tmp';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }
        $outputPath = $tmpDir . '/ocr_pdf_' . uniqid() . '.png';

        if ($this->commandExists('convert')) {
            $escapedInput = escapeshellarg($pdfPath . '[0]');
            $escapedOutput = escapeshellarg($outputPath);
            $cmd = "convert -density 300 {$escapedInput} -quality 100 -colorspace Gray {$escapedOutput}";
            exec($cmd . ' 2>&1', $output, $returnCode);

            if ($returnCode === 0 && file_exists($outputPath)) {
                return $outputPath;
            }
        }

        if ($this->commandExists('gs')) {
            $escapedInput = escapeshellarg($pdfPath);
            $escapedOutput = escapeshellarg($outputPath);
            $cmd = "gs -dNOPAUSE -dBATCH -sDEVICE=png16m -r300 -dFirstPage=1 -dLastPage=1 "
                . "-sOutputFile={$escapedOutput} {$escapedInput}";
            exec($cmd . ' 2>&1', $output, $returnCode);

            if ($returnCode === 0 && file_exists($outputPath)) {
                return $outputPath;
            }
        }

        $this->logger->warning('PDF conversion failed, passing raw PDF to Tesseract');
        return $pdfPath;
    }

    /**
     * Convert ALL pages of a PDF to individual images.
     *
     * @return string[] Array of image file paths
     */
    public function convertPdfPages(string $pdfPath): array
    {
        $tmpDir = $this->uploadDirectory . '/../var/tmp';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }

        $basePath = $tmpDir . '/ocr_pdf_' . uniqid();
        $pages = [];

        if ($this->commandExists('identify') && $this->commandExists('convert')) {
            exec('identify -format "%n\n" ' . escapeshellarg($pdfPath) . ' 2>/dev/null | head -1', $output);
            $pageCount = (int)($output[0] ?? 1);
            $pageCount = max(1, min($pageCount, 20));

            for ($i = 0; $i < $pageCount; $i++) {
                $pagePath = "{$basePath}_p{$i}.png";
                $cmd = sprintf(
                    'convert -density 300 %s -quality 100 -colorspace Gray %s',
                    escapeshellarg($pdfPath . "[{$i}]"),
                    escapeshellarg($pagePath)
                );
                exec($cmd . ' 2>&1', $out, $ret);
                if ($ret === 0 && file_exists($pagePath)) {
                    $pages[] = $pagePath;
                }
            }
        }

        if (empty($pages)) {
            $singlePage = $this->convertPdfToImage($pdfPath);
            if ($singlePage !== $pdfPath) {
                $pages[] = $singlePage;
            }
        }

        return $pages;
    }

    private function isPdf(string $filePath): bool
    {
        $mime = mime_content_type($filePath) ?: '';
        return $mime === 'application/pdf' || str_ends_with(strtolower($filePath), '.pdf');
    }

    private function commandExists(string $command): bool
    {
        exec("which {$command} 2>/dev/null", $output, $returnCode);
        return $returnCode === 0;
    }
}
