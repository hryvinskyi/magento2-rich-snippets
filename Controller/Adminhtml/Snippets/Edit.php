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
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Admin\AdminResources;
use Hryvinskyi\RichSnippets\Model\Admin\Snippets\SnippetsRequest;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Forward;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;

/**
 * The editor of one stored snippet, named by `?id=`: what it describes, its properties drawn as a builder and where
 * it is output
 *
 * The island is opened before the page is rendered, carrying the snippet, so the editor draws without a round trip.
 * An address without a stored snippet's ID answers the admin's page-not-found.
 */
class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = AdminResources::VIEW;

    /** The route path of this page: the route id, never the front name */
    public const ROUTE = 'hryvinskyi_richsnippets/snippets/edit';

    /** The menu item the page highlights */
    public const MENU_ID = 'Hryvinskyi_RichSnippets::rich_snippets';

    /**
     * @param Context $context
     * @param IslandPageInterface $islandPage The page host the page's layout prints the island from
     * @param IslandInterface $island The editor's island
     * @param SnippetRepositoryInterface $snippets
     * @param PageFactory $pageFactory
     * @param ForwardFactory $forwardFactory
     */
    public function __construct(
        Context $context,
        private readonly IslandPageInterface $islandPage,
        private readonly IslandInterface $island,
        private readonly SnippetRepositoryInterface $snippets,
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Open the island for the snippet asked for and render the page under its name and menu item
     *
     * @return Page|Forward
     * @throws \Hryvinskyi\AdminUi\Api\Exception\InvalidIslandException When the island cannot be printed
     * @throws \Magento\Framework\Exception\LocalizedException When no form key can be made or a file cannot be
     *     resolved
     */
    public function execute(): Page|Forward
    {
        $id = $this->getRequest()->getParam(SnippetsRequest::ID, '');
        $id = is_string($id) && ctype_digit($id) ? (int) $id : 0;
        try {
            $snippet = $this->snippets->get($id);
        } catch (NoSuchEntityException) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $this->islandPage->open($this->island);

        $page = $this->pageFactory->create();
        if (!$page instanceof Page) {
            throw new \LogicException('The admin area renders admin pages.');
        }
        $page->setActiveMenu(self::MENU_ID);
        $page->getConfig()->getTitle()->prepend((string) __('Rich Snippets'));
        $name = $snippet->getName();
        $page->getConfig()->getTitle()->prepend($name === '' ? (string) __('Snippets') : $name);

        return $page;
    }
}
