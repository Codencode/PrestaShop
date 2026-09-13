<?php

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\AdminBar;

use PrestaShop\PrestaShop\Core\Security\AdminEmployeeContext;

final class ProductAdminBarActionProvider implements AdminBarActionProviderInterface
{
    public function __construct(private readonly AdminBarPermissionChecker $permissionChecker)
    {
    }

    public function getActions(AdminBarPageContext $pageContext, AdminEmployeeContext $employeeContext): iterable
    {
        // TODO <cnc> ===== Front admin bar ===== ProductAdminBarActionProvider::getActions() - DA VERIFICARE
        // Il renderer FO deve accettare solo azioni con un endpoint BO locale esplicito.
        if ($pageContext->getResourceType() !== 'product' || $pageContext->getResourceId() === null) {
            return [];
        }

        if (!$this->permissionChecker->canUpdateProducts($employeeContext->getProfileId())) {
            return [];
        }

        return [
            new AdminBarAction(
                'product_edit',
                // TODO <cnc> ===== Front admin bar ===== TRADUZIONE: inserire valore in inglese e usare translator
                'Modifica prodotto',
                [],
                '/product/' . $pageContext->getResourceId(),
            ),
        ];
    }
}
