<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets;

use Hryvinskyi\AdminSetupWizard\Api\Http\JsonAnswerInterface;
use Hryvinskyi\RichSnippets\Model\Admin\AdminResources;
use Hryvinskyi\RichSnippets\Model\Admin\Editor\ConditionCatalogs;
use Hryvinskyi\RichSnippets\Model\Admin\Editor\EditorRequest;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;

/**
 * Answers what the expression builder asks for a condition's value: `?attribute=<code>` (and `field=<code>`
 * optionally) for the attribute's options, `{"options": [...]}`; with `q=<text>`, the values matching it,
 * `{"items": [...]}`. An attribute no field offers is refused with HTTP 400 (see ConditionCatalogs)
 *
 * The options are read with the permission to see the module, so the read-only editor shows a condition's values by
 * their labels. The matching values are what the catalog's products hold for the attribute, so asking for them
 * needs the permission to change snippets too.
 */
class ConditionOptions extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = AdminResources::VIEW;

    /** The route path an address of this endpoint is built from: the route id, never the front name */
    public const ROUTE = 'hryvinskyi_richsnippets/snippets/conditionOptions';

    /**
     * @param Context $context
     * @param ConditionCatalogs $catalogs
     * @param JsonAnswerInterface $jsonAnswer
     */
    public function __construct(
        Context $context,
        private readonly ConditionCatalogs $catalogs,
        private readonly JsonAnswerInterface $jsonAnswer
    ) {
        parent::__construct($context);
    }

    /**
     * Whether the signed-in admin may read what the request asks for
     *
     * @return bool
     */
    protected function _isAllowed(): bool
    {
        $query = $this->getRequest()->getParam(EditorRequest::SUGGEST_QUERY, '');

        return parent::_isAllowed()
            && ($query === '' || $query === null || $this->_authorization->isAllowed(AdminResources::SNIPPETS));
    }

    /**
     * Read the request, run the use case and answer its outcome
     *
     * @return Json
     */
    public function execute(): Json
    {
        return $this->jsonAnswer->answer(
            'rich_snippets.snippets.conditionOptions',
            fn (): array => $this->catalogs->conditionOptions($this->getRequest()->getParams())
        );
    }
}
