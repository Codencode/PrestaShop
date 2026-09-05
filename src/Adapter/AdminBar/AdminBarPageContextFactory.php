<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\AdminBar;

use ProductControllerCore;
use Throwable;

final class AdminBarPageContextFactory
{
    public function create(object $controller): ?AdminBarPageContext
    {
        // TODO <cnc> ===== Front admin bar ===== AdminBarPageContextFactory::create() - DA VERIFICARE
        // Ricavare gli ID dalle API pubbliche dei controller concreti, senza usare Tools o body class.
        if (!method_exists($controller, 'getControllerName')) {
            return null;
        }

        $pageName = $controller->getControllerName();
        if (!is_string($pageName) || $pageName === '') {
            return null;
        }

        return new AdminBarPageContext($controller, $pageName, $this->getResourceId($controller));
    }

    private function getResourceId(object $controller): ?int
    {
        // TODO <cnc-notice> ===== Front admin bar ===== AdminBarPageContextFactory::getResourceId() ///////////////////////// SONO ARRIVATO QUI
        // Il branch "feature/front-office-admin-bar-COPIA-02-page-context-providers" è una copia creata prima
        // di provare provider separati e taggati: ciascuno riconosce il proprio controller e
        // restituisce tipo e ID risorsa, senza estendere questa factory.
        // TODO <cnc> ===== Front admin bar ===== AdminBarPageContextFactory::getResourceId() - DA VERIFICARE
        // Arrivati: il caso prodotto è riconosciuto dal controller e l'ID proviene da getProduct().
        // Proseguire: verificare API pubbliche equivalenti per categoria e CMS; poi aggiungere il permission
        // checker DBAL, il provider dell'azione product_edit e il redirector BO.

        try {
            switch (true) {
                case $controller instanceof ProductControllerCore:
                    $resourceId = (int) $controller->getProduct()?->id;
                    break;
                case $controller instanceof CategoryControllerCore:
                    $resourceId = (int) $controller->getCategory()->id;
                    break;
                case $controller instanceof CmsControllerCore:
                    $resourceId = (int) ($controller->getCms()?->id ?? $controller->getCmsCategory()?->id);
                    break;
                default:
                    return null;
            }

            return $resourceId > 0 ? $resourceId : null;
        } catch (Throwable) {
            return null;
        }
    }
}
