<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Controller\Adminhtml\Test;

use Hryvinskyi\RichSnippets\Model\Admin\AdminResources;
use Hryvinskyi\RichSnippets\Model\Admin\Check\FindingsCsvDownload;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;

/**
 * Downloads every page behind a finding as `rich-snippets-{key}.csv`: `?key=<key>&store=<id>`
 *
 * A key the last check did not find answers 400 `{"error": "key"}`.
 */
class FindingsCsv extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = AdminResources::VIEW;

    /** The route path an address of this endpoint is built from: the route id, never the front name */
    public const ROUTE = 'hryvinskyi_richsnippets/test/findingsCsv';

    /**
     * @param Context $context
     * @param FindingsCsvDownload $download
     */
    public function __construct(
        Context $context,
        private readonly FindingsCsvDownload $download
    ) {
        parent::__construct($context);
    }

    /**
     * Answer the file
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        return $this->download->answer($this->getRequest()->getParams());
    }
}
