<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Block\Adminhtml\System\Config;

use Hryvinskyi\RichSnippets\Model\Admin\Settings\LastCheckSummary;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

/**
 * The read-only "Last Check" summary: pages checked, invalid and with a gap, when the last check ran and how long it
 * took, and a link to its findings on the Test page (see LastCheckSummary)
 *
 * Until a check has finished there are no counts, and the summary says so.
 */
class LastCheck extends Field
{
    private const FINDINGS_ROUTE = 'hryvinskyi_richsnippets/test/index';

    /**
     * @param Context $context
     * @param LastCheckSummary $summary
     * @param array<string,mixed> $data
     * @param SecureHtmlRenderer|null $secureRenderer
     */
    public function __construct(
        Context $context,
        private readonly LastCheckSummary $summary,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        parent::__construct($context, $data, $secureRenderer);
    }

    /**
     * The summary
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $summary = $this->summary->summary();
        if ($summary === null) {
            return '<p class="rich-snippets-last-check">'
                . $this->_escaper->escapeHtml((string) __('No check has run yet.'))
                . '</p>';
        }
        $invalidClass = $summary['invalid'] > 0 ? ' rich-snippets-last-check__value--invalid' : '';
        $html = '<div class="rich-snippets-last-check"><table class="rich-snippets-last-check__table"><thead><tr>'
            . '<th>' . $this->_escaper->escapeHtml((string) __('Pages checked')) . '</th>'
            . '<th>' . $this->_escaper->escapeHtml((string) __('Invalid')) . '</th>'
            . '<th>' . $this->_escaper->escapeHtml((string) __('With a gap')) . '</th>'
            . '</tr></thead><tbody><tr>'
            . '<td>' . $summary['pagesChecked'] . '</td>'
            . '<td class="rich-snippets-last-check__value' . $invalidClass . '">' . $summary['invalid'] . '</td>'
            . '<td>' . $summary['withGap'] . '</td>'
            . '</tr></tbody></table>'
            . '<p>' . $this->_escaper->escapeHtml($summary['when']) . '</p>';
        if ($summary['running'] !== null) {
            $html .= '<p>' . $this->_escaper->escapeHtml($summary['running']) . '</p>';
        }

        return $html . '<p><a href="' . $this->_escaper->escapeUrl($this->findingsUrl()) . '">'
            . $this->_escaper->escapeHtml((string) __('Open the findings')) . '</a></p></div>';
    }

    /**
     * A read-only summary has no value to inherit
     *
     * @param AbstractElement $element
     * @return bool
     */
    protected function _isInheritCheckboxRequired(AbstractElement $element)
    {
        return false;
    }

    /**
     * The address of the Test page's "Whole catalog" mode
     *
     * @return string
     */
    private function findingsUrl(): string
    {
        return $this->getUrl(self::FINDINGS_ROUTE, ['_query' => ['mode' => 'catalog']]);
    }
}
