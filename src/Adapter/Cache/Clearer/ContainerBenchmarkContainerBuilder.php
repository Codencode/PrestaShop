<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\Cache\Clearer;

use Symfony\Component\DependencyInjection\Compiler\Compiler;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
final class ContainerBenchmarkContainerBuilder extends ContainerBuilder
{
    private ?ContainerBenchmarkCompiler $benchmarkCompiler = null;

    public function __construct(private readonly string $projectDir)
    {
        parent::__construct();
    }

    public function compile(bool $resolveEnvPlaceholders = false): void
    {
        ContainerBenchmark::start($this->projectDir);
        try {
            parent::compile($resolveEnvPlaceholders);
        } finally {
            ContainerBenchmark::complete();
        }
    }

    public function getCompiler(): Compiler
    {
        return $this->benchmarkCompiler ??= new ContainerBenchmarkCompiler();
    }
}

/**
 * @internal
 */
final class ContainerBenchmarkCompiler extends Compiler
{
    public function compile(ContainerBuilder $container): void
    {
        ContainerBenchmark::compilerPassesStarted();
        try {
            parent::compile($container);
        } finally {
            ContainerBenchmark::compilerPassesCompleted();
        }
    }
}
