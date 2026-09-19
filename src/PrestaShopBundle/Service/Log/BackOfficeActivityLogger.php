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
        return match ($activity->getType()) {
            BackOfficeActivityType::CREATE => $this->formatMessage(
                '%s addition',
                $activity->getObjectType()
            ),
            BackOfficeActivityType::UPDATE => $this->formatMessage(
                '%s modification',
                $activity->getObjectType()
            ),
            BackOfficeActivityType::DELETE => $this->formatMessage(
                '%s deletion',
                $activity->getObjectType()
            ),
            BackOfficeActivityType::ACTIVATE => $this->getStatusMessage($activity, true),
            BackOfficeActivityType::DEACTIVATE => $this->getStatusMessage($activity, false),
            BackOfficeActivityType::DUPLICATE => $this->getDuplicateMessage($activity),
        };
    }

    private function getStatusMessage(BackOfficeActivity $activity, bool $activated): ?string
    {
        if (null === $activity->getObjectId()) {
            return null;
        }

        return $this->formatMessage(
            $activated ? '%s activated: %d' : '%s deactivated: %d',
            $activity->getObjectType(),
            $activity->getObjectId()
        );
    }

    private function getDuplicateMessage(BackOfficeActivity $activity): ?string
    {
        if (null === $activity->getObjectId() || null === $activity->getNewObjectId()) {
            return null;
        }

        return $this->formatMessage(
            '%s duplicated: (from %d to %d).',
            $activity->getObjectType(),
            $activity->getObjectId(),
            $activity->getNewObjectId()
        );
    }

    private function formatMessage(string $message, mixed ...$values): string
    {
        return sprintf(
            $this->translator->trans(
                $message,
                [],
                'Admin.Advparameters.Feature'
            ),
            ...$values
        );
    }
}
