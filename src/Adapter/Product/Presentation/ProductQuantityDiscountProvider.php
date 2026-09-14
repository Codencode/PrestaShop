<?php

/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\Product\Presentation;

use Address;
use Combination;
use Configuration;
use Context;
use Customer;
use Group;
use PrestaShop\PrestaShop\Core\FeatureFlag\FeatureFlagSettings;
use PrestaShop\PrestaShop\Core\FeatureFlag\FeatureFlagStateCheckerInterface;
use PrestaShop\PrestaShop\Core\Pricing\Product\Calculator\ProductCalculatorInterface;
use PrestaShop\PrestaShop\Core\Pricing\Product\ProductPrice;
use Product;
use SpecificPrice;
use SpecificPriceFormatter;
use Throwable;
use Tools;

final class ProductQuantityDiscountProvider
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $quantityDiscountsCache = [];

    public function __construct(
        private readonly FeatureFlagStateCheckerInterface $featureFlagStateChecker,
        private readonly ProductCalculatorInterface $productCalculator,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getQuantityDiscounts(Product $product, Context $context, ?int $idProductAttribute): array
    {
        $idCustomer = isset($context->customer) ? (int) $context->customer->id : 0;
        $idCountry = $idCustomer ? (int) Customer::getCurrentCountry($idCustomer) : (int) Tools::getCountry();
        $idGroup = (int) Group::getCurrent()->id;
        $taxRate = $this->getTaxRate($product, $context);
        $cacheKey = $this->getCacheKey($product, $context, $idProductAttribute, $idCountry, $idGroup);

        if (isset($this->quantityDiscountsCache[$cacheKey])) {
            return $this->quantityDiscountsCache[$cacheKey];
        }

        $specificPrices = SpecificPrice::getQuantityDiscounts(
            (int) $product->id,
            (int) $context->shop->id,
            (int) $context->currency->id,
            $idCountry,
            $idGroup,
            $idProductAttribute,
            false,
            $idCustomer
        );

        foreach ($specificPrices as &$specificPrice) {
            if ($specificPrice['id_product_attribute']) {
                $combination = new Combination((int) $specificPrice['id_product_attribute']);
                $attributes = $combination->getAttributesName((int) $context->language->id);
                foreach ($attributes as $attribute) {
                    $specificPrice['attributes'] = $attribute['name'] . ' - ';
                }
                $specificPrice['attributes'] = rtrim($specificPrice['attributes'], ' - ');
            }
            if ((int) $specificPrice['id_currency'] === 0 && $specificPrice['reduction_type'] === 'amount') {
                $specificPrice['reduction'] = Tools::convertPriceFull($specificPrice['reduction'], null, Context::getContext()->currency);
            }
        }
        unset($specificPrice);

        $quantityDiscounts = $this->formatQuantityDiscounts(
            $specificPrices,
            $this->getProductPrice($product, $idProductAttribute),
            $taxRate,
            (float) $product->ecotax,
            $context
        );

        return $this->quantityDiscountsCache[$cacheKey] = $quantityDiscounts;
    }

    public function getTaxRate(Product $product, Context $context): float
    {
        return (float) $product->getTaxesRate(new Address((int) $context->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')}));
    }

    /**
     * @param array<int, array<string, mixed>> $specificPrices
     *
     * @return array<int, array<string, mixed>>
     */
    private function formatQuantityDiscounts(array $specificPrices, float $price, float $taxRate, float $ecotaxAmount, Context $context): array
    {
        $isTaxIncluded = (int) Group::getPriceDisplayMethod(Group::getCurrent()->id) === PS_TAX_INC;

        foreach ($specificPrices as $key => &$specificPrice) {
            $specificPriceFormatter = new SpecificPriceFormatter(
                $specificPrice,
                $isTaxIncluded,
                $context->currency,
                Configuration::get('PS_DISPLAY_DISCOUNT_PRICE')
            );
            $specificPrice = $specificPriceFormatter->formatSpecificPrice($price, $taxRate, $ecotaxAmount);
            $specificPrice['nextQuantity'] = isset($specificPrices[$key + 1]) ? (int) $specificPrices[$key + 1]['from_quantity'] : -1;
        }

        return $specificPrices;
    }

    private function getProductPrice(Product $product, ?int $idProductAttribute): float
    {
        if ($this->isNewPricingEnabled()) {
            $productPrice = ProductPrice::create((int) $product->id, (int) $idProductAttribute);
            $this->productCalculator->compute($productPrice);

            return (float) (string) $productPrice->getFinalPrice()->getTaxExcluded();
        }

        return (float) $product->getPrice(Product::$_taxCalculationMethod === PS_TAX_INC, $idProductAttribute, 6, null, false, false);
    }

    private function isNewPricingEnabled(): bool
    {
        try {
            return $this->featureFlagStateChecker->isEnabled(FeatureFlagSettings::FEATURE_FLAG_NEW_PRICING);
        } catch (Throwable) {
            return false;
        }
    }

    private function getCacheKey(Product $product, Context $context, ?int $idProductAttribute, int $idCountry, int $idGroup): string
    {
        return implode(':', [
            $product->id,
            $idProductAttribute,
            $context->shop->id,
            $context->currency->id,
            $context->language->id,
            $context->customer->id,
            $context->cart->id,
            $context->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')},
            $idCountry,
            $idGroup,
        ]);
    }
}
