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
use Hryvinskyi\RichSnippets\Model\Admin\Editor\ConditionCatalogs;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;

/**
 * Counts the pages a placement reaches, kind by kind, and puts the placement into words:
 * `{"pageKinds", "conditions", "storeIds", "store"}` in; out, `{"kinds", "matching", "total", "sentence", "count",
 * "countLine", "asked"}` (see ConditionCatalogs)
 *
 * It reads nothing it changes; it is a POST because the conditions travel as a JSON body. The conditions it is sent
 * may test any attribute, so its counts tell what the catalog holds: it needs the permission to change snippets.
 */
class PlacementCount extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = AdminResources::SNIPPETS;

    /** The route path an address of this endpoint is built from: the route id, never the front name */
    public const ROUTE = 'hryvinskyi_richsnippets/snippets/placementCount';

    /**
     * @param Context $context
     * @param JsonBodyInterface $jsonBody
     * @param ConditionCatalogs $catalogs
     * @param JsonAnswerInterface $jsonAnswer
     */
    public function __construct(
        Context $context,
        private readonly JsonBodyInterface $jsonBody,
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
            'rich_snippets.snippets.placementCount',
            fn (): array => $this->catalogs->placementCount($this->jsonBody->read($this->getRequest()))
        );
    }
}
