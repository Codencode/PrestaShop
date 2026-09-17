<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShopBundle\EventSubscriber;

use PrestaShopBundle\BackOffice\Crud\BackOfficeCrudOperationScope;
use PrestaShopBundle\Controller\Admin\PrestaShopAdminController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Marks Back Office requests so CRUD activity reporting can be enabled only for BO operations.
 */
class BackOfficeCrudOperationScopeSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {// TODO <cnc> ########## BACK OFFICE CRUD LOGGING ########## - BackOfficeCrudOperationScopeSubscriber::onKernelController() - DA VERIFICARE
        if (!$event->isMainRequest()) {
            return;
        }

        $controller = $event->getController();
        $controllerInstance = is_array($controller) ? $controller[0] : $controller;

        if (!$controllerInstance instanceof PrestaShopAdminController) {
            return;
        }

        $event->getRequest()->attributes->set(
            BackOfficeCrudOperationScope::REQUEST_ATTRIBUTE,
            true
        );
    }
}
