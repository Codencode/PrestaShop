<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShopBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PrestaShopBundle\Entity\Employee\Employee;
use PrestaShopBundle\Entity\Repository\EmployeeRepository;
use PrestaShopBundle\SchebTwoFactor\TwoFactorIntegrityCalculator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'prestashop:two-factor:sync',
    description: 'Synchronize employee two-factor flags and integrity hash.',
)]
final class SyncEmployeeTwoFactorCommand extends Command
{
    public function __construct(
        private readonly EmployeeRepository $employeeRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TwoFactorIntegrityCalculator $twoFactorIntegrityCalculator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('employee-id', InputArgument::REQUIRED, 'Employee ID')
            ->addOption('enabled', null, InputOption::VALUE_REQUIRED, 'Set two_factor_enabled to 0 or 1')
            ->addOption('totp-enabled', null, InputOption::VALUE_REQUIRED, 'Set two_factor_totp_enabled to 0 or 1')
            ->addOption('email-enabled', null, InputOption::VALUE_REQUIRED, 'Set two_factor_email_enabled to 0 or 1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $employeeId = $this->normalizeEmployeeId($input->getArgument('employee-id'));
            $enabled = $this->normalizeNullableBoolOption($input->getOption('enabled'), 'enabled');
            $totpEnabled = $this->normalizeNullableBoolOption($input->getOption('totp-enabled'), 'totp-enabled');
            $emailEnabled = $this->normalizeNullableBoolOption($input->getOption('email-enabled'), 'email-enabled');
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }

        /** @var Employee|null $employee */
        $employee = $this->employeeRepository->find($employeeId);
        if (!$employee instanceof Employee) {
            $io->error(sprintf('Employee with ID %d was not found.', $employeeId));

            return self::FAILURE;
        }

        if (null === $enabled && null === $totpEnabled && null === $emailEnabled) {
            $io->note('No two-factor flags provided: only integrity will be recalculated from current values.');
        }

        if (null !== $enabled) {
            $employee->setTwoFactorEnabled($enabled);
        }

        if (null !== $totpEnabled) {
            $employee->setTwoFactorTotEnabled($totpEnabled);
        }

        if (null !== $emailEnabled) {
            $employee->setTwoFactorEmailEnabled($emailEnabled);
        }

        $oldIntegrity = $employee->getTwoFactorIntegrity();
        $newIntegrity = $this->twoFactorIntegrityCalculator->calculate(
            $employee->getId(),
            $employee->getTwoFactorEnabled(),
            $employee->getTwoFactorTotEnabled(),
            $employee->getTwoFactorEmailEnabled(),
            $employee->getTwoFactorSecret()
        );

        $employee->setTwoFactorIntegrity($newIntegrity);

        $io->table(
            ['Field', 'Value'],
            [
                ['employee_id', (string) $employee->getId()],
                ['two_factor_enabled', $employee->getTwoFactorEnabled() ? '1' : '0'],
                ['two_factor_totp_enabled', $employee->getTwoFactorTotEnabled() ? '1' : '0'],
                ['two_factor_email_enabled', $employee->getTwoFactorEmailEnabled() ? '1' : '0'],
                ['old_two_factor_integrity', (string) $oldIntegrity],
                ['new_two_factor_integrity', $newIntegrity],
            ]
        );

        $this->entityManager->persist($employee);
        $this->entityManager->flush();

        $io->success(sprintf('Employee %d two-factor settings synchronized.', $employee->getId()));

        return self::SUCCESS;
    }

    private function normalizeEmployeeId(mixed $employeeId): int
    {
        if (!is_scalar($employeeId) || !ctype_digit((string) $employeeId) || (int) $employeeId <= 0) {
            throw new InvalidArgumentException(sprintf('Employee ID "%s" is invalid. It must be a positive integer.', (string) $employeeId));
        }

        return (int) $employeeId;
    }

    private function normalizeNullableBoolOption(mixed $value, string $optionName): ?bool
    {
        if (null === $value) {
            return null;
        }

        if (!is_scalar($value) || !in_array((string) $value, ['0', '1'], true)) {
            throw new InvalidArgumentException(sprintf('Option --%s must be 0 or 1.', $optionName));
        }

        return '1' === (string) $value;
    }
}
