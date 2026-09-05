<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\Security;

use BadMethodCallException;
use Doctrine\ORM\NonUniqueResultException;
use Error;
use ErrorException;
use PrestaShop\PrestaShop\Core\ConfigurationInterface;
use PrestaShop\PrestaShop\Core\Http\CookieOptions;
use PrestaShop\PrestaShop\Core\Security\AdminEmployeeContext;
use PrestaShop\PrestaShop\Core\Security\AdminSessionReaderInterface;
use PrestaShopBundle\Entity\Employee\Employee;
use PrestaShopBundle\Entity\Employee\EmployeeSession;
use PrestaShopBundle\Security\Admin\EmployeeProvider;
use PrestaShopBundle\Security\Admin\TokenAttributes;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

final class AdminEmployeeContextProvider
{
    public function __construct(
        private readonly AdminSessionReaderInterface $sessionReader,
        private readonly EmployeeProvider $employeeProvider,
        private readonly ConfigurationInterface $configuration,
    ) {
    }

    public function getContext(Request $request): ?AdminEmployeeContext
    {
        $session = $this->sessionReader->read($request);
        // Matches the "main" firewall in app/config/admin/security.yml.
        $serializedToken = $session['_sf2_attributes']['_security_main'] ?? null;
        if (!is_string($serializedToken) || $serializedToken === '' || strlen($serializedToken) > 1048576) {
            return null;
        }

        $token = $this->readToken($serializedToken);
        if ($token === null || !$token->hasAttribute(TokenAttributes::LAST_ADMIN_ACTIVITY)) {
            return null;
        }

        $lastActivity = $token->getAttribute(TokenAttributes::LAST_ADMIN_ACTIVITY);
        $now = time();
        $hours = (int) $this->configuration->get('PS_COOKIE_LIFETIME_BO');
        $cookieLifetime = ($hours > 0 ? min($hours, CookieOptions::MAX_COOKIE_VALUE) : CookieOptions::MAX_COOKIE_VALUE) * 3600;
        $storageLifetime = (int) ini_get('session.gc_maxlifetime');
        // Conservative FO expiry: do not rely on probabilistic PHP garbage collection.
        $lifetime = $storageLifetime > 0 ? min($cookieLifetime, $storageLifetime) : $cookieLifetime;
        if (!is_int($lastActivity) || $lastActivity <= 0 || $lastActivity > $now || $now - $lastActivity >= $lifetime) {
            return null;
        }

        $employee = $token->getUser();
        if (!$employee instanceof Employee || $employee->getId() <= 0 || !$token->hasAttribute(TokenAttributes::EMPLOYEE_SESSION)) {
            return null;
        }
        $employeeSession = $token->getAttribute(TokenAttributes::EMPLOYEE_SESSION);
        if (!$employeeSession instanceof EmployeeSession || $employeeSession->getId() <= 0 || !$employeeSession->getToken()) {
            return null;
        }

        if ((bool) $this->configuration->get('PS_COOKIE_CHECKIP')) {
            if (!$token->hasAttribute(TokenAttributes::IP_ADDRESS)
                || !is_string($token->getAttribute(TokenAttributes::IP_ADDRESS))
                || $token->getAttribute(TokenAttributes::IP_ADDRESS) !== $request->getClientIp()) {
                return null;
            }
        }

        try {
            $freshEmployee = $this->employeeProvider->refreshUser($employee);
        } catch (UserNotFoundException | NonUniqueResultException) {
            return null;
        }

        // Same identity comparisons as Symfony's refresh, plus the BO session check.
        if (!$freshEmployee instanceof Employee
            || !$freshEmployee->isActive()
            || $freshEmployee->getId() !== $employee->getId()
            || !$employee->isEqualTo($freshEmployee)
            || !$freshEmployee->hasSession($employeeSession->getId(), $employeeSession->getToken())) {
            return null;
        }

        return new AdminEmployeeContext($freshEmployee->getId(), $freshEmployee->getProfile()->getId());
    }

    private function readToken(string $serializedToken): ?TokenInterface
    {
        // Only deserialize the server-side security token, never an HTTP cookie.
        // Unknown/custom token types fail closed; unrelated attributes need no classes.
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            $token = unserialize($serializedToken, [
                'allowed_classes' => [Employee::class, EmployeeSession::class, PostAuthenticationToken::class, RememberMeToken::class, UsernamePasswordToken::class],
                'max_depth' => 64,
            ]);
            if ((!$token instanceof PostAuthenticationToken && !$token instanceof RememberMeToken && !$token instanceof UsernamePasswordToken)
                || $token->getFirewallName() !== 'main') {
                return null;
            }

            return $token;
        } catch (ErrorException | Error | BadMethodCallException) {
            return null;
        } finally {
            restore_error_handler();
        }
    }
}
