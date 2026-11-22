<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Block\Adminhtml\System\Config\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * A text setting that shows an example in its empty input, taken from the field's `placeholder` attribute
 */
class Placeholder extends Field
{
    private const FIELD_CONFIG = 'field_config';
    private const PLACEHOLDER = 'placeholder';

    /**
     * The input with the field's example as its placeholder
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $config = $element->getData(self::FIELD_CONFIG);
        $placeholder = is_array($config) ? ($config[self::PLACEHOLDER] ?? null) : null;
        if (is_string($placeholder) && $placeholder !== '') {
            $element->setData(self::PLACEHOLDER, $placeholder);
        }

        return parent::_getElementHtml($element);
    }
}
