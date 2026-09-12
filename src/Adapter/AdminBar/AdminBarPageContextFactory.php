<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\AdminBar;

final class AdminBarPageContextFactory
{
    /** @param iterable<AdminBarResourceProviderInterface> $resourceProviders */
    public function __construct(private readonly iterable $resourceProviders)
    {
    }

    public function create(object $controller): ?AdminBarPageContext
    {
        // TODO <cnc> ===== Front admin bar ===== - ESTENSIONE PER MODULI - AdminBarPageContextFactory::create() - DA VERIFICARE
        // Aggiungere un test con ModuleFrontController per ownerModule e controller normalizzato.
        if (!method_exists($controller, 'getPageName')) {
            return null;
        }

        $pageName = $controller->getPageName();
        if (!is_string($pageName) || $pageName === '') {
            return null;
        }

        $ownerModule = null;
        $controllerName = $pageName;
        if ($controller instanceof \ModuleFrontController && $controller->module instanceof \Module) {
            $ownerModule = $controller->module->name;
            $modulePagePrefix = 'module-' . $ownerModule . '-';
            if (str_starts_with($pageName, $modulePagePrefix)) {
                $controllerName = substr($pageName, strlen($modulePagePrefix));
            }
        }

        foreach ($this->resourceProviders as $resourceProvider) {
            $resource = $resourceProvider->getResource($controller);
            if ($resource !== null) {
                return new AdminBarPageContext(
                    $controller,
                    $pageName,
                    $resource->getId(),
                    $resource->getType(),
                    $ownerModule,
                    $controllerName,
                );
            }
        }

        return new AdminBarPageContext($controller, $pageName, null, null, $ownerModule, $controllerName);
    }
}
