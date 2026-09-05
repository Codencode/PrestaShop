<?php
declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\AdminBar;

use PrestaShop\PrestaShop\Core\Security\AdminEmployeeContext;

interface AdminBarActionProviderInterface
{
    // TODO <cnc> ===== Front admin bar ===== AdminBarActionProviderInterface::getActions() - DA VERIFICARE
    // Definire il contratto pubblico per i provider registrati dai moduli.
    /** @return iterable<AdminBarAction> */
    public function getActions(AdminBarPageContext $pageContext, AdminEmployeeContext $employeeContext): iterable;
}
