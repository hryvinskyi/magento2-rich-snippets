<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Block;

use Hryvinskyi\RichSnippets\ViewModel\StorefrontSnippets;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;

/**
 * The snippets' place in every storefront page, at the end of the body
 *
 * It writes the page's script blocks when it renders (into the head, or right here) and gives the page the tags a
 * snippet change cleans it by. It is never cached on its own: the page cache keeps it as part of the page.
 */
class Snippets extends AbstractBlock implements IdentityInterface
{
    /**
     * @param Context $context
     * @param StorefrontSnippets $snippets
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly StorefrontSnippets $snippets,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritDoc
     *
     * @return list<string>
     */
    public function getIdentities(): array
    {
        return $this->snippets->identities();
    }

    /**
     * @inheritDoc
     */
    protected function _toHtml(): string
    {
        return $this->snippets->write();
    }
}
