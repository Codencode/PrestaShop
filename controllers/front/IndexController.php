<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */
class IndexControllerCore extends FrontController
{
    /** @var string */
    public $php_self = 'index';

    /**
     * Assign template vars related to page content.
     *
     * @see FrontController::initContent()
     */
    public function initContent(): void
    {
        parent::initContent();
        $this->context->smarty->assign([
            'HOOK_HOME' => Hook::exec('displayHome'),
        ]);
        $this->setTemplate('index');

        //TODO <cnc-notice> ATTENZIONE: ho questo errore: The service "PrestaShopBundle\Entity\Repository\EmployeeRepository" has a dependency on a non-existent service "PrestaShop\PrestaShop\Core\Util\InternationalizedDomainNameConverter".
        //$adminEmployeeContextProvider = $this->get(PrestaShop\PrestaShop\Adapter\Security\AdminEmployeeContextProvider\AdminEmployeeContextProvider);
        $f = 0;
        ////////////////////////////

    }

    /**
     * {@inheritdoc}
     */
    public function getCanonicalURL(): string
    {
        return $this->context->link->getPageLink('index');
    }
}
