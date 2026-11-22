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
use Hryvinskyi\RichSnippets\Model\Admin\Editor\EditorCatalogs;
use Hryvinskyi\RichSnippets\Model\Admin\Editor\EditorRequest;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;

/**
 * Answers the field catalog: `?sample=<kind:id>&store=<id>`; out, the fields by group with each one's value on
 * the sample page, the lists, the enumerated values per property and the properties Google asks for per type
 * (see EditorCatalogs)
 *
 * The catalog without a sample page is read with the permission to see the module, as the read-only editor reads
 * it; the values of a sample page are catalog data, so asking for them needs the permission to change snippets too.
 */
class Fields extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = AdminResources::VIEW;

    /** The route path an address of this endpoint is built from: the route id, never the front name */
    public const ROUTE = 'hryvinskyi_richsnippets/snippets/fields';

    /**
     * @param Context $context
     * @param EditorCatalogs $catalogs
     * @param JsonAnswerInterface $jsonAnswer
     */
    public function __construct(
        Context $context,
        private readonly EditorCatalogs $catalogs,
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
        $sample = $this->getRequest()->getParam(EditorRequest::SAMPLE, '');

        return parent::_isAllowed()
            && ($sample === '' || $sample === null || $this->_authorization->isAllowed(AdminResources::SNIPPETS));
    }

    /**
     * Read the request, run the use case and answer its outcome
     *
     * @return Json
     */
    public function execute(): Json
    {
        return $this->jsonAnswer->answer(
            'rich_snippets.snippets.fields',
            fn (): array => $this->catalogs->fields($this->getRequest()->getParams())
        );
    }
}
