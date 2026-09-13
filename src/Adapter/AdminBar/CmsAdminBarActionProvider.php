<?php

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\AdminBar;

use PrestaShop\PrestaShop\Core\Security\AdminEmployeeContext;

final class CmsAdminBarActionProvider implements AdminBarActionProviderInterface
{
    public function __construct(private readonly AdminBarPermissionChecker $permissionChecker)
    {
    }

    public function getActions(AdminBarPageContext $pageContext, AdminEmployeeContext $employeeContext): iterable
    {
        // TODO <cnc> ===== Front admin bar ===== CmsAdminBarActionProvider::getActions() - DA VERIFICARE
        // Il renderer FO deve accettare solo azioni con un endpoint BO locale esplicito.
        if (!$this->permissionChecker->canUpdateCmsContent($employeeContext->getProfileId())) {
            return [];
        }

        $resourceId = $pageContext->getResourceId();
        if ($resourceId === null) {
            return [];
        }
        // La categoria CMS radice non ha azione di modifica neppure nel BO Core.
        if ($pageContext->getResourceType() === 'cms_category' && $resourceId === 1) {
            return [];
        }

        return match ($pageContext->getResourceType()) {
            'cms' => [
                new AdminBarAction(
                    'cms_edit',
                    // TODO <cnc> ===== Front admin bar ===== TRADUZIONE: inserire valore in inglese e usare translator
                    'Modifica pagina CMS',
                    [],
                    '/cms/' . $resourceId,
                ),
            ],
            'cms_category' => [
                new AdminBarAction(
                    'cms_category_edit',
                    // TODO <cnc> ===== Front admin bar ===== TRADUZIONE: inserire valore in inglese e usare translator
                    'Modifica categoria CMS',
                    [],
                    '/cms-category/' . $resourceId,
                ),
            ],
            default => [],
        };
    }
}
