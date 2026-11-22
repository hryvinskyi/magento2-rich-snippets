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
use Hryvinskyi\RichSnippets\Model\Admin\Snippets\SnippetWrites;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;

/**
 * Deletes a snippet: `{"id", "store"}` in; out, the list after the write, or the refusal naming the snippets that
 * include it (see SnippetWrites)
 */
class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = AdminResources::SNIPPETS;

    /** The route path an address of this endpoint is built from: the route id, never the front name */
    public const ROUTE = 'hryvinskyi_richsnippets/snippets/delete';

    /**
     * @param Context $context
     * @param JsonBodyInterface $jsonBody
     * @param SnippetWrites $writes
     * @param JsonAnswerInterface $jsonAnswer
     */
    public function __construct(
        Context $context,
        private readonly JsonBodyInterface $jsonBody,
        private readonly SnippetWrites $writes,
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
            'rich_snippets.snippets.delete',
            fn (): array => $this->writes->delete($this->jsonBody->read($this->getRequest()))
        );
    }
}
