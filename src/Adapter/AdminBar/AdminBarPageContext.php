<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\AdminBar;

final class AdminBarPageContext
{
    public function __construct(
        private readonly object $controller,
        private readonly string $pageName,
        private readonly ?int $resourceId = null,
        private readonly ?string $resourceType = null,
    ) {
    }

    public function getController(): object
    {
        return $this->controller;
    }

    public function getPageName(): string
    {
        return $this->pageName;
    }

    public function getResourceId(): ?int
    {
        return $this->resourceId;
    }

    public function getResourceType(): ?string
    {
        return $this->resourceType;
    }
}
