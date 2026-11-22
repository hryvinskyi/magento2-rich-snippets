<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets;

use Hryvinskyi\RichSnippets\Model\Admin\AdminResources;
use Hryvinskyi\RichSnippets\Model\Admin\Editor\SaveAnswer;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;

/**
 * Saves the editor's draft: `{"snippet", "updatedAt", "idempotencyKey"}` in; out, the snippet as stored or the
 * problems that stopped the save (see EditorDocument)
 *
 * `updatedAt` is the version the editor loaded: a save after someone else saved the snippet answers 409 with when and
 * by whom. A new snippet carries an idempotency key generated once per click; a request repeated with it creates
 * nothing more and answers the snippet the first one created.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = AdminResources::SNIPPETS;

    /** The route path an address of this endpoint is built from: the route id, never the front name */
    public const ROUTE = 'hryvinskyi_richsnippets/snippets/save';

    /**
     * @param Context $context
     * @param SaveAnswer $saveAnswer
     */
    public function __construct(
        Context $context,
        private readonly SaveAnswer $saveAnswer
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
        return $this->saveAnswer->answer($this->getRequest());
    }
}
