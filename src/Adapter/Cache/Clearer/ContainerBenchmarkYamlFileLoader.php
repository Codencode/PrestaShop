<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\Cache\Clearer;

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Benchmark-only YAML loader that measures PrestaShop's top-level service imports.
 *
 * @internal
 */
final class ContainerBenchmarkYamlFileLoader extends YamlFileLoader
{
    public function import(
        mixed $resource,
        ?string $type = null,
        bool|string $ignoreErrors = false,
        ?string $sourceResource = null,
        $exclude = null,
    ): mixed {
        $group = is_string($resource) ? self::getBenchmarkGroup($resource) : null;
        if (null === $group || !ContainerBenchmark::isRunning()) {
            return parent::import($resource, $type, $ignoreErrors, $sourceResource, $exclude);
        }

        $definitionsBefore = count($this->container->getDefinitions());
        $aliasesBefore = count($this->container->getAliases());
        $startedAt = hrtime(true);

        try {
            return parent::import($resource, $type, $ignoreErrors, $sourceResource, $exclude);
        } finally {
            ContainerBenchmark::prestashopServiceGroupLoadCompleted(
                $group,
                hrtime(true) - $startedAt,
                $definitionsBefore,
                count($this->container->getDefinitions()),
                $aliasesBefore,
                count($this->container->getAliases()),
            );
        }
    }

    public function registerClasses(
        Definition $prototype,
        string $namespace,
        string $resource,
        string|array|null $exclude = null,
    ) {
        if (!ContainerBenchmark::isRunning()) {
            parent::registerClasses(...func_get_args());

            return;
        }

        $source = func_num_args() > 4 ? func_get_arg(4) : null;
        $definitionsBefore = count($this->container->getDefinitions());
        $aliasesBefore = count($this->container->getAliases());
        $startedAt = hrtime(true);

        try {
            parent::registerClasses(...func_get_args());
        } finally {
            ContainerBenchmark::prestashopResourceScanCompleted(
                $namespace,
                $resource,
                is_string($source) ? $source : null,
                hrtime(true) - $startedAt,
                $definitionsBefore,
                count($this->container->getDefinitions()),
                $aliasesBefore,
                count($this->container->getAliases()),
            );
        }
    }

    private static function getBenchmarkGroup(string $resource): ?string
    {
        $resource = str_replace('\\', '/', $resource);

        return match ($resource) {
            'services/bundle/*.yml' => 'services/bundle',
            'services/core/*.yml' => 'services/core',
            'services/adapter/*.yml' => 'services/adapter',
            'services/extra_property/*.yml' => 'services/extra_property',
            'services/legacy.yml' => 'services/legacy.yml',
            'services/alias.yml' => 'services/alias.yml',
            'services/front_legacy.yml' => 'services/front_legacy.yml',
            'services_dev/bundle/*.yml' => 'services_dev/bundle',
            default => null,
        };
    }
}
