<?php

declare(strict_types=1);

namespace App\Service\SiteAdapter;

use Psr\Log\LoggerInterface;

class GenericDomAdapter implements SiteAdapterInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function setContext(array $context): void
    {
        // Generic adapter doesn't need context
    }

    public function supports(string $host, string $html): bool
    {
        return true; // Always supports as fallback
    }

    public function parse(string $html): array
    {
        // Try JSON-LD first
        $data = $this->extractJsonLd($html);

        $xp = $this->createXPath($html);

        // Title
        if (!isset($data['name'])) {
            $title = trim($xp->evaluate('string(//meta[@property="og:title"]/@content)'));
            if (!$title) {
                $title = trim($xp->evaluate('string(//h1)'));
            }
            if (!$title) {
                $title = trim($xp->evaluate('string(//title)'));
                // Remove common suffixes
                $title = preg_replace('/\s*[\|\-]\s*.*$/', '', $title);
            }
            if ($title) {
                $data['name'] = $title;
            }
        }

        // Description
        if (!isset($data['description'])) {
            $desc = trim($xp->evaluate('string(//meta[@name="description"]/@content)'));
            if (!$desc) {
                $desc = trim($xp->evaluate('string(//meta[@property="og:description"]/@content)'));
            }
            if ($desc) {
                $data['description'] = $desc;
            }
        }

        // Price (look for elements with "price" class)
        if (!isset($data['price'])) {
            $priceNodes = $xp->query('//*[contains(@class, "price")]');
            foreach ($priceNodes as $node) {
                $priceText = trim($node->textContent);
                if (preg_match('/[£$€]\s*(\d{1,}[,\d]*\.?\d{0,2})\b/', $priceText, $matches)) {
                    $price = str_replace(',', '', $matches[1]);
                    // Validate reasonable price
                    if ((float)$price >= 0.50 && (float)$price <= 999999) {
                        $data['price'] = $price;
                        break;
                    }
                }
            }
        }

        $this->logger->info('Generic: Extracted data', $data);
        return array_filter($data);
    }

    private function extractJsonLd(string $html): array
    {
        $data = [];

        if (!preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/s', $html, $matches)) {
            return $data;
        }

        foreach ($matches[1] as $jsonString) {
            $jsonData = json_decode($jsonString, true);
            if (!$jsonData) {
                continue;
            }

            if (isset($jsonData['name']) && !isset($data['name'])) {
                $data['name'] = $jsonData['name'];
            }

            if (isset($jsonData['offers']['price']) && !isset($data['price'])) {
                $data['price'] = $jsonData['offers']['price'];
            } elseif (isset($jsonData['offers'][0]['price']) && !isset($data['price'])) {
                $data['price'] = $jsonData['offers'][0]['price'];
            }

            if (isset($jsonData['description']) && !isset($data['description'])) {
                $data['description'] = $jsonData['description'];
            }

            if (isset($jsonData['brand']['name']) && !isset($data['manufacturer'])) {
                $data['manufacturer'] = $jsonData['brand']['name'];
            } elseif (isset($jsonData['brand']) && is_string($jsonData['brand']) && !isset($data['manufacturer'])) {
                $data['manufacturer'] = $jsonData['brand'];
            }
        }

        return $data;
    }

    private function createXPath(string $html): \DOMXPath
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        return new \DOMXPath($dom);
    }
}
