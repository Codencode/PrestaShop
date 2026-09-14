<?php

/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\Product\Presentation;

use Configuration;
use Context;
use Group;
use GroupReduction;
use Hook;
use PrestaShop\PrestaShop\Adapter\Image\ImageRetriever;
use PrestaShop\PrestaShop\Adapter\Presenter\Object\ObjectPresenter;
use Product;
use ProductPresenterFactory;
use Tax;
use TaxConfiguration;
use Tools;
use PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductLazyArray;
use PrestaShop\PrestaShop\Core\Product\ProductExtraContentFinder;

final class ProductPageProductProvider
{
    public function __construct(private readonly ProductQuantityDiscountProvider $quantityDiscountProvider)
    {
    }

    public function getProduct(Product $product, Context $context, ?int $idProductAttribute, int $quantityWanted = 1): ProductLazyArray
    {
        $productForPresentation = (new ObjectPresenter())->present($product);
        $productForPresentation['description'] = $this->transformDescriptionWithImages($product->description, $product, $context);
        $productForPresentation['out_of_stock'] = (int) $product->out_of_stock;
        $productForPresentation['id_product_attribute'] = $idProductAttribute;
        $productForPresentation['minimal_quantity'] = $this->getMinimalQuantity($product, $context, $idProductAttribute);
        $productForPresentation['cart_quantity'] = $context->cart->getProductQuantity((int) $product->id, $idProductAttribute)['quantity'];
        $productForPresentation['quantity_required'] = $this->getRequiredQuantity($productForPresentation);
        $productForPresentation['quantity_wanted'] = max($quantityWanted, $productForPresentation['quantity_required']);
        $productForPresentation['extraContent'] = (new ProductExtraContentFinder())->addParams(['product' => $product])->present();
        $productForPresentation['ecotax_tax_inc'] = $product->getEcotax(null, true, true);
        $productForPresentation['ecotax'] = Tools::convertPrice($this->getEcotax($productForPresentation, $product, $context), $context->currency, true, $context);

        $productFull = Product::getProductProperties($context->language->id, $productForPresentation, $context);
        $productFull = $this->addCustomizationData($productFull, $product, $context);
        $productFull['show_quantities'] = (bool) (
            Configuration::get('PS_DISPLAY_QTIES')
            && Configuration::get('PS_STOCK_MANAGEMENT')
            && $productFull['quantity'] > 0
            && $product->available_for_order
            && !Configuration::isCatalogMode()
        );
        $productFull['quantity_label'] = $productFull['quantity'] > 1 ? $context->getTranslator()->trans('Items', [], 'Shop.Theme.Catalog') : $context->getTranslator()->trans('Item', [], 'Shop.Theme.Catalog');
        $productFull['quantity_discounts'] = $this->quantityDiscountProvider->getQuantityDiscounts($product, $context, $idProductAttribute);
        $groupReduction = GroupReduction::getValueForProduct($product->id, (int) Group::getCurrent()->id);
        $productFull['customer_group_discount'] = $groupReduction === false ? Group::getReduction((int) $context->cookie->id_customer) / 100 : $groupReduction;
        $productFull['title'] = $this->getTitle($product, $context, $idProductAttribute);

        $factory = new ProductPresenterFactory($context, new TaxConfiguration());

        return $factory->getPresenter()->present($factory->getPresentationSettings(), $productFull, $context->language);
    }

    public function filterProductContent(ProductLazyArray $product): ProductLazyArray
    {
        $filteredProduct = Hook::exec('filterProductContent', ['object' => $product], null, false, true, false, null, true);

        return !empty($filteredProduct['object']) ? $filteredProduct['object'] : $product;
    }

    private function getMinimalQuantity(Product $product, Context $context, ?int $idProductAttribute): int
    {
        $minimalQuantity = $product->minimal_quantity;
        if ($idProductAttribute) {
            $combination = $this->findCombination($product, $context, $idProductAttribute);
            if ($combination && $combination['minimal_quantity']) {
                $minimalQuantity = (int) $combination['minimal_quantity'];
            }
        }

        return max(1, (int) $minimalQuantity);
    }

    private function getRequiredQuantity(array $product): int
    {
        return max(1, (int) $product['minimal_quantity'] - (int) $product['cart_quantity']);
    }

    private function getEcotax(array $productForPresentation, Product $product, Context $context): float
    {
        $ecotax = $productForPresentation['ecotax'];
        if ($productForPresentation['id_product_attribute']) {
            $combination = $this->findCombination($product, $context, (int) $productForPresentation['id_product_attribute']);
            if (isset($combination['ecotax']) && $combination['ecotax'] > 0) {
                $ecotax = $combination['ecotax'];
            }
        }
        if ($ecotax) {
            $priceDisplay = $context->smarty->getTemplateVars('priceDisplay');
            $priceDisplay ??= Product::getTaxCalculationMethod((int) $context->cookie->id_customer);
            if ($priceDisplay == 0) {
                $ecotax *= 1 + Tax::getProductEcotaxRate() / 100;
            }
        }

        return (float) $ecotax;
    }

    private function findCombination(Product $product, Context $context, int $idProductAttribute): ?array
    {
        $combinations = $product->getAttributesGroups($context->language->id, $idProductAttribute);

        return is_array($combinations) && $combinations ? reset($combinations) : null;
    }

    private function transformDescriptionWithImages(string $description, Product $product, Context $context): string
    {
        $pattern = '/\[img\-([0-9]+)\-(left|right)\-([a-zA-Z0-9-_]+)\]/';
        while (preg_match($pattern, $description, $matches)) {
            $imageLink = $context->link->getImageLink($product->link_rewrite, $matches[1], $matches[3]);
            $class = $matches[2] === 'left' ? 'class="imageFloatLeft"' : 'class="imageFloatRight"';
            $description = str_replace($matches[0], '<img src="' . $imageLink . '" alt="" ' . $class . '/>', $description);
        }

        return $description;
    }

    private function getTitle(Product $product, Context $context, ?int $idProductAttribute): string
    {
        $title = $product->name;
        if (!Configuration::get('PS_PRODUCT_ATTRIBUTES_IN_TITLE') || !$idProductAttribute) {
            return $title;
        }
        $attributes = $product->getAttributeCombinationsById($idProductAttribute, $context->language->id);
        foreach ($attributes ?: [] as $attribute) {
            $title .= ' ' . $attribute['group_name'] . ' ' . $attribute['attribute_name'];
        }

        return $title;
    }

    private function addCustomizationData(array $productFull, Product $product, Context $context): array
    {
        if (!$productFull['customizable']) {
            $productFull['customizations'] = ['fields' => []];
            $productFull['id_customization'] = 0;
            $productFull['is_customizable'] = false;

            return $productFull;
        }

        $customizationData = ['fields' => []];
        $customizedData = [];
        $idCustomization = 0;
        foreach ($context->cart->getProductCustomization($productFull['id_product'], null, true) as $customization) {
            $idCustomization = $customization['id_customization'];
            $customizedData[$customization['index']] = $customization;
        }
        foreach ($product->getCustomizationFields($context->language->id) ?: [] as $customizationField) {
            $key = $customizationField['id_customization_field'];
            $field['label'] = $customizationField['name'];
            $field['id_customization_field'] = $key;
            $field['required'] = $customizationField['required'];
            switch ($customizationField['type']) {
                case Product::CUSTOMIZE_FILE:
                    $field['type'] = 'image';
                    $field['image'] = null;
                    $field['input_name'] = 'file' . $key;
                    break;
                case Product::CUSTOMIZE_TEXTFIELD:
                    $field['type'] = 'text';
                    $field['text'] = '';
                    $field['input_name'] = 'textField' . $key;
                    break;
                default:
                    $field['type'] = null;
            }
            if (array_key_exists($key, $customizedData)) {
                $data = $customizedData[$key];
                $field['is_customized'] = true;
                switch ($customizationField['type']) {
                    case Product::CUSTOMIZE_FILE:
                        $field['image'] = (new ImageRetriever($context->link))->getCustomizationImage($data['value']);
                        $field['remove_image_url'] = $context->link->getProductDeletePictureLink($productFull, $key);
                        break;
                    case Product::CUSTOMIZE_TEXTFIELD:
                        $field['text'] = $data['value'];
                        break;
                }
            } else {
                $field['is_customized'] = false;
            }
            $customizationData['fields'][] = $field;
        }
        $productFull['customizations'] = $customizationData;
        $productFull['id_customization'] = $idCustomization;
        $productFull['is_customizable'] = true;

        return $productFull;
    }
}
