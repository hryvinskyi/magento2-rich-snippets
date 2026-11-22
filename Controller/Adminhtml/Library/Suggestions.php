<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Controller\Adminhtml\Library;

use Hryvinskyi\AdminSetupWizard\Api\Http\JsonAnswerInterface;
use Hryvinskyi\RichSnippets\Model\Admin\AdminResources;
use Hryvinskyi\RichSnippets\Model\Admin\Library\LibraryQuestions;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;

/**
 * Answers the questions a template asks when it is added, with the answers this shop is offered: `?template=<code>`
 * in; out, `{"template", "questions"}` (see LibraryQuestions)
 *
 * A template the library does not have answers 400 `{"error": "template_unknown"}`; a failure is logged and answers
 * 500 `{"error": "error"}`.
 */
class Suggestions extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = AdminResources::VIEW;

    /** The route path an address of this endpoint is built from: the route id, never the front name */
    public const ROUTE = 'hryvinskyi_richsnippets/library/suggestions';

    /**
     * @param Context $context
     * @param LibraryQuestions $questions
     * @param JsonAnswerInterface $jsonAnswer
     */
    public function __construct(
        Context $context,
        private readonly LibraryQuestions $questions,
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
            'rich_snippets.library.suggestions',
            fn (): array => $this->questions->of($this->getRequest()->getParams())
        );
    }
}
