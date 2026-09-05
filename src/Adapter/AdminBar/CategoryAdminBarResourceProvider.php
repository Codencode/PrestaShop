<?php

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\AdminBar;

use CategoryControllerCore;
use Throwable;

final class CategoryAdminBarResourceProvider implements AdminBarResourceProviderInterface
{
    public function getResource(object $controller): ?AdminBarResource
    {
        if (!$controller instanceof CategoryControllerCore) {
            return null;
        }

        try {
            $id = (int) $controller->getCategory()->id;

            return $id > 0 ? new AdminBarResource('category', $id) : null;
        } catch (Throwable) {
            return null;
        }
    }
}