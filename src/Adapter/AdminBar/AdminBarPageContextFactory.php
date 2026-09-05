<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\AdminBar;

final class AdminBarPageContextFactory
{
    public function create(object $controller): ?AdminBarPageContext
    {
        // TODO <cnc> ===== Front admin bar ===== AdminBarPageContextFactory::create() - DA VERIFICARE
        // I controller risorsa devono esporre esplicitamente il proprio ID, senza usare Tools o la request.
        if (!method_exists($controller, 'getControllerName')) {
            return null;
        }

        $pageName = $controller->getControllerName();
        if (!is_string($pageName) || $pageName === '') {
            return null;
        }

        return new AdminBarPageContext($controller, $pageName);
    }
}
