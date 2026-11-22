<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Controller\Adminhtml\Library;

use Hryvinskyi\AdminSetupWizard\Api\Http\JsonAnswerInterface;
use Hryvinskyi\AdminSetupWizard\Api\Http\JsonBodyInterface;
use Hryvinskyi\RichSnippets\Model\Admin\AdminResources;
use Hryvinskyi\RichSnippets\Model\Admin\Library\LibraryWrites;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;

/**
 * Adds the recommended templates the shop has no copy of, each switched off with its suggested answers and the
 * building blocks it uses: `{"idempotencyKey"}` in; out, what the add ended with and the library after it (see
 * LibraryWrites)
 *
 * The key is generated once per press of *Add the recommended set*; a request repeated with it adds nothing more and
 * answers the first add.
 */
class AddRecommended extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = AdminResources::SNIPPETS;

    /** The route path an address of this endpoint is built from: the route id, never the front name */
    public const ROUTE = 'hryvinskyi_richsnippets/library/addRecommended';

    /**
     * @param Context $context
     * @param JsonBodyInterface $jsonBody
     * @param LibraryWrites $writes
     * @param JsonAnswerInterface $jsonAnswer
     */
    public function __construct(
        Context $context,
        private readonly JsonBodyInterface $jsonBody,
        private readonly LibraryWrites $writes,
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
            'rich_snippets.library.add_recommended',
            fn (): array => $this->writes->addRecommended($this->jsonBody->read($this->getRequest()))
        );
    }
}
