<?php

/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace PrestaShop\PrestaShop\Adapter\Presenter\Object;

use Category;
use CMS;
use CMSCategory;
use Context;
use Exception;
use Hook;
use Manufacturer;
use ObjectModel;
use PrestaShop\PrestaShop\Adapter\Presenter\PresenterInterface;
use Product;
use Validate;

class ObjectPresenter implements PresenterInterface
{
    /**
     * @param ObjectModel $object
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function present($object)
    {
        if (!($object instanceof ObjectModel)) {
            throw new Exception('ObjectPresenter can only present ObjectModel classes');
        }

        $presentedObject = [];

        $fields = $object::$definition['fields'];
        foreach ($fields as $fieldName => $null) {
            $presentedObject[$fieldName] = $object->{$fieldName};
        }

        // TODO <cnc-modifica> - ObjectPresenter::present() - modifica per rendere generale gli shortcodes
        switch (get_class($object)) {
            case 'Product':
                $presentedObject['description'] = $this->processShortcodes($presentedObject['description']);
                break;
            case 'Category':
                $presentedObject['description'] = $this->processShortcodes($presentedObject['description']);
                break;
            case 'CMS':
                $presentedObject['content'] = $this->processShortcodes($presentedObject['content']);
                break;
            case 'Manufacturer':
                $presentedObject['description'] = $this->processShortcodes($presentedObject['description']);
                break;
        }
        // ********************************************************

        $presentedObject['id'] = $object->id;

        Hook::exec('actionPresentObject', ['presentedObject' => &$presentedObject, 'table' => $object::$definition['table']]);

        $this->filterHtmlContent($object::$definition['table'], $presentedObject, $object->getHtmlFields());

        return $presentedObject;
    }

    /**
     * Execute filterHtml hook for html Content for objectPresenter.
     *
     * @param string $type
     * @param ObjectModel $presentedObject
     * @param array $htmlFields
     */
    private function filterHtmlContent($type, &$presentedObject, $htmlFields)
    {
        if (!empty($htmlFields) && is_array($htmlFields)) {
            // Chained hook call - if multiple modules are hooked here, they will receive the result of the previous one as a parameter
            $filteredHtml = Hook::exec(
                'filterHtmlContent',
                [
                    'type' => $type,
                    'htmlFields' => $htmlFields,
                    'object' => $presentedObject,
                ],
                null,
                false,
                true,
                false,
                null,
                true
            );

            if (!empty($filteredHtml['object'])) {
                $presentedObject = $filteredHtml['object'];
            }
        }
    }

    private function processShortcodes($content): string
    { // TODO <cnc-modifica> - ObjectPresenter::processShortcodes() - - modifica per rendere generale gli shortcodes
        /** @var Context $context */
        $context = Context::getContext();
        $pattern = '/<span\s+data-entity-type="([^"]+)"\s+data-entity-id="(\d+)"/';

        preg_match_all($pattern, $content, $entitiesIds);

        $content = preg_replace_callback($pattern, function ($matches) use ($context) {
            /**
             * // TODO <cnc-modifica> - ObjectPresenter::processShortcodes() - DA MIGLIORARE:
             * - qui bisognerebbe non caricare ogni volta il CMS
             * - questo codice forse andrebbe spostato in un service richiamabile da qualsiasi punto,
             *   si potrebbe creare un metodo nell'ObjectModel per convertire gli shortcodes
             */
            $entityType = $matches[1];
            $entityId = (int) $matches[2];
            $langId = (int) $context->language->id;
            $anchorText = false;
            $link = false;

            switch ($entityType) {
                case 'product':
                    $entity = new Product($entityId, false, $langId);
                    if (Validate::isLoadedObject($entity)) {
                        $anchorText = $entity->name;
                        $link = $context->link->getProductLink($entity);
                    }
                    break;
                case 'category':
                    $entity = new Category($entityId, $langId);
                    if (Validate::isLoadedObject($entity)) {
                        $anchorText = $entity->name;
                        $link = $context->link->getCategoryLink($entity);
                    }
                    break;
                case 'cms':
                    $entity = new CMS($entityId, $langId);
                    if (Validate::isLoadedObject($entity)) {
                        $anchorText = $entity->meta_title;
                        $link = $context->link->getCMSLink($entity);
                    }
                    break;
                case 'cmsCategory':
                    $entity = new CMSCategory($entityId, $langId);
                    if (Validate::isLoadedObject($entity)) {
                        $anchorText = $entity->name;
                        $link = $context->link->getCMSCategoryLink($entity);
                    }
                    break;
                case 'manufacturer':
                    $entity = new Manufacturer($entityId, $langId);
                    if (Validate::isLoadedObject($entity)) {
                        $anchorText = $entity->name;
                        $link = $context->link->getManufacturerLink($entity);
                    }
                    break;
            }

            if ($anchorText !== false && $link !== false) {
                return '<a href="' . $link . '">' . $anchorText . '</a>';
            }

            return '';
        }, $content);

        return $content;
    }
}
