<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

namespace PrestaShop\PrestaShop\Core\Form\IdentifiableObject\Handler;

use PrestaShop\PrestaShop\Core\ActivityLog\BackOfficeActivityLoggerInterface;
use PrestaShop\PrestaShop\Core\ExtraProperty\Form\ExtraPropertiesFormDataPersister;
use PrestaShop\PrestaShop\Core\Form\IdentifiableObject\DataHandler\FormDataHandlerInterface;
use PrestaShop\PrestaShop\Core\Hook\HookDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Creates new form handlers.
 */
final class FormHandlerFactory implements FormHandlerFactoryInterface
{
    /**
     * @var HookDispatcherInterface
     */
    private $hookDispatcher;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var bool
     */
    private $isDemoModeEnabled;

    /**
     * @var ExtraPropertiesFormDataPersister
     */
    private $extraPropertiesFormDataPersister;

    /**
     * @var BackOfficeActivityLoggerInterface
     */
    private $backOfficeActivityLogger;

    /**
     * @param HookDispatcherInterface $hookDispatcher
     * @param TranslatorInterface $translator
     * @param bool $isDemoModeEnabled
     * @param ExtraPropertiesFormDataPersister $extraPropertiesFormDataPersister
     */
    public function __construct(
        HookDispatcherInterface $hookDispatcher,
        TranslatorInterface $translator,
        $isDemoModeEnabled,
        ExtraPropertiesFormDataPersister $extraPropertiesFormDataPersister,
        BackOfficeActivityLoggerInterface $backOfficeActivityLogger
    ) {
        $this->hookDispatcher = $hookDispatcher;
        $this->translator = $translator;
        $this->isDemoModeEnabled = $isDemoModeEnabled;
        $this->extraPropertiesFormDataPersister = $extraPropertiesFormDataPersister;
        $this->backOfficeActivityLogger = $backOfficeActivityLogger;
    }

    /**
     * {@inheritdoc}
     */
    public function create(
        FormDataHandlerInterface $dataHandler,
        ?string $activityLogObjectType = null
    ) {
        return new FormHandler(
            $dataHandler,
            $this->hookDispatcher,
            $this->translator,
            $this->isDemoModeEnabled,
            $this->extraPropertiesFormDataPersister,
            $this->backOfficeActivityLogger,
            $activityLogObjectType
        );
    }
}
