<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Controller\Adminhtml\Overview;

use Hryvinskyi\AdminSetupWizard\Api\Http\JsonAnswerInterface;
use Hryvinskyi\RichSnippets\Model\Admin\AdminResources;
use Hryvinskyi\RichSnippets\Model\Admin\Overview\OverviewAnswers;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;

/**
 * Answers the Overview's numbers: `?store=<id>`, every store view when left out (see OverviewPresenter)
 */
class Data extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = AdminResources::VIEW;

    /** The route path an address of this endpoint is built from: the route id, never the front name */
    public const ROUTE = 'hryvinskyi_richsnippets/overview/data';

    /**
     * @param Context $context
     * @param OverviewAnswers $answers
     * @param JsonAnswerInterface $jsonAnswer
     */
    public function __construct(
        Context $context,
        private readonly OverviewAnswers $answers,
        private readonly JsonAnswerInterface $jsonAnswer
    ) {
        parent::__construct($context);
    }

    /**
     * Answer the Overview
     *
     * @return Json
     */
    public function execute(): Json
    {
        return $this->jsonAnswer->answer(
            'rich_snippets.overview.data',
            fn (): array => $this->answers->read($this->getRequest()->getParams())
        );
    }
}
