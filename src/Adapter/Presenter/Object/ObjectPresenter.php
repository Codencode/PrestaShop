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

use CMS;
use Context;
use Exception;
use Hook;
use ObjectModel;
use PrestaShop\PrestaShop\Adapter\Presenter\PresenterInterface;

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

        // TODO <cnc> - ObjectPresenter::present() - modifica per rendere generale gli shortcodes
        switch (get_class($object)) {
            case 'CMS':
                $presentedObject['content'] = $this->processShortcodes($presentedObject['content']);
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
    {// TODO <cnc> - ObjectPresenter::processShortcodes() - - modifica per rendere generale gli shortcodes
        /** @var Context $context */
        $context = Context::getContext();
        $pattern = '/(URL_CMS_ID|NAME_CMS_ID)=(\d+)/';

        preg_match_all($pattern, $content, $cmsIds);

        $content = preg_replace_callback($pattern, function ($matches) use ($context) {
            /**
             * // TODO <cnc-modifica> - ObjectPresenter::processShortcodes() - DA MIGLIORARE:
             * - qui bisognerebbe non caricare ogni volta il CMS
             * - oltre all'entity CMS potrebbe essere anche:
             *      - CMSCategory
             *      - Product
             *      - Category
             *      - Manufacturer
             */
            $cms = new CMS((int) $matches[2], $context->language->id);

            if ($matches[1] == 'NAME_CMS_ID') {
                $value = $cms->meta_title;
            } else {
                $value = $context->link->getCMSLink($cms);
            }

            return $value;
        }, $content);

        return $content;
    }
}
