<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\Cache\Clearer;

use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationParameterBag;
use Symfony\Component\HttpKernel\DependencyInjection\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;

/**
 * Temporary benchmark-only copy of Symfony's MergeExtensionConfigurationPass
 * with per-extension timings.
 *
 * @internal
 */
final class ContainerBenchmarkMergeExtensionConfigurationPass extends MergeExtensionConfigurationPass
{
    /** @param string[] $extensions */
    public function __construct(private readonly array $benchmarkExtensions)
    {
        parent::__construct($benchmarkExtensions);
    }

    public function process(ContainerBuilder $container): void
    {
        // Keep HttpKernel's implicit extension loading exactly as in Symfony.
        foreach ($this->benchmarkExtensions as $extension) {
            if (!count($container->getExtensionConfig($extension))) {
                $container->loadFromExtension($extension, []);
            }
        }

        $parameters = $container->getParameterBag()->all();
        $definitions = $container->getDefinitions();
        $aliases = $container->getAliases();
        $exprLangProviders = $container->getExpressionLanguageProviders();
        $configAvailable = class_exists(BaseNode::class);

        foreach ($container->getExtensions() as $name => $extension) {
            if (!$extension instanceof PrependExtensionInterface) {
                continue;
            }

            $startedAt = hrtime(true);
            try {
                $extension->prepend($container);
            } finally {
                ContainerBenchmark::extensionPrependCompleted($name, $extension::class, hrtime(true) - $startedAt);
            }
        }

        foreach ($container->getExtensions() as $name => $extension) {
            if (!$config = $container->getExtensionConfig($name)) {
                // This extension was not called.
                continue;
            }

            $startedAt = hrtime(true);
            $definitionsBefore = count($container->getDefinitions());
            $aliasesBefore = count($container->getAliases());

            try {
                $resolvingBag = $container->getParameterBag();
                if ($resolvingBag instanceof EnvPlaceholderParameterBag && $extension instanceof Extension) {
                    // Create a dedicated bag so that we can track env vars per-extension.
                    $resolvingBag = new MergeExtensionConfigurationParameterBag($resolvingBag);
                    if ($configAvailable) {
                        BaseNode::setPlaceholderUniquePrefix($resolvingBag->getEnvPlaceholderUniquePrefix());
                    }
                }

                $config = $resolvingBag->resolveValue($config);
                try {
                    $tmpContainer = new MergeExtensionConfigurationContainerBuilder($extension, $resolvingBag);
                    $tmpContainer->setResourceTracking($container->isTrackingResources());
                    $tmpContainer->addObjectResource($extension);
                    if ($extension instanceof ConfigurationExtensionInterface && null !== $configuration = $extension->getConfiguration($config, $tmpContainer)) {
                        $tmpContainer->addObjectResource($configuration);
                    }
                    foreach ($exprLangProviders as $provider) {
                        $tmpContainer->addExpressionLanguageProvider($provider);
                    }

                    $extension->load($config, $tmpContainer);
                } catch (\Exception $e) {
                    if ($resolvingBag instanceof MergeExtensionConfigurationParameterBag) {
                        $container->getParameterBag()->mergeEnvPlaceholders($resolvingBag);
                    }

                    throw $e;
                }

                if ($resolvingBag instanceof MergeExtensionConfigurationParameterBag) {
                    // Don't keep track of env vars that are overridden when configs are merged.
                    $resolvingBag->freezeAfterProcessing($extension, $tmpContainer);
                }

                $container->merge($tmpContainer);
                $container->getParameterBag()->add($parameters);
            } finally {
                ContainerBenchmark::extensionLoadCompleted(
                    $name,
                    $extension::class,
                    hrtime(true) - $startedAt,
                    $definitionsBefore,
                    count($container->getDefinitions()),
                    $aliasesBefore,
                    count($container->getAliases()),
                );
            }
        }

        $container->addDefinitions($definitions);
        $container->addAliases($aliases);
    }
}
