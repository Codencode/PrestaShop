<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\Cache\Clearer;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * @internal
 */
final class CacheRebuildWarmer implements CacheWarmerInterface
{
    public function __construct(private readonly CacheWarmerInterface $innerWarmer)
    {
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $warmupStartedAt = CacheRebuildBenchmark::warmupStarted();
        try {
            return $this->innerWarmer->warmUp($cacheDir, $buildDir);
        } finally {
            CacheRebuildBenchmark::warmupCompleted($warmupStartedAt);
        }
    }

    public function isOptional(): bool
    {
        return $this->innerWarmer->isOptional();
    }
}
