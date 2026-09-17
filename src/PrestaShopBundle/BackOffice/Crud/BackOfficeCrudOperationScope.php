<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShopBundle\BackOffice\Crud;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Determines whether CRUD activity reporting is enabled for the current Back Office request.
 */
final class BackOfficeCrudOperationScope
{
    public const REQUEST_ATTRIBUTE = '_back_office_crud_reporting';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function isEnabled(): bool
    {// TODO <cnc> ########## BACK OFFICE CRUD LOGGING ########## - BackOfficeCrudOperationScope::isEnabled() - DA VERIFICARE
        return true === $this->requestStack
            ->getMainRequest()
            ?->attributes
            ->get(self::REQUEST_ATTRIBUTE);
    }
}
