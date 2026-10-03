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
    private const REQUEST_STARTED_AT_KEY = 'prestashop_container_benchmark_request_started_at';
    private const REQUEST_STARTED_AT_ISO_KEY = 'prestashop_container_benchmark_request_started_at_iso';
    private const MIN_COMPILER_PASS_LOG_DURATION = 100_000_000; // 100 ms

    private static ?int $requestStartedAt = null;
    private static ?string $requestStartedAtIso = null;
    private static ?string $projectDir = null;
    private static ?string $environment = null;
    private static ?bool $debug = null;
    private static ?string $app = null;
    private static ?int $containerCompilationStartedAt = null;
    private static ?int $containerCompilationDuration = null;
    private static ?int $containerCompilationCompletedAt = null;
    private static ?int $compilerPassesStartedAt = null;
    private static ?int $compilerPassesDuration = null;
    private static ?int $definitionsBeforeCompile = null;
    private static ?int $aliasesBeforeCompile = null;
    private static ?int $definitionsAfterCompile = null;
    private static ?int $aliasesAfterCompile = null;

    /** @var array<int, array{class: string, group: string, duration: int}> */
    private static array $compilerPasses = [];

    /** @var array<int, array{scope: string, modules: int, files_found: int, already_registered: int, definitions_before: int, definitions_after: int, aliases_before: int, aliases_after: int, duration: int}> */
    private static array $moduleServicesLoads = [];

    /** @var array<string, array{name: string, class: string, prepend_duration: int, load_duration: int, definitions_before: ?int, definitions_after: ?int, aliases_before: ?int, aliases_after: ?int}> */
    private static array $extensionLoads = [];

    /** @var array<int, array{group: string, duration: int, definitions_before: int, definitions_after: int, aliases_before: int, aliases_after: int}> */
    private static array $prestashopServiceGroups = [];

    /** @var array<int, array{namespace: string, resource: string, source: ?string, duration: int, definitions_before: int, definitions_after: int, aliases_before: int, aliases_after: int}> */
    private static array $prestashopResourceScans = [];

    public static function start(
        string $projectDir,
        string $environment,
        bool $debug,
        string $app,
        int $definitionsBeforeCompile,
        int $aliasesBeforeCompile,
    ): void {
        self::$containerCompilationStartedAt = hrtime(true);
        self::$containerCompilationDuration = null;
        self::$containerCompilationCompletedAt = null;
        self::$compilerPassesStartedAt = null;
        self::$compilerPassesDuration = null;
        self::$compilerPasses = [];
        self::$moduleServicesLoads = [];
        self::$extensionLoads = [];
        self::$prestashopServiceGroups = [];
        self::$prestashopResourceScans = [];
        self::$projectDir = $projectDir;
        self::$environment = $environment;
        self::$debug = $debug;
        self::$app = $app;
        self::$definitionsBeforeCompile = $definitionsBeforeCompile;
        self::$aliasesBeforeCompile = $aliasesBeforeCompile;
        self::$definitionsAfterCompile = null;
        self::$aliasesAfterCompile = null;

        $requestStartedAt = $_SERVER[self::REQUEST_STARTED_AT_KEY] ?? null;
        $requestStartedAtIso = $_SERVER[self::REQUEST_STARTED_AT_ISO_KEY] ?? null;
        self::$requestStartedAt = is_int($requestStartedAt) ? $requestStartedAt : null;
        self::$requestStartedAtIso = is_string($requestStartedAtIso) ? $requestStartedAtIso : null;
    }

    public static function isRunning(): bool
    {
        return null !== self::$containerCompilationStartedAt;
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

    public static function compilerPassCompleted(string $class, string $group, int $duration): void
    {
        if (null === self::$containerCompilationStartedAt) {
            return;
        }

        self::$compilerPasses[] = [
            'class' => $class,
            'group' => $group,
            'duration' => $duration,
        ];
    }

    public static function moduleServicesLoadCompleted(
        string $scope,
        int $modules,
        int $filesFound,
        int $alreadyRegistered,
        int $definitionsBefore,
        int $definitionsAfter,
        int $aliasesBefore,
        int $aliasesAfter,
        int $duration,
    ): void {
        if (null === self::$containerCompilationStartedAt) {
            return;
        }

        self::$moduleServicesLoads[] = [
            'scope' => $scope,
            'modules' => $modules,
            'files_found' => $filesFound,
            'already_registered' => $alreadyRegistered,
            'definitions_before' => $definitionsBefore,
            'definitions_after' => $definitionsAfter,
            'aliases_before' => $aliasesBefore,
            'aliases_after' => $aliasesAfter,
            'duration' => $duration,
        ];
    }

    public static function extensionPrependCompleted(string $name, string $class, int $duration): void
    {
        if (null === self::$containerCompilationStartedAt) {
            return;
        }

        self::initializeExtensionLoad($name, $class);
        self::$extensionLoads[$name]['prepend_duration'] += $duration;
    }

    public static function extensionLoadCompleted(
        string $name,
        string $class,
        int $duration,
        int $definitionsBefore,
        int $definitionsAfter,
        int $aliasesBefore,
        int $aliasesAfter,
    ): void {
        if (null === self::$containerCompilationStartedAt) {
            return;
        }

        self::initializeExtensionLoad($name, $class);
        self::$extensionLoads[$name]['load_duration'] += $duration;
        self::$extensionLoads[$name]['definitions_before'] ??= $definitionsBefore;
        self::$extensionLoads[$name]['definitions_after'] = $definitionsAfter;
        self::$extensionLoads[$name]['aliases_before'] ??= $aliasesBefore;
        self::$extensionLoads[$name]['aliases_after'] = $aliasesAfter;
    }

    public static function prestashopServiceGroupLoadCompleted(
        string $group,
        int $duration,
        int $definitionsBefore,
        int $definitionsAfter,
        int $aliasesBefore,
        int $aliasesAfter,
    ): void {
        if (null === self::$containerCompilationStartedAt) {
            return;
        }

        self::$prestashopServiceGroups[] = [
            'group' => $group,
            'duration' => $duration,
            'definitions_before' => $definitionsBefore,
            'definitions_after' => $definitionsAfter,
            'aliases_before' => $aliasesBefore,
            'aliases_after' => $aliasesAfter,
        ];
    }

    public static function prestashopResourceScanCompleted(
        string $namespace,
        string $resource,
        ?string $source,
        int $duration,
        int $definitionsBefore,
        int $definitionsAfter,
        int $aliasesBefore,
        int $aliasesAfter,
    ): void {
        if (null === self::$containerCompilationStartedAt) {
            return;
        }

        self::$prestashopResourceScans[] = [
            'namespace' => $namespace,
            'resource' => $resource,
            'source' => $source,
            'duration' => $duration,
            'definitions_before' => $definitionsBefore,
            'definitions_after' => $definitionsAfter,
            'aliases_before' => $aliasesBefore,
            'aliases_after' => $aliasesAfter,
        ];
    }

    public static function compilationCompleted(int $definitionsAfterCompile, int $aliasesAfterCompile): void
    {
        if (null === self::$containerCompilationStartedAt) {
            return;
        }

        self::$containerCompilationDuration = hrtime(true) - self::$containerCompilationStartedAt;
        self::$containerCompilationCompletedAt = hrtime(true);
        self::$definitionsAfterCompile = $definitionsAfterCompile;
        self::$aliasesAfterCompile = $aliasesAfterCompile;

        if (null === self::$requestStartedAt) {
            self::writeLog();
        }
    }

    public static function requestCompleted(): void
    {
        if (null === self::$containerCompilationCompletedAt) {
            return;
        }

        self::writeLog();
    }

    private static function writeLog(): void
    {
        if (null === self::$containerCompilationStartedAt || null === self::$containerCompilationCompletedAt || null === self::$projectDir) {
            return;
        }

        $completedAt = hrtime(true);
        $logDirectory = self::$projectDir . DIRECTORY_SEPARATOR . '_log';
        if (!is_dir($logDirectory)) {
            @mkdir($logDirectory, 0775, true);
        }

        $requestMetrics = '';
        $executionContext = 'cli';
        if (null !== self::$requestStartedAt && null !== self::$requestStartedAtIso) {
            $executionContext = 'http';
            $requestMetrics = sprintf(
                "Before container compilation: %s ms\nAfter container compilation: %s ms\nTotal request: %s ms\n",
                self::formatDuration(self::$containerCompilationStartedAt - self::$requestStartedAt),
                self::formatDuration($completedAt - self::$containerCompilationCompletedAt),
                self::formatDuration($completedAt - self::$requestStartedAt),
            );
        }

        $log = sprintf(
            "=== RUN %s ===\nEnvironment: %s\nDebug: %s\nApp: %s\nExecution context: %s\n%sContainer compilation: %s ms\nCompiler passes: %s ms\nContainer definitions: %d -> %d\nContainer aliases: %d -> %d\nModule services loading:\n%sExtension configuration loading (slowest first):\n%sPrestaShop service configuration (slowest first):\n%sPrestaShop resource scans (slowest first):\n%sCompiler pass ranking (>= 100 ms, slowest first):\n%s=== END RUN ===\n",
            self::$requestStartedAtIso ?? date('c'),
            self::$environment ?? 'n/a',
            self::$debug ? 'true' : 'false',
            self::$app ?? 'n/a',
            $executionContext,
            $requestMetrics,
            self::formatDuration(self::$containerCompilationDuration),
            self::formatDuration(self::$compilerPassesDuration),
            self::$definitionsBeforeCompile ?? 0,
            self::$definitionsAfterCompile ?? 0,
            self::$aliasesBeforeCompile ?? 0,
            self::$aliasesAfterCompile ?? 0,
            self::formatModuleServicesLoads(),
            self::formatExtensionLoads(),
            self::formatPrestaShopServiceGroups(),
            self::formatPrestaShopResourceScans(),
            self::formatCompilerPasses(),
        );

        @file_put_contents(
            $logDirectory . DIRECTORY_SEPARATOR . 'container-benchmark.log',
            $log,
            FILE_APPEND | LOCK_EX,
        );

        self::reset();
    }

    private static function reset(): void
    {
        self::$requestStartedAt = null;
        self::$requestStartedAtIso = null;
        self::$projectDir = null;
        self::$environment = null;
        self::$debug = null;
        self::$app = null;
        self::$containerCompilationStartedAt = null;
        self::$containerCompilationDuration = null;
        self::$containerCompilationCompletedAt = null;
        self::$compilerPassesStartedAt = null;
        self::$compilerPassesDuration = null;
        self::$definitionsBeforeCompile = null;
        self::$aliasesBeforeCompile = null;
        self::$definitionsAfterCompile = null;
        self::$aliasesAfterCompile = null;
        self::$compilerPasses = [];
        self::$moduleServicesLoads = [];
        self::$extensionLoads = [];
        self::$prestashopServiceGroups = [];
        self::$prestashopResourceScans = [];
    }

    private static function formatDuration(?int $duration): string
    {
        if (null === $duration) {
            return 'n/a';
        }

        return number_format($duration / 1_000_000, 3, '.', '');
    }

    private static function formatModuleServicesLoads(): string
    {
        if (!self::$moduleServicesLoads) {
            return "  (none)\n";
        }

        $lines = [];
        foreach (self::$moduleServicesLoads as $load) {
            $lines[] = sprintf(
                "  %s: %s ms | modules: %d | files: %d | already registered: %d | new: %d | definitions: %d -> %d | aliases: %d -> %d",
                $load['scope'],
                self::formatDuration($load['duration']),
                $load['modules'],
                $load['files_found'],
                $load['already_registered'],
                $load['files_found'] - $load['already_registered'],
                $load['definitions_before'],
                $load['definitions_after'],
                $load['aliases_before'],
                $load['aliases_after'],
            );
        }

        return implode("\n", $lines) . "\n";
    }

    private static function initializeExtensionLoad(string $name, string $class): void
    {
        self::$extensionLoads[$name] ??= [
            'name' => $name,
            'class' => $class,
            'prepend_duration' => 0,
            'load_duration' => 0,
            'definitions_before' => null,
            'definitions_after' => null,
            'aliases_before' => null,
            'aliases_after' => null,
        ];
    }

    private static function formatExtensionLoads(): string
    {
        if (!self::$extensionLoads) {
            return "  (none)\n";
        }

        $loads = array_values(self::$extensionLoads);
        usort(
            $loads,
            static fn (array $left, array $right): int =>
                ($right['prepend_duration'] + $right['load_duration']) <=> ($left['prepend_duration'] + $left['load_duration']),
        );

        $lines = [];
        foreach ($loads as $load) {
            $definitions = null === $load['definitions_before']
                ? 'n/a'
                : sprintf('%d -> %d', $load['definitions_before'], $load['definitions_after']);
            $aliases = null === $load['aliases_before']
                ? 'n/a'
                : sprintf('%d -> %d', $load['aliases_before'], $load['aliases_after']);

            $lines[] = sprintf(
                '  %s ms %s | prepend: %s ms | load+merge: %s ms | definitions: %s | aliases: %s | %s',
                self::formatDuration($load['prepend_duration'] + $load['load_duration']),
                $load['name'],
                self::formatDuration($load['prepend_duration']),
                self::formatDuration($load['load_duration']),
                $definitions,
                $aliases,
                $load['class'],
            );
        }

        return implode("\n", $lines) . "\n";
    }

    private static function formatPrestaShopServiceGroups(): string
    {
        if (!self::$prestashopServiceGroups) {
            return "  (none)\n";
        }

        $groups = self::$prestashopServiceGroups;
        usort(
            $groups,
            static fn (array $left, array $right): int => $right['duration'] <=> $left['duration'],
        );

        $lines = [];
        foreach ($groups as $group) {
            $definitionsDelta = $group['definitions_after'] - $group['definitions_before'];
            $aliasesDelta = $group['aliases_after'] - $group['aliases_before'];
            $lines[] = sprintf(
                '  %s ms %s | definitions: %d -> %d (%+d) | aliases: %d -> %d (%+d)',
                self::formatDuration($group['duration']),
                $group['group'],
                $group['definitions_before'],
                $group['definitions_after'],
                $definitionsDelta,
                $group['aliases_before'],
                $group['aliases_after'],
                $aliasesDelta,
            );
        }

        return implode("\n", $lines) . "\n";
    }

    private static function formatPrestaShopResourceScans(): string
    {
        if (!self::$prestashopResourceScans) {
            return "  (none)\n";
        }

        $scans = self::$prestashopResourceScans;
        usort(
            $scans,
            static fn (array $left, array $right): int => $right['duration'] <=> $left['duration'],
        );

        $lines = [];
        foreach ($scans as $scan) {
            $definitionsDelta = $scan['definitions_after'] - $scan['definitions_before'];
            $aliasesDelta = $scan['aliases_after'] - $scan['aliases_before'];
            $source = null === $scan['source'] ? 'n/a' : basename($scan['source']);
            $lines[] = sprintf(
                '  %s ms %s | resource: %s | source: %s | definitions: %d -> %d (%+d) | aliases: %d -> %d (%+d)',
                self::formatDuration($scan['duration']),
                $scan['namespace'],
                $scan['resource'],
                $source,
                $scan['definitions_before'],
                $scan['definitions_after'],
                $definitionsDelta,
                $scan['aliases_before'],
                $scan['aliases_after'],
                $aliasesDelta,
            );
        }

        return implode("\n", $lines) . "\n";
    }

    private static function formatCompilerPasses(): string
    {
        usort(
            self::$compilerPasses,
            static fn (array $left, array $right): int => $right['duration'] <=> $left['duration'],
        );

        $lines = [];
        foreach (self::$compilerPasses as $compilerPass) {
            if (
                $compilerPass['duration'] < self::MIN_COMPILER_PASS_LOG_DURATION
                && !str_ends_with($compilerPass['class'], '\\ModuleControllerRegisterPass')
            ) {
                continue;
            }

            $lines[] = sprintf(
                '  %s ms [%s] %s',
                self::formatDuration($compilerPass['duration']),
                $compilerPass['group'],
                $compilerPass['class'],
            );
        }

        return $lines ? implode("\n", $lines) . "\n" : "  (none)\n";
    }
}
