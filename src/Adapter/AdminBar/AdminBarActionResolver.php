<?php
declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\AdminBar;

use PrestaShop\PrestaShop\Adapter\HookManager;
use PrestaShop\PrestaShop\Core\Security\AdminEmployeeContext;

final class AdminBarActionResolver
{
    private const MODULE_ACTIONS_HOOK = 'actionAdminBarGetActions';

    /** @param iterable<AdminBarActionProviderInterface> $providers */
    public function __construct(
        private readonly iterable $providers,
        private readonly HookManager $hookManager,
    )
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

        $moduleResults = $this->hookManager->exec(
            self::MODULE_ACTIONS_HOOK,
            [
                'pageContext' => $pageContext,
                'employeeContext' => $employeeContext,
            ],
            null,
            true,
        );

        if (!is_array($moduleResults)) {
            return $actions;
        }

        foreach ($moduleResults as $moduleActions) {
            if (!is_iterable($moduleActions)) {
                continue;
            }

            foreach ($moduleActions as $action) {
                if ($action instanceof AdminBarAction && $action->getEndpoint() !== null) {
                    $actions[] = $action;
                }
            }
        }

        return $actions;
    }
}
