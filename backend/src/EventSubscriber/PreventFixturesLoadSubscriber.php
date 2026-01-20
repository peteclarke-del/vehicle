<?php
// phpcs:ignoreFile
namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Prevents accidental running of fixtures that purge the development DB.
 *
 * Behaviour:
 *  - Allowed if APP_ENV=test or FORCE_FIXTURES=1.
 *  - Allowed if the command is run with an explicitly whitelisted flag.
 *
 * Whitelist is configurable via the `FIXTURES_WHITELIST` env var, for example:
 *
 *   FIXTURES_WHITELIST="--append,-n"
 *
 * @package App\EventSubscriber
 * @author Your Name <you@example.com>
 * @license https://opensource.org/licenses/MIT MIT
 * @link https://example.local/
 */
class PreventFixturesLoadSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array<string,string>
     */
    public static function getSubscribedEvents(): array
    {
        return [ConsoleEvents::COMMAND => 'onConsoleCommand'];
    }

    /**
     * Handle console commands and prevent dangerous fixture runs.
     *
     * @param ConsoleCommandEvent $event The console event instance.
     *
     * @return void
     */
    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        if (!$command) {
            return;
        }

        $name = $command->getName();
        if ($name !== 'doctrine:fixtures:load') {
            return;
        }

        $input = $event->getInput();

        // Allow when explicitly configured
        $appEnv = getenv('APP_ENV') ?: ($_SERVER['APP_ENV'] ?? null);
        $force = getenv('FORCE_FIXTURES') ?: ($_SERVER['FORCE_FIXTURES'] ?? null);
        if ($appEnv === 'test' || $force === '1') {
            return;
        }

        // Check the configured whitelist
        $whitelistEnv = getenv('FIXTURES_WHITELIST');
        $whitelist = $whitelistEnv ?: ($_SERVER['FIXTURES_WHITELIST'] ?? '');
        $allowed = $this->_hasWhitelistedFlag($input, $whitelist);
        if ($allowed) {
            return;
        }

        // Default helpful message (kept under 85 chars per line for linters)
        throw new \RuntimeException(
            "Refusing to run 'doctrine:fixtures:load' in a non-test environment.\n"
            . "To proceed do one of:\n"
            . " - set APP_ENV=test\n"
            . " - export FORCE_FIXTURES=1\n"
            . " - set FIXTURES_WHITELIST to allowed flags (comma separated)\n"
            . "This guard prevents accidental purging of your development DB."
        );
    }

    /**
     * Returns true if any whitelisted flag is present on the input.
     *
     * @param InputInterface|null $input The console input object, or null.
     * @param string $whitelistCommaSeparated Comma-separated whitelist flags.
     *
     * @return bool True when a whitelisted flag is present.
     */
    private function _hasWhitelistedFlag(?InputInterface $input, string $whitelistCommaSeparated): bool
    {
        if (!$input || trim($whitelistCommaSeparated) === '') {
            // fallback: allow --append by default for convenience
            if (!$input) {
                return false;
            }
            $hasAppendTrue = $input->hasParameterOption(['--append', '-a'], true);
            $hasAppendFalse = $input->hasParameterOption(['--append', '-a'], false);
            return $hasAppendTrue || $hasAppendFalse;
        }

        $parts = array_map('trim', explode(',', $whitelistCommaSeparated));
        foreach ($parts as $p) {
            if ($p === '') {
                continue;
            }
            // normalize: ensure flag starts with dash
            if ($p[0] !== '-') {
                $p = '--' . $p;
            }
            $hasTrue = $input->hasParameterOption([$p], true);
            $hasFalse = $input->hasParameterOption([$p], false);
            if ($hasTrue || $hasFalse) {
                return true;
            }
        }
        return false;
    }
}
