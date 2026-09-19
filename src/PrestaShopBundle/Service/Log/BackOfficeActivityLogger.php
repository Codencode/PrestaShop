<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShopBundle\Service\Log;

use PrestaShop\PrestaShop\Core\ActivityLog\BackOfficeActivity;
use PrestaShop\PrestaShop\Core\ActivityLog\BackOfficeActivityLoggerInterface;
use PrestaShop\PrestaShop\Core\ActivityLog\BackOfficeActivityType;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Logs successful Back Office activities through the standard logging infrastructure.
 */
final class BackOfficeActivityLogger implements BackOfficeActivityLoggerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly BackOfficeActivityScope $scope,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function log(BackOfficeActivity $activity): void
    {// TODO <cnc> ########## BACK OFFICE ACTIVITY LOGGING ########## - BackOfficeActivityLogger::log() - DA VERIFICARE
        try {
            if (!$this->scope->isEnabled()) {
                return;
            }

            $message = $this->getMessage($activity);
            if (null === $message) {
                return;
            }

            $this->logger->info(
                $message,
                [
                    'object_type' => $activity->getObjectType(),
                    'object_id' => $activity->getLogObjectId(),
                    'allow_duplicate' => true,
                ]
            );
        } catch (Throwable) {
            // Activity logging must never break a successful Back Office operation.
        }
    }

    private function getMessage(BackOfficeActivity $activity): ?string
    {// TODO <cnc> ########## BACK OFFICE ACTIVITY LOGGING ########## - BackOfficeActivityLogger::getMessage() - DA VERIFICARE
        if (BackOfficeActivityType::DUPLICATE === $activity->getType()) {
            if (null === $activity->getObjectId() || null === $activity->getNewObjectId()) {
                return null;
            }

            return sprintf(
                $this->translator->trans(
                    '%s duplicated: (from %d to %d).',
                    [],
                    'Admin.Advparameters.Feature'
                ),
                $activity->getObjectType(),
                $activity->getObjectId(),
                $activity->getNewObjectId()
            );
        }

        $message = match ($activity->getType()) {
            BackOfficeActivityType::CREATE => '%s addition',
            BackOfficeActivityType::UPDATE => '%s modification',
            BackOfficeActivityType::DELETE => '%s deletion',
            default => null,
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
            $activity->getObjectType()
        );
    }
}
