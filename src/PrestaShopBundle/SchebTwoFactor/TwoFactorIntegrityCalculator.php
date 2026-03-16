<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

namespace PrestaShopBundle\SchebTwoFactor;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class TwoFactorIntegrityCalculator
{
    public function __construct(
        #[Autowire('%new_cookie_key%')]
        private ?string $newCookieKey = null
    ) {
    }

    public function calculate(
        int $employeeId,
        bool $twoFactorEnabled,
        bool $twoFactorTotpEnabled,
        bool $twoFactorEmailEnabled,
        ?string $twoFactorTotpSecret
    ): string {
        $payload = implode('|', [
            'employee_id=' . $employeeId,
            'two_factor_enabled=' . (int) $twoFactorEnabled,
            'two_factor_totp_enabled=' . (int) $twoFactorTotpEnabled,
            'two_factor_email_enabled=' . (int) $twoFactorEmailEnabled,
            'two_factor_totp_secret=' . ($twoFactorTotpSecret ?? ''),
        ]);

        return hash_hmac('sha256', $payload, (string) $this->newCookieKey);
    }
}
