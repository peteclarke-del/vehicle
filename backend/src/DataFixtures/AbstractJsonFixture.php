<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Base fixture loader for JSON-backed fixture datasets.
 */
abstract class AbstractJsonFixture extends Fixture
{
    /**
     * Return the filename (basename) under backend/data to load.
     *
     * @return string
     */
    abstract protected function getDataFilename(): string;

    /**
     * Process a single decoded JSON item. Implement in subclasses.
     *
     * @param mixed $item
     * @param ObjectManager $manager
     *
     * @return void
     */
    abstract protected function processItem(mixed $item, ObjectManager $manager): void;

    /**
     * Load the JSON file and pass each item to the subclass for processing.
     *
     * @param ObjectManager $manager
     *
     * @return void
     */
    public function load(ObjectManager $manager): void
    {
        $basePath = __DIR__ . '/../../data/' . $this->getDataFilename();

        // Discover JSON files: support single file, a directory (recursive), or glob pattern
        $files = [];
        if (is_file($basePath)) {
            $files[] = $basePath;
        } elseif (is_dir($basePath)) {
            $ri = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($basePath));
            foreach ($ri as $f) {
                if ($f->isFile() && strtolower($f->getExtension()) === 'json') {
                    $files[] = $f->getPathname();
                }
            }
        } else {
            // treat as glob pattern
            $globbed = glob($basePath);
            if ($globbed !== false) {
                foreach ($globbed as $g) {
                    if (is_file($g) && strtolower(pathinfo($g, PATHINFO_EXTENSION)) === 'json') {
                        $files[] = $g;
                    }
                }
            }
        }

        if (count($files) === 0) {
            // nothing to load
            return;
        }

        // Decode and merge items from all files into a single array of items.
        // Annotate items with context derived from the file path (e.g. type/make)
        $items = [];
        $dataRoot = realpath(__DIR__ . '/../../data/') ?: (__DIR__ . '/../../data/');
        foreach ($files as $file) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if ($decoded === null) {
                continue;
            }

            // derive relative path pieces to infer type/make or consumable folders
            $rel = ltrim(str_replace($dataRoot, '', realpath($file)), '/\\');
            $parts = preg_split('#[\\/]+#', $rel);
            $inConsumablesFolder = isset($parts[0]) && $parts[0] === 'consumables';
            $typeFromPath = $inConsumablesFolder ? ($parts[1] ?? null) : ($parts[0] ?? null);
            $makeFromPath = null;
            $isMakeOrModelDir = !$inConsumablesFolder
                && isset($parts[1])
                && in_array($parts[1], ['makes', 'models'])
                && isset($parts[2]);
            if ($isMakeOrModelDir) {
                $makeFromPath = pathinfo($parts[2], PATHINFO_FILENAME);
            }

            // Normalize decoded payload into an array of items
            if (is_array($decoded)) {
                $isAssoc = array_keys($decoded) !== range(0, count($decoded) - 1);
                if ($isAssoc) {
                    // single object
                    $d = $decoded;
                    if ($typeFromPath && !isset($d['type']) && !isset($d['vehicleType'])) {
                        $d['type'] = $typeFromPath;
                    }
                    if ($makeFromPath && !isset($d['make'])) {
                        $d['make'] = $makeFromPath;
                    }
                    // for consumables keep vehicleType key too
                    if ($inConsumablesFolder && $typeFromPath && !isset($d['vehicleType'])) {
                        $d['vehicleType'] = $typeFromPath;
                    }
                    $items[] = $d;
                } else {
                    foreach ($decoded as $d) {
                        if (!is_array($d)) {
                            continue;
                        }
                        if ($typeFromPath && !isset($d['type']) && !isset($d['vehicleType'])) {
                            $d['type'] = $typeFromPath;
                        }
                        if ($makeFromPath && !isset($d['make'])) {
                            $d['make'] = $makeFromPath;
                        }
                        if ($inConsumablesFolder && $typeFromPath && !isset($d['vehicleType'])) {
                            $d['vehicleType'] = $typeFromPath;
                        }
                        $items[] = $d;
                    }
                }
            }
        }

        if (count($items) === 0) {
            return;
        }

        // Allow subclasses to preload caches or do setup before processing.
        if (method_exists($this, 'beforeLoad')) {
            $this->beforeLoad($manager, $items);
        }

        $batchSize = $this->getBatchSize();
        $count = 0;
        foreach ($items as $item) {
            $this->processItem($item, $manager);
            $count++;
            if ($batchSize > 0 && $count % $batchSize === 0) {
                // flush periodically to reduce memory and IO batching
                $manager->flush();
            }
        }

        // final flush for any remaining items
        $manager->flush();
    }

    /**
     * Optional hook subclasses may implement to preload caches.
     *
     * @param ObjectManager $manager Doctrine object manager.
     * @param array         $data    Decoded JSON array to be processed.
     *
     * @return void
     */
    protected function beforeLoad(ObjectManager $manager, array $data): void
    {
        // noop by default
    }

    /**
     * Batch size for periodic flushes. Return 0 to disable batching.
     */
    protected function getBatchSize(): int
    {
        return 100;
    }
}
