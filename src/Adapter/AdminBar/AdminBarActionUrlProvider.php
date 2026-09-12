<?php

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\AdminBar;

use PrestaShop\PrestaShop\Adapter\Security\AdminPathProvider;

final class AdminBarActionUrlProvider
{
    /** @var array<string, array{endpoint: string, parameter: string}> */
    private const ACTION_ENDPOINTS = [
        'product_edit' => ['endpoint' => 'product', 'parameter' => 'product_id'],
        'category_edit' => ['endpoint' => 'category', 'parameter' => 'category_id'],
        'cms_edit' => ['endpoint' => 'cms', 'parameter' => 'cms_id'],
        'cms_category_edit' => ['endpoint' => 'cms-category', 'parameter' => 'cms_category_id'],
    ];

    public function __construct(private readonly AdminPathProvider $adminPathProvider)
    {
    }

    public function getUrl(AdminBarAction $action): ?string
    {
        $endpoint = $action->getEndpoint();
        if ($endpoint === null) {
            $actionEndpoint = self::ACTION_ENDPOINTS[$action->getName()] ?? null;
            if ($actionEndpoint === null) {
                return null;
            }

            $resourceId = $action->getParameters()[$actionEndpoint['parameter']] ?? null;
            if (!is_int($resourceId) || $resourceId <= 0) {
                return null;
            }

            $endpoint = '/_admin-bar/' . $actionEndpoint['endpoint'] . '/' . $resourceId;
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
