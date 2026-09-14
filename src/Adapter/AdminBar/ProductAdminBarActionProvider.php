<?php

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\AdminBar;

use PrestaShop\PrestaShop\Core\Security\AdminEmployeeContext;
use PrestaShopBundle\Translation\TranslatorInterface;

final class ProductAdminBarActionProvider implements AdminBarActionProviderInterface
{
    public function __construct(
        private readonly AdminBarPermissionChecker $permissionChecker,
        private readonly TranslatorInterface $translator,
    ) {
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
                $this->translator->trans('Edit product'),
                [],
                '/product/' . $pageContext->getResourceId(),
            ),
        ];
    }
}
