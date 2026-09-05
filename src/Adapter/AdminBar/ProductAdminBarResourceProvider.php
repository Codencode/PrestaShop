<?php

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\AdminBar;

use ProductControllerCore;
use Throwable;

final class ProductAdminBarResourceProvider implements AdminBarResourceProviderInterface
{
    public function getResource(object $controller): ?AdminBarResource
    {
        if (!$controller instanceof ProductControllerCore) {
            return null;
        }

        try {
            $id = (int) $controller->getProduct()?->id;

            return $id > 0 ? new AdminBarResource('product', $id) : null;
        } catch (Throwable) {
            return null;
        }
    }
}