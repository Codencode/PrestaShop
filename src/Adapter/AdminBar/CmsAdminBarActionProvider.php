<?php

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\AdminBar;

use PrestaShop\PrestaShop\Core\Security\AdminEmployeeContext;
use PrestaShopBundle\Translation\TranslatorInterface;

final class CmsAdminBarActionProvider implements AdminBarActionProviderInterface
{
    public function __construct(
        private readonly AdminBarPermissionChecker $permissionChecker,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getActions(
        AdminBarPageContext $pageContext,
        AdminEmployeeContext $employeeContext
    ): iterable {
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
                    $this->translator->trans('Edit CMS page'),
                    [],
                    '/cms/' . $resourceId,
                ),
            ],
            'cms_category' => [
                new AdminBarAction(
                    'cms_category_edit',
                    $this->translator->trans('Edit CMS category'),
                    [],
                    '/cms-category/' . $resourceId,
                ),
            ],
            default => [],
        };
    }
}
