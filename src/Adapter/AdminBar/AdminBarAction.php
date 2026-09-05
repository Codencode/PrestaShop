<?php
declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\AdminBar;

final class AdminBarAction
{
    /** @param array<string, int> $parameters */
    public function __construct(private readonly string $name, private readonly string $label, private readonly array $parameters = []) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /** @return array<string, int> */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
