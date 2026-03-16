<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

namespace PrestaShop\PrestaShop\Adapter\Profile\Employee\CommandHandler;

use Employee;
use PrestaShop\PrestaShop\Core\CommandBus\Attributes\AsCommandHandler;
use PrestaShop\PrestaShop\Core\Crypto\Hashing;
use PrestaShop\PrestaShop\Core\Domain\Employee\Command\AddEmployeeCommand;
use PrestaShop\PrestaShop\Core\Domain\Employee\CommandHandler\AddEmployeeHandlerInterface;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\EmailAlreadyUsedException;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\EmployeeException;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\InvalidProfileException;
use PrestaShop\PrestaShop\Core\Domain\Employee\ValueObject\EmployeeId;
use PrestaShop\PrestaShop\Core\Employee\Access\ProfileAccessCheckerInterface;
use PrestaShop\PrestaShop\Core\Employee\ContextEmployeeProviderInterface;
use PrestaShopBundle\SchebTwoFactor\TwoFactorIntegrityCalculator;

/**
 * Handles command which adds new employee using legacy object model
 *
 * @internal
 */
#[AsCommandHandler]
final class AddEmployeeHandler extends AbstractEmployeeHandler implements AddEmployeeHandlerInterface
{
    /**
     * @var Hashing
     */
    private $hashing;

    /**
     * @var ProfileAccessCheckerInterface
     */
    private $profileAccessChecker;

    /**
     * @var ContextEmployeeProviderInterface
     */
    private $contextEmployeeProvider;

    /**
     * @var TwoFactorIntegrityCalculator
     */
    private $twoFactorIntegrityCalculator;

    /**
     * @param Hashing $hashing
     * @param ProfileAccessCheckerInterface $profileAccessChecker
     * @param ContextEmployeeProviderInterface $contextEmployeeProvider
     * @param TwoFactorIntegrityCalculator $twoFactorIntegrityCalculator
     */
    public function __construct(
        Hashing $hashing,
        ProfileAccessCheckerInterface $profileAccessChecker,
        ContextEmployeeProviderInterface $contextEmployeeProvider,
        TwoFactorIntegrityCalculator $twoFactorIntegrityCalculator
    ) {
        $this->hashing = $hashing;
        $this->profileAccessChecker = $profileAccessChecker;
        $this->contextEmployeeProvider = $contextEmployeeProvider;
        $this->twoFactorIntegrityCalculator = $twoFactorIntegrityCalculator;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(AddEmployeeCommand $command)
    {
        $canAccessProfile = $this->profileAccessChecker->canEmployeeAccessProfile(
            $this->contextEmployeeProvider->getId(),
            (int) $command->getProfileId()
        );

        if (!$canAccessProfile) {
            throw new InvalidProfileException('You cannot access the provided profile.');
        }

        $this->assertEmailIsNotAlreadyUsed($command->getEmail()->getValue());
        $this->assertHomepageIsAccessible($command->getDefaultPageId(), $command->getProfileId());

        $employee = $this->createLegacyEmployeeObjectFromCommand($command);

        $this->associateWithShops($employee, $command->getShopAssociation());

        return new EmployeeId((int) $employee->id);
    }

    /**
     * Create legacy employee object.
     *
     * @param AddEmployeeCommand $command
     *
     * @return Employee
     */
    private function createLegacyEmployeeObjectFromCommand(AddEmployeeCommand $command)
    {
        $employee = new Employee();
        $employee->firstname = $command->getFirstName()->getValue();
        $employee->lastname = $command->getLastName()->getValue();
        $employee->email = $command->getEmail()->getValue();
        $employee->id_lang = $command->getLanguageId();
        $employee->id_profile = $command->getProfileId();
        $employee->default_tab = $command->getDefaultPageId();
        $employee->active = $command->isActive();
        $employee->passwd = $this->hashing->hash($command->getPlainPassword()->getValue());
        $employee->id_last_order = $employee->getLastElementsForNotify('order');
        $employee->id_last_customer_message = $employee->getLastElementsForNotify('customer_message');
        $employee->id_last_customer = $employee->getLastElementsForNotify('customer');
        $employee->has_enabled_gravatar = $command->hasEnabledGravatar();

        if (false === $employee->add()) {
            throw new EmployeeException(sprintf('Failed to add new employee with email "%s"', $command->getEmail()->getValue()));
        }

        $employee->two_factor_integrity = $this->twoFactorIntegrityCalculator->calculate(
            (int) $employee->id,
            (bool) $employee->two_factor_enabled,
            (bool) $employee->two_factor_totp_enabled,
            (bool) $employee->two_factor_email_enabled,
            $employee->two_factor_totp_secret
        );

        if (false === $employee->update()) {
            throw new EmployeeException(sprintf('Failed to initialize two-factor integrity for employee with email "%s"', $command->getEmail()->getValue()));
        }

        return $employee;
    }

    /**
     * @param string $email
     *
     * @throws EmailAlreadyUsedException
     */
    private function assertEmailIsNotAlreadyUsed($email)
    {
        if (Employee::employeeExists($email)) {
            throw new EmailAlreadyUsedException($email, 'An account already exists for this email address');
        }
    }
}
