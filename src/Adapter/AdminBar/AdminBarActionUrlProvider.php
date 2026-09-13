<?php

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\AdminBar;

use PrestaShop\PrestaShop\Adapter\Security\AdminPathProvider;

/**
 * Builds local Back Office URLs for Admin Bar actions.
 *
 * Explicit endpoints are restricted to the /_admin-bar path to prevent extensions from
 * generating arbitrary or external URLs from the Front Office.
 */
final class AdminBarActionUrlProvider
{
    public function __construct(private readonly AdminPathProvider $adminPathProvider)
    {
    }

    public function getUrl(AdminBarAction $action): ?string
    {
        $endpoint = $action->getEndpoint();
        if ($endpoint === null) {
            return null;
        }

        if (preg_match('#\A/_admin-bar(?:/[A-Za-z0-9][A-Za-z0-9_-]*)+\z#', $endpoint) !== 1) {
            return null;
        }

        $adminPath = $this->adminPathProvider->getPath();
        if ($adminPath === null) {
            return null;
        }

        return rtrim($adminPath, '/') . $endpoint;
    }
}
