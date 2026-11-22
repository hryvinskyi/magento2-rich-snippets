<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Block\Adminhtml\System\Config;

use Hryvinskyi\RichSnippets\Model\Admin\Settings\ConfigScopeStore;
use Hryvinskyi\RichSnippets\Model\Admin\Settings\ThemeMarkupLine;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

/**
 * The read-only "Found on the Storefront" line: what the last page test saw of the theme's own Product markup in
 * the store view of the scope shown, with a dot of its tone (see ThemeMarkupLine)
 */
class ThemeMarkup extends Field
{
    /**
     * @param Context $context
     * @param ThemeMarkupLine $line
     * @param ConfigScopeStore $scopeStore
     * @param array<string,mixed> $data
     * @param SecureHtmlRenderer|null $secureRenderer
     */
    public function __construct(
        Context $context,
        private readonly ThemeMarkupLine $line,
        private readonly ConfigScopeStore $scopeStore,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        parent::__construct($context, $data, $secureRenderer);
    }

    /**
     * The status line
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $request = $this->getRequest();
        $storeId = $this->scopeStore->storeId($request->getParam('website'), $request->getParam('store'));
        $line = $storeId === null
            ? ['tone' => 'none', 'text' => (string) __('No page test has looked at a product page yet.')]
            : $this->line->forStore($storeId);

        return '<p class="rich-snippets-status rich-snippets-status--' . $this->_escaper->escapeHtmlAttr($line['tone'])
            . '"><span class="rich-snippets-status__dot" aria-hidden="true"></span>'
            . $this->_escaper->escapeHtml($line['text']) . '</p>';
    }

    /**
     * A read-only line has no value to inherit
     *
     * @param AbstractElement $element
     * @return bool
     */
    protected function _isInheritCheckboxRequired(AbstractElement $element)
    {
        return false;
    }
}
