<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShopBundle\Controller\Admin;

use PrestaShop\PrestaShop\Core\FeatureFlag\FeatureFlagSettings;
use PrestaShopBundle\Security\Attribute\AdminSecurity;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class AdminBarController extends PrestaShopAdminController
{
    #[AdminSecurity("is_granted('update', 'AdminProducts')", redirectRoute: 'admin_product_catalog')]
    public function editProductAction(int $productId): RedirectResponse
    {
        // TODO <cnc> ===== Front admin bar ===== AdminBarController::editProductAction() - DA VERIFICARE
        // Aggiungere qui solo azioni esplicitamente mappate e protette dal relativo permesso BO.
        if (!$this->getFeatureFlagStateChecker()->isEnabled(FeatureFlagSettings::FEATURE_FLAG_FRONT_OFFICE_ADMIN_BAR)) {
            throw $this->createNotFoundException();
        }

        return $this->redirectToRoute('admin_product_form', ['id' => $productId]);
    }
}
