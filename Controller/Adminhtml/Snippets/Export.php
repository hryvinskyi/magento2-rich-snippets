<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets;

use Hryvinskyi\RichSnippets\Model\Admin\AdminResources;
use Hryvinskyi\RichSnippets\Model\Admin\Snippets\ExportDownload;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;

/**
 * Downloads an export as a JSON file: `?id=<id>` for one snippet, `{name}.json`; nothing for every snippet,
 * `rich-snippets-{shop}.json`
 *
 * A snippet that is not stored answers 400 `{"error": "unknown_snippet"}`.
 */
class Export extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = AdminResources::SNIPPETS;

    /** The route path an address of this endpoint is built from: the route id, never the front name */
    public const ROUTE = 'hryvinskyi_richsnippets/snippets/export';

    /**
     * @param Context $context
     * @param ExportDownload $download
     */
    public function __construct(
        Context $context,
        private readonly ExportDownload $download
    ) {
        parent::__construct($context);
    }

    /**
     * Answer the export the query asks for
     *
     * @return Json
     */
    public function execute(): Json
    {
        return $this->download->answer($this->getRequest()->getParams());
    }
}
