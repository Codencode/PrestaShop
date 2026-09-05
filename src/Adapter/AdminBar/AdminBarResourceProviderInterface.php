<?php

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\AdminBar;

interface AdminBarResourceProviderInterface
{
    public function getResource(object $controller): ?AdminBarResource;
}