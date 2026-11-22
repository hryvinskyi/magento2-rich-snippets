<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Controller\Adminhtml\Test;

use Hryvinskyi\AdminSetupWizard\Api\Http\JsonAnswerInterface;
use Hryvinskyi\RichSnippets\Model\Admin\AdminResources;
use Hryvinskyi\RichSnippets\Model\Admin\Test\TestAnswers;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;

/**
 * Answers the page test's page picker: `?query=<text>&store=<id>`; out, the pages whose name holds the text and the
 * text as any address when it is a storefront path (see TestAnswers)
 */
class Search extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = AdminResources::TEST;

    /** The route path an address of this endpoint is built from: the route id, never the front name */
    public const ROUTE = 'hryvinskyi_richsnippets/test/search';

    /**
     * @param Context $context
     * @param TestAnswers $answers
     * @param JsonAnswerInterface $jsonAnswer
     */
    public function __construct(
        Context $context,
        private readonly TestAnswers $answers,
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
            'rich_snippets.test.search',
            fn (): array => $this->answers->search($this->getRequest()->getParams())
        );
    }
}
