<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Core\Security;

use Symfony\Component\HttpFoundation\Request;

interface AdminSessionReaderInterface
{
    /**
     * Read an existing server-side PHP session without saving or renewing it.
     * Never use client-provided serialized data as the result of this method.
     *
     * @return array<string, mixed>|null
     */
    public function read(Request $request): ?array;
}
