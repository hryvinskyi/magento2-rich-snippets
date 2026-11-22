<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets;

use Hryvinskyi\AdminSetupWizard\Api\Http\JsonAnswerInterface;
use Hryvinskyi\AdminSetupWizard\Api\Http\JsonBodyInterface;
use Hryvinskyi\RichSnippets\Model\Admin\AdminResources;
use Hryvinskyi\RichSnippets\Model\Admin\Snippets\SnippetTransfers;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;

/**
 * Tells what importing a file would do, changing nothing: `{"file"}` in, the file's text; out, each snippet of the
 * file with its status, choices and problems (see SnippetTransfers)
 *
 * A file that is not an export this module reads answers 400 with the reason (`not_an_export` …).
 */
class ImportPreview extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = AdminResources::SNIPPETS;

    /** The route path an address of this endpoint is built from: the route id, never the front name */
    public const ROUTE = 'hryvinskyi_richsnippets/snippets/importPreview';

    /**
     * @param Context $context
     * @param JsonBodyInterface $jsonBody
     * @param SnippetTransfers $transfers
     * @param JsonAnswerInterface $jsonAnswer
     */
    public function __construct(
        Context $context,
        private readonly JsonBodyInterface $jsonBody,
        private readonly SnippetTransfers $transfers,
        private readonly JsonAnswerInterface $jsonAnswer
    ) {
        parent::__construct($context);
    }

    /**
     * Read the request, run the use case and answer its outcome
     *
     * @return Json
     */
    public function execute(): Json
    {
        return $this->jsonAnswer->answer(
            'rich_snippets.snippets.import_preview',
            fn (): array => $this->transfers->preview($this->jsonBody->read($this->getRequest()))
        );
    }
}
