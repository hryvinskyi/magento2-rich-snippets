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
use Hryvinskyi\RichSnippets\Model\Admin\Check\CheckAnswers;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;

/**
 * "Check now": asks for a catalog check and answers where the check stands (see CheckStatusPresenter)
 *
 * Asked while a check is requested or running, it asks for nothing more, so a second click adds no run.
 */
class CheckRequest extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = AdminResources::TEST;

    /** The route path an address of this endpoint is built from: the route id, never the front name */
    public const ROUTE = 'hryvinskyi_richsnippets/test/checkRequest';

    /**
     * @param Context $context
     * @param CheckAnswers $answers
     * @param JsonAnswerInterface $jsonAnswer
     */
    public function __construct(
        Context $context,
        private readonly CheckAnswers $answers,
        private readonly JsonAnswerInterface $jsonAnswer
    ) {
        parent::__construct($context);
    }

    /**
     * Ask for the check and answer where it stands
     *
     * @return Json
     */
    public function execute(): Json
    {
        return $this->jsonAnswer->answer('rich_snippets.test.check_request', fn (): array => $this->answers->request());
    }
}
