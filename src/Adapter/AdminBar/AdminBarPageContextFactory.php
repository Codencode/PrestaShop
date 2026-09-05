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
        // TODO <cnc> ===== Front admin bar ===== AdminBarPageContextFactory::create() - DA VERIFICARE
        // TODO <cnc-notice> ===== Front admin bar ===== AdminBarPageContextFactory::create() ///////////////////////// SONO ARRIVATO QUI
        // Arrivati: provider taggati per prodotto, categoria e CMS; la factory non conosce i controller concreti.
        // Proseguire: aggiungere permission checker DBAL, provider Core dell'azione e redirector BO.
        if (!method_exists($controller, 'getControllerName')) {
            return null;
        }

        $pageName = $controller->getControllerName();
        if (!is_string($pageName) || $pageName === '') {
            return null;
        }

        foreach ($this->resourceProviders as $resourceProvider) {
            $resource = $resourceProvider->getResource($controller);
            if ($resource !== null) {
                return new AdminBarPageContext($controller, $pageName, $resource->getId(), $resource->getType());
            }
        }

        return new AdminBarPageContext($controller, $pageName);
    }
}