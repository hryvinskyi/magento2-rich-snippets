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
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;

/**
 * Answers the expression builder's configuration for the picked kinds of page: `?kinds=product,cms&store=<id>`;
 * out, `{"config": {...}}` (see ConditionCatalogs)
 *
 * The builder offers value suggestions only to an admin who may change snippets, who alone may ask for them.
 */
class BuilderConfig extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = AdminResources::VIEW;

    /** The route path an address of this endpoint is built from: the route id, never the front name */
    public const ROUTE = 'hryvinskyi_richsnippets/snippets/builderConfig';

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
     * Read the request, run the use case and answer its outcome
     *
     * @return Json
     */
    public function execute(): Json
    {
        return $this->jsonAnswer->answer(
            'rich_snippets.snippets.builderConfig',
            fn (): array => $this->catalogs->builderConfig(
                $this->getRequest()->getParams(),
                $this->_authorization->isAllowed(AdminResources::SNIPPETS)
            )
        );
    }
}
