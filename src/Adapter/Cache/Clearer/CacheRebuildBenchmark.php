<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\Cache\Clearer;

/**
 * Temporary instrumentation for Admin Symfony cache rebuilds.
 *
 * @internal
 */
final class CacheRebuildBenchmark
{
    private static ?int $rebuildStartedAt = null;

    private static ?int $compilerPassesStartedAt = null;

    private static ?string $logFile = null;

    public static function start(string $projectDir, string $environment): void
    {
        self::$rebuildStartedAt = hrtime(true);
        self::$compilerPassesStartedAt = null;
        self::$logFile = $projectDir . DIRECTORY_SEPARATOR . '_log' . DIRECTORY_SEPARATOR .  '_log_cache_rebuild.log';

        self::log('=== Admin cache rebuild started at ' . date('c') . ' (env=' . $environment . ') ===');
        self::log('Cache rebuild started');
        self::log('Symfony container compilation started');
    }

    public static function compilerPassesStarted(): void
    {
        if (null === self::$rebuildStartedAt) {
            return;
        }

        self::$compilerPassesStartedAt = hrtime(true);
        self::log('Compiler passes started');
    }

    public static function compilerPassesCompleted(): void
    {
        if (null === self::$compilerPassesStartedAt) {
            return;
        }

        self::log('Compiler passes duration: ' . self::durationSince(self::$compilerPassesStartedAt));
    }

    public static function warmupStarted(): ?int
    {
        if (null === self::$rebuildStartedAt) {
            return null;
        }

        self::log('Symfony container compilation duration: ' . self::durationSince(self::$rebuildStartedAt));
        self::log('Cache warmup started');

        return hrtime(true);
    }

    public static function warmupCompleted(?int $warmupStartedAt): void
    {
        if (null === $warmupStartedAt) {
            return;
        }

        self::log('Cache warmup duration: ' . self::durationSince($warmupStartedAt));
    }

    public static function completed(): void
    {
        if (null === self::$rebuildStartedAt) {
            return;
        }

        self::log('Cache rebuild completed');
        self::log('Total duration: ' . self::durationSince(self::$rebuildStartedAt));
        self::log('=== Admin cache rebuild completed ===');

        self::$rebuildStartedAt = null;
        self::$compilerPassesStartedAt = null;
        self::$logFile = null;
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

        $directory = dirname(self::$logFile);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        @file_put_contents(self::$logFile, $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
