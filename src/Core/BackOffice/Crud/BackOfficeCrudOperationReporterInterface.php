<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Core\BackOffice\Crud;

/**
 * Reports successful Back Office CRUD operations without exposing infrastructure details to Core.
 */
interface BackOfficeCrudOperationReporterInterface
{
    public function report(CrudOperation $operation): void;
}
