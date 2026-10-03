<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\Cache\Clearer;

/**
 * Temporary instrumentation for the Back Office cache clear action.
 *
 * @internal
 */
final class CacheClearBenchmark
{
    private static ?int $runStartedAt = null;

    private static ?string $logFile = null;

    public static function start(string $projectDir): void
    {
        self::$runStartedAt = hrtime(true);
        self::$logFile = $projectDir . DIRECTORY_SEPARATOR . '_log' . DIRECTORY_SEPARATOR . '_log_cache_clear.log';

        self::log('=== Cache clear run started at ' . date('c') . ' ===');
        self::log('clearCacheAction started');
    }

    public static function syncCompleted(): void
    {
        if (null === self::$runStartedAt) {
            return;
        }

        self::log('Synchronous part duration: ' . self::durationSince(self::$runStartedAt));
    }

    public static function shutdownStarted(): void
    {
        if (null === self::$runStartedAt) {
            return;
        }

        self::log('Shutdown callback started');
    }

    public static function shutdownCompleted(): void
    {
        if (null === self::$runStartedAt) {
            return;
        }

        self::log('Shutdown callback completed');
        self::log('Total duration: ' . self::durationSince(self::$runStartedAt));
        self::log('=== Cache clear run completed ===');
    }

    public static function commandStarted(string $command, string $appId, string $environment, bool $debug): ?int
    {
        if (null === self::$runStartedAt) {
            return null;
        }

        self::log(sprintf('%s started: app=%s env=%s debug=%s', $command, $appId, $environment, $debug ? 'on' : 'off'));

        return hrtime(true);
    }

    public static function commandCompleted(?int $startedAt, string $command, string $appId, string $environment, bool $debug): void
    {
        if (null === $startedAt) {
            return;
        }

        self::log(sprintf('%s duration: %s app=%s env=%s debug=%s', $command, self::durationSince($startedAt), $appId, $environment, $debug ? 'on' : 'off'));
    }

    private static function durationSince(int $startedAt): string
    {
        return number_format((hrtime(true) - $startedAt) / 1_000_000, 3, '.', '') . ' ms';
    }

    private static function log(string $message): void
    {
        if (null === self::$logFile) {
            return;
        }

        file_put_contents(self::$logFile, $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
