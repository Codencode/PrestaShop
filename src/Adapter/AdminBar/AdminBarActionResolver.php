<?php
declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\AdminBar;

use PrestaShop\PrestaShop\Core\Security\AdminEmployeeContext;

final class AdminBarActionResolver
{
    /** @param iterable<AdminBarActionProviderInterface> $providers */
    public function __construct(private readonly iterable $providers)
    {
    }

    /** @return list<AdminBarAction> */
    public function getActions(AdminBarPageContext $pageContext, AdminEmployeeContext $employeeContext): array
    {
        // TODO <cnc> ===== Front admin bar ===== AdminBarActionResolver::getActions() - DA VERIFICARE
        // Aggiungere solo provider le cui azioni abbiano un endpoint BO esplicitamente mappato.
        $actions = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->getActions($pageContext, $employeeContext) as $action) {
                $actions[] = $action;
            }
        }
        return $actions;
    }
}
