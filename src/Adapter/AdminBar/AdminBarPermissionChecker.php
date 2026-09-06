<?php

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\AdminBar;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final class AdminBarPermissionChecker
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $databasePrefix,
    ) {
    }

    public function canUpdateProducts(int $profileId): bool
    {
        return $this->canUpdate($profileId, 'ADMINPRODUCTS');
    }

    public function canUpdateCategories(int $profileId): bool
    {
        return $this->canUpdate($profileId, 'ADMINCATEGORIES');
    }

    private function canUpdate(int $profileId, string $legacyController): bool
    {
        // TODO <cnc> ===== Front admin bar ===== AdminBarPermissionChecker::canUpdate() - DA VERIFICARE
        // Estendere solo quando un'azione Core concreta richiede un nuovo permesso.
        if ($profileId === _PS_ADMIN_PROFILE_) {
            return true;
        }

        try {
            return false !== $this->connection->createQueryBuilder()
                ->select('1')
                ->from($this->databasePrefix . 'access', 'a')
                ->innerJoin('a', $this->databasePrefix . 'authorization_role', 'ar', 'ar.id_authorization_role = a.id_authorization_role')
                ->where('a.id_profile = :profileId')
                ->andWhere('ar.slug LIKE :role')
                ->setParameter('profileId', $profileId)
                ->setParameter('role', 'ROLE_MOD_%_' . $legacyController . '_UPDATE')
                ->setMaxResults(1)
                ->executeQuery()
                ->fetchOne();
        } catch (Exception) {
            return false;
        }
    }
}