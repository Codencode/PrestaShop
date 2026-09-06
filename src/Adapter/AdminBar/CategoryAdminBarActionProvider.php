<?php

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\AdminBar;

use PrestaShop\PrestaShop\Core\Security\AdminEmployeeContext;

final class CategoryAdminBarActionProvider implements AdminBarActionProviderInterface
{
    public function __construct(private readonly AdminBarPermissionChecker $permissionChecker)
    {
    }

    public function getActions(AdminBarPageContext $pageContext, AdminEmployeeContext $employeeContext): iterable
    {
        // TODO <cnc> ===== Front admin bar ===== CategoryAdminBarActionProvider::getActions() - DA VERIFICARE
        // Il renderer FO deve accettare solo azioni con un endpoint BO esplicitamente mappato.
        if ($pageContext->getResourceType() !== 'category' || $pageContext->getResourceId() === null) {
            return [];
        }
        if (!$this->permissionChecker->canUpdateCategories($employeeContext->getProfileId())) {
            return [];
        }

        return [new AdminBarAction('category_edit', 'Modifica categoria', ['category_id' => $pageContext->getResourceId()])];
    }
}
