<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Controller\Adminhtml\Test;

use Hryvinskyi\AdminSetupWizard\Api\Http\JsonAnswerInterface;
use Hryvinskyi\AdminSetupWizard\Api\Http\JsonBodyInterface;
use Hryvinskyi\RichSnippets\Model\Admin\AdminResources;
use Hryvinskyi\RichSnippets\Model\Admin\Test\TestAnswers;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;

/**
 * Tests one storefront page: `{"page": "kind:id"}` or `{"path": "/…"}`, with `store`, in; out, what the page carries
 * as a visitor reads it, its checks and why the other snippets are not there (see PageTestPresenter)
 *
 * It reads the storefront, so it is a POST: it makes the storefront render and cache the page, and keeps what it
 * saw of the theme's microdata.
 */
class Run extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = AdminResources::TEST;

    /** The route path an address of this endpoint is built from: the route id, never the front name */
    public const ROUTE = 'hryvinskyi_richsnippets/test/run';

    /**
     * @param Context $context
     * @param JsonBodyInterface $jsonBody
     * @param TestAnswers $answers
     * @param JsonAnswerInterface $jsonAnswer
     */
    public function __construct(
        Context $context,
        private readonly JsonBodyInterface $jsonBody,
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
            'rich_snippets.test.run',
            fn (): array => $this->answers->run($this->jsonBody->read($this->getRequest()))
        );
    }
}
