<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\Cache\Clearer;

/**
 * Temporary instrumentation for Symfony container compilation.
 *
 * @internal
 */
final class ContainerBenchmark
{
    private static ?int $containerCompilationStartedAt = null;

    private static ?int $compilerPassesStartedAt = null;

    private static ?int $moduleControllerRegisterPassStartedAt = null;

    private static int $modulesProcessed = 0;

    private static int $phpFilesScanned = 0;

    private static int $controllersFound = 0;

    public static function start(string $projectDir): void
    {
        self::$containerCompilationStartedAt = hrtime(true);
        self::$compilerPassesStartedAt = null;
        self::$moduleControllerRegisterPassStartedAt = null;
        self::$compilerPassesDuration = null;
        self::$moduleControllerRegisterPassDuration = null;
        self::$modulesProcessed = 0;
        self::$phpFilesScanned = 0;
        self::$controllersFound = 0;
        self::$projectDir = $projectDir;
    }

    public static function compilerPassesStarted(): void
    {
        if (null !== self::$containerCompilationStartedAt) {
            self::$compilerPassesStartedAt = hrtime(true);
        }
    }

    public static function compilerPassesCompleted(): void
    {
        if (null !== self::$compilerPassesStartedAt) {
            self::$compilerPassesDuration = hrtime(true) - self::$compilerPassesStartedAt;
        }
    }

    public static function moduleControllerRegisterPassStarted(): void
    {
        if (null !== self::$containerCompilationStartedAt) {
            self::$moduleControllerRegisterPassStartedAt = hrtime(true);
        }
    }

    public static function moduleControllerRegisterPassCompleted(): void
    {
        if (null !== self::$moduleControllerRegisterPassStartedAt) {
            self::$moduleControllerRegisterPassDuration = hrtime(true) - self::$moduleControllerRegisterPassStartedAt;
        }
    }

    public static function moduleProcessed(): void
    {
        if (null !== self::$containerCompilationStartedAt) {
            ++self::$modulesProcessed;
        }
    }

    public static function phpFileScanned(): void
    {
        if (null !== self::$containerCompilationStartedAt) {
            ++self::$phpFilesScanned;
        }
    }

    public static function controllerFound(): void
    {
        if (null !== self::$containerCompilationStartedAt) {
            ++self::$controllersFound;
        }
    }

    public static function complete(): void
    {
        if (null === self::$containerCompilationStartedAt || null === self::$projectDir) {
            return;
        }

        $logDirectory = self::$projectDir . DIRECTORY_SEPARATOR . '_log';
        if (!is_dir($logDirectory)) {
            @mkdir($logDirectory, 0775, true);
        }

        $log = sprintf(
            "=== RUN %s ===\nContainer compilation: %s ms\nCompiler passes: %s ms\nModuleControllerRegisterPass: %s ms\nModules processed: %d\nPHP files scanned: %d\nControllers found: %d\n=== END RUN ===\n",
            date('c'),
            self::formatDuration(hrtime(true) - self::$containerCompilationStartedAt),
            self::formatDuration(self::$compilerPassesDuration),
            self::formatDuration(self::$moduleControllerRegisterPassDuration),
            self::$modulesProcessed,
            self::$phpFilesScanned,
            self::$controllersFound,
        );
        @file_put_contents($logDirectory . DIRECTORY_SEPARATOR . 'container-benchmark.log', $log, FILE_APPEND | LOCK_EX);

        self::$containerCompilationStartedAt = null;
        self::$compilerPassesStartedAt = null;
        self::$moduleControllerRegisterPassStartedAt = null;
        self::$projectDir = null;
    }

    private static ?int $compilerPassesDuration = null;

    private static ?int $moduleControllerRegisterPassDuration = null;

    private static ?string $projectDir = null;

    private static function formatDuration(?int $duration): string
    {
        if (null === $duration) {
            return 'n/a';
        }

        return number_format($duration / 1_000_000, 3, '.', '');
    }
}
