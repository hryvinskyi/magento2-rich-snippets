<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets;

use Hryvinskyi\AdminUi\Api\Page\IslandInterface;
use Hryvinskyi\AdminUi\Api\Page\IslandPageInterface;
use Hryvinskyi\RichSnippets\Model\Admin\AdminResources;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * Every snippet of the shop in one list: what each is, on which pages, under which condition and for which store
 * views it is output, with its switch, its row menu, and moving snippets between shops
 *
 * The page's island is opened before the page is rendered, carrying the first state, so the page draws without a
 * round trip; the island draws the module's header and navigation above it.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = AdminResources::VIEW;

    /** The route path of this page: the route id, never the front name */
    public const ROUTE = 'hryvinskyi_richsnippets/snippets/index';

    /** The menu item the page highlights */
    public const MENU_ID = 'Hryvinskyi_RichSnippets::rich_snippets';

    /**
     * @param Context $context
     * @param IslandPageInterface $islandPage The page host the page's layout prints the island from
     * @param IslandInterface $island The snippet list's island
     * @param PageFactory $pageFactory
     */
    public function __construct(
        Context $context,
        private readonly IslandPageInterface $islandPage,
        private readonly IslandInterface $island,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Open the island and render the page under its title and menu item
     *
     * @return Page
     * @throws \Hryvinskyi\AdminUi\Api\Exception\InvalidIslandException When the island cannot be printed
     * @throws \Magento\Framework\Exception\LocalizedException When no form key can be made or a file cannot be
     *     resolved
     */
    public function execute(): Page
    {
        $this->islandPage->open($this->island);

        $page = $this->pageFactory->create();
        if (!$page instanceof Page) {
            throw new \LogicException('The admin area renders admin pages.');
        }
        $page->setActiveMenu(self::MENU_ID);
        $page->getConfig()->getTitle()->prepend((string) __('Rich Snippets'));
        $page->getConfig()->getTitle()->prepend((string) __('Snippets'));

        return $page;
    }
}
