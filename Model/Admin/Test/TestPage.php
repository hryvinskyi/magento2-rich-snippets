<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Test;

use Hryvinskyi\AdminSetupWizard\Api\Exception\InvalidWizardRequestException;
use Hryvinskyi\AdminUi\Api\Page\KeyedAdminUrlInterface;
use Hryvinskyi\AdminUi\Api\Page\PageContentInterface;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Test\CheckRequest;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Test\CheckStatus;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Test\FindingItems;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Test\Findings;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Test\FindingsCsv;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Test\Run;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Test\Search;
use Hryvinskyi\RichSnippets\Model\Admin\Check\CheckAnswers;
use Hryvinskyi\RichSnippets\Model\Admin\Check\CheckStatusPresenter;
use Hryvinskyi\RichSnippets\Model\Admin\Check\FindingRows;
use Hryvinskyi\RichSnippets\Model\Admin\Check\ScheduleLine;
use Hryvinskyi\RichSnippets\Model\Admin\Editor\EditorRequest;
use Hryvinskyi\RichSnippets\Model\Admin\Snippets\SnippetsRequest;
use Magento\Framework\App\RequestInterface;

/**
 * What the Test page hands its island: where the catalog check stands and the findings of the last one, so the
 * "Whole catalog" mode draws without a round trip, and the page the "One page" mode tests first
 *
 * The data is `{"mode", "store", "page", "catalogStore", "finding", "schedule", "status", "findings"}`: `mode` is
 * `catalog` when the address asks for it (`?mode=catalog`), else `page`; `store` the store view the page test reads,
 * the one the address names (`?store=<id>`) or the default one; `page` the page tested first (see FirstPage), null
 * when there is none; `catalogStore` the store view the findings are of, the one the address names or null for every
 * store view; `finding` the key of the finding whose pages open first (`?finding=<key>`), null unless the findings
 * hold it; `schedule` when the check runs by itself (see ScheduleLine); `status` where the check stands (see
 * CheckStatusPresenter); `findings` the findings table (see FindingRows). A store view the shop does not have is
 * taken as none named.
 *
 * The endpoints are the page picker's search, the page test, where the check stands, "Check now", the findings, the
 * pages behind one and their download. The page links to a snippet's editor, to the settings, and to the admin
 * screens of the pages behind a finding, each drawn only for an admin who may open it.
 *
 * @phpstan-import-type Picked from FirstPage
 * @phpstan-import-type Answer from CheckStatusPresenter
 * @phpstan-import-type Row from FindingRows
 */
class TestPage implements PageContentInterface
{
    /** The address's parameters this page reads */
    public const MODE = 'mode';
    public const PAGE = 'page';
    public const PATH = 'path';
    public const FINDING = 'finding';

    /** The modes the page has; the first is the one it opens in unless the address names another */
    private const MODES = ['page', 'catalog'];

    /** The screens the Test page links to, by route path */
    public const ROUTES = [
        'hryvinskyi_richsnippets/snippets/edit',
        'adminhtml/system_config/edit',
        'catalog/product/index',
        'catalog/product/edit',
        'catalog/category/edit',
        'cms/page/edit',
    ];

    /**
     * @param RequestInterface $request
     * @param EditorRequest $editorRequest
     * @param SnippetsRequest $snippetsRequest
     * @param FirstPage $firstPage
     * @param CheckAnswers $checks
     * @param ScheduleLine $schedule
     * @param KeyedAdminUrlInterface $urls
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly EditorRequest $editorRequest,
        private readonly SnippetsRequest $snippetsRequest,
        private readonly FirstPage $firstPage,
        private readonly CheckAnswers $checks,
        private readonly ScheduleLine $schedule,
        private readonly KeyedAdminUrlInterface $urls
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getUrls(): array
    {
        return [
            'search' => $this->urls->getUrl(Search::ROUTE),
            'run' => $this->urls->getUrl(Run::ROUTE),
            'checkStatus' => $this->urls->getUrl(CheckStatus::ROUTE),
            'checkRequest' => $this->urls->getUrl(CheckRequest::ROUTE),
            'findings' => $this->urls->getUrl(Findings::ROUTE),
            'findingItems' => $this->urls->getUrl(FindingItems::ROUTE),
            'findingsCsv' => $this->urls->getUrl(FindingsCsv::ROUTE),
        ];
    }

    /**
     * @inheritDoc
     */
    public function getRoutes(): array
    {
        return self::ROUTES;
    }

    /**
     * @inheritDoc
     *
     * @return array{mode: string, store: int, page: Picked|null, catalogStore: int|null, finding: string|null,
     *     schedule: string, status: Answer, findings: array{store: int|null, subtitle: string, findings: list<Row>}}
     * @throws InvalidWizardRequestException Never: the store view the findings are read for is one the shop has
     */
    public function getData(): array
    {
        $query = $this->request->getParams();
        $storeId = $this->storeId($query);
        $catalogStore = $this->catalogStore($query);
        $findings = $this->checks->findings([
            SnippetsRequest::STORE => $catalogStore === null ? '' : (string) $catalogStore,
        ]);
        $finding = $this->text($query, self::FINDING);

        return [
            'mode' => in_array($this->text($query, self::MODE), self::MODES, true)
                ? $this->text($query, self::MODE)
                : self::MODES[0],
            'store' => $storeId,
            'page' => $this->firstPage->of($this->text($query, self::PAGE), $this->text($query, self::PATH), $storeId),
            'catalogStore' => $catalogStore,
            'finding' => in_array($finding, array_column($findings['findings'], 'key'), true) ? $finding : null,
            'schedule' => $this->schedule->line(),
            'status' => $this->checks->status(),
            'findings' => $findings,
        ];
    }

    /**
     * @inheritDoc
     */
    public function getLoadingText(): string
    {
        return (string) __('Loading the test…');
    }

    /**
     * @inheritDoc
     */
    public function getFailedText(): string
    {
        return (string) __('The test could not be loaded. Reload the page to try again.');
    }

    /**
     * The store view the page test reads: the one the address names, else the default one
     *
     * @param array<mixed> $query
     * @return int
     * @throws InvalidWizardRequestException Never: the default store view is always one of the shop's
     */
    private function storeId(array $query): int
    {
        try {
            return $this->editorRequest->storeId($query);
        } catch (InvalidWizardRequestException) {
            return $this->editorRequest->storeId([]);
        }
    }

    /**
     * The store view the findings are of: the one the address names, else every store view
     *
     * @param array<mixed> $query
     * @return int|null
     */
    private function catalogStore(array $query): ?int
    {
        try {
            return $this->snippetsRequest->storeId($query);
        } catch (InvalidWizardRequestException) {
            return null;
        }
    }

    /**
     * A parameter of the address as text; empty when it is not text
     *
     * @param array<mixed> $query
     * @param string $name
     * @return string
     */
    private function text(array $query, string $name): string
    {
        $value = $query[$name] ?? '';

        return is_string($value) ? $value : '';
    }
}
