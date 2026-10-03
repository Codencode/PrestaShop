<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\Cache\Clearer;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
final class CacheRebuildCompilerPass implements CompilerPassInterface
{
    public function __construct(private readonly bool $startsMeasurement)
    {
    }

    public function process(ContainerBuilder $container): void
    {
        if ($this->startsMeasurement) {
            CacheRebuildBenchmark::compilerPassesStarted();

            return;
        }

        CacheRebuildBenchmark::compilerPassesCompleted();
    }
}
