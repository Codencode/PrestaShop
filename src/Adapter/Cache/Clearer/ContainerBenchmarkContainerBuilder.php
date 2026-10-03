<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\Cache\Clearer;

use Symfony\Component\DependencyInjection\Compiler\Compiler;
use Symfony\Component\HttpKernel\DependencyInjection\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\EnvParameterException;

/**
 * @internal
 */
final class ContainerBenchmarkContainerBuilder extends ContainerBuilder
{
    private ?ContainerBenchmarkCompiler $benchmarkCompiler = null;

    public function __construct(
        private readonly string $projectDir,
        private readonly string $environment,
        private readonly bool $debug,
        private readonly string $app,
    ) {
        parent::__construct();
    }

    public function compile(bool $resolveEnvPlaceholders = false): void
    {
        ContainerBenchmark::start(
            $this->projectDir,
            $this->environment,
            $this->debug,
            $this->app,
            count($this->getDefinitions()),
            count($this->getAliases()),
        );

        try {
            parent::compile($resolveEnvPlaceholders);
        } finally {
            ContainerBenchmark::compilationCompleted(
                count($this->getDefinitions()),
                count($this->getAliases()),
            );
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
            $passGroups = $this->getPassGroups();
            foreach ($this->getPassConfig()->getPasses() as $pass) {
                $passStartedAt = hrtime(true);
                try {
                    if ($pass instanceof MergeExtensionConfigurationPass) {
                        $reflection = new \ReflectionProperty(MergeExtensionConfigurationPass::class, 'extensions');
                        $extensions = $reflection->getValue($pass);
                        (new ContainerBenchmarkMergeExtensionConfigurationPass($extensions))->process($container);
                    } else {
                        $pass->process($container);
                    }
                } finally {
                    ContainerBenchmark::compilerPassCompleted(
                        $pass::class,
                        $passGroups[spl_object_id($pass)] ?? 'unknown',
                        hrtime(true) - $passStartedAt,
                    );
                }
            }
        } catch (\Exception $e) {
            $usedEnvs = [];
            $previousException = $e;

            do {
                $message = $previousException->getMessage();

                if ($message !== $resolvedMessage = $container->resolveEnvPlaceholders($message, null, $usedEnvs)) {
                    $reflection = new \ReflectionProperty($previousException, 'message');
                    $reflection->setValue($previousException, $resolvedMessage);
                }
            } while ($previousException = $previousException->getPrevious());

            if ($usedEnvs) {
                $e = new EnvParameterException($usedEnvs, $e);
            }

            throw $e;
        } finally {
            $this->getServiceReferenceGraph()->clear();
            ContainerBenchmark::compilerPassesCompleted();
        }
    }

    /**
     * @return array<int, string>
     */
    private function getPassGroups(): array
    {
        $passConfig = $this->getPassConfig();
        $passGroups = [spl_object_id($passConfig->getMergePass()) => 'merge'];

        foreach ([
            'beforeOptimization' => $passConfig->getBeforeOptimizationPasses(),
            'optimization' => $passConfig->getOptimizationPasses(),
            'beforeRemoving' => $passConfig->getBeforeRemovingPasses(),
            'removing' => $passConfig->getRemovingPasses(),
            'afterRemoving' => $passConfig->getAfterRemovingPasses(),
        ] as $group => $passes) {
            foreach ($passes as $pass) {
                $passGroups[spl_object_id($pass)] = $group;
            }
        }

        return $passGroups;
    }
}
