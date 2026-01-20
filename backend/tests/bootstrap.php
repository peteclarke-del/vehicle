<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (file_exists(dirname(__DIR__).'/config/bootstrap.php')) {
    require dirname(__DIR__).'/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// Additionally, proactively reset the protected static `booted` flag on any test
// classes under the `tests/` directory that may inherit it. This prevents the
// `WebTestCase::createClient()` LogicException if a prior static boot flag was
// left set on a subclass.
$testsDir = __DIR__;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testsDir)) as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $contents = file_get_contents($file->getPathname());
    if (!preg_match('/namespace\s+([^;]+);/m', $contents, $ns)) {
        continue;
    }
    if (!preg_match('/class\s+(\w+)/m', $contents, $cn)) {
        continue;
    }
    $fqcn = trim($ns[1]) . '\\' . $cn[1];
    if (!class_exists($fqcn)) {
        // Attempt to load the class via autoload
        try {
            require_once $file->getPathname();
        } catch (\Throwable $e) {
            continue;
        }
    }
    try {
        $refClass = new \ReflectionClass($fqcn);
        if ($refClass->isSubclassOf(\Symfony\Bundle\FrameworkBundle\Test\KernelTestCase::class)) {
            $prop = new \ReflectionProperty($fqcn, 'booted');
            $prop->setAccessible(true);
            $prop->setValue(null, false);
        }
    } catch (\ReflectionException) {
        // ignore classes we can't reflect
    }
}

// Set test environment
$_SERVER['APP_ENV'] = 'test';
$_ENV['APP_ENV'] = 'test';

// Ensure any leftover booted kernel state is cleared before tests run. Some test
// helpers may boot the kernel during static setup; calling the protected
// KernelTestCase::ensureKernelShutdown() via reflection guarantees a clean
// start for `WebTestCase::createClient()`.
if (class_exists(\Symfony\Bundle\FrameworkBundle\Test\KernelTestCase::class)) {
    $ref = new \ReflectionMethod(\Symfony\Bundle\FrameworkBundle\Test\KernelTestCase::class, 'ensureKernelShutdown');
    $ref->setAccessible(true);
    $ref->invoke(null);
    // Also ensure the static boot flag is false in case it was left set.
    try {
        $prop = new \ReflectionProperty(
            \Symfony\Bundle\FrameworkBundle\Test\KernelTestCase::class,
            'booted'
        );
        $prop->setAccessible(true);
        $prop->setValue(null, false);
    } catch (\ReflectionException $e) {
        // ignore if reflection fails
    }
}
