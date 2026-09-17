<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShopBundle\BackOffice\Crud;

use PrestaShop\PrestaShop\Core\BackOffice\Crud\BackOfficeCrudOperationReporterInterface;
use PrestaShop\PrestaShop\Core\BackOffice\Crud\CrudOperation;
use PrestaShop\PrestaShop\Core\BackOffice\Crud\CrudOperationType;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Reports successful CRUD operations only when executed from a Back Office request.
 */
final class SymfonyBackOfficeCrudOperationReporter implements BackOfficeCrudOperationReporterInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly BackOfficeCrudOperationScope $scope,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function report(CrudOperation $operation): void
    {// TODO <cnc> ########## BACK OFFICE CRUD LOGGING ########## - SymfonyBackOfficeCrudOperationReporter::report() - DA VERIFICARE
        if (!$this->scope->isEnabled()) {
            return;
        }

        $message = $this->getMessage($operation);
        if (null === $message) {
            return;
        }

        try {
            $this->logger->info(
                $message,
                [
                    'object_type' => $operation->getObjectType(),
                    'object_id' => $operation->getLogObjectId(),
                    'allow_duplicate' => true,
                ]
            );
        } catch (Throwable) {
            // Activity logging must never break a successful CRUD operation.
        }
    }

    private function getMessage(CrudOperation $operation): ?string
    {// TODO <cnc> ########## BACK OFFICE CRUD LOGGING ########## - SymfonyBackOfficeCrudOperationReporter::getMessage() - DA VERIFICARE
        $message = match ($operation->getOperation()) {
            CrudOperationType::CREATE => '%s addition',
            CrudOperationType::UPDATE => '%s modification',
            CrudOperationType::DELETE => '%s deletion',
            CrudOperationType::DUPLICATE => null,
        };

        if (null === $message) {
            return null;
        }

        return sprintf(
            $this->translator->trans(
                $message,
                [],
                'Admin.Advparameters.Feature'
            ),
            $operation->getObjectType()
        );
    }
}
