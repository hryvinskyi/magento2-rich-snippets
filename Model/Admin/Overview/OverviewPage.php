<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Overview;

use Hryvinskyi\AdminUi\Api\Page\KeyedAdminUrlInterface;
use Hryvinskyi\AdminUi\Api\Page\PageContentInterface;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Overview\Data;

/**
 * What the Overview page hands its island: the Overview of every store view as its endpoint answers it, so the page
 * draws without a round trip
 *
 * The one endpoint is `data` (`?store=<id>`), answering the same for one store view. The page links to the Test
 * page, the snippet list, the editor of a new snippet, the library and the module's settings, each drawn only for an
 * admin who may open it.
 */
class OverviewPage implements PageContentInterface
{
    /** The screens the Overview links to, by route path */
    public const ROUTES = [
        'hryvinskyi_richsnippets/test/index',
        'hryvinskyi_richsnippets/snippets/index',
        'hryvinskyi_richsnippets/snippets/new',
        'hryvinskyi_richsnippets/library/index',
        'adminhtml/system_config/edit',
    ];

    /**
     * @param OverviewAnswers $answers
     * @param KeyedAdminUrlInterface $urls
     */
    public function __construct(
        private readonly OverviewAnswers $answers,
        private readonly KeyedAdminUrlInterface $urls
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getUrls(): array
    {
        return ['data' => $this->urls->getUrl(Data::ROUTE)];
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
     * @throws \Hryvinskyi\AdminSetupWizard\Api\Exception\InvalidWizardRequestException Never for every store view
     */
    public function getData(): array
    {
        return $this->answers->read([]);
    }

    /**
     * @inheritDoc
     */
    public function getLoadingText(): string
    {
        return (string) __('Loading the overview…');
    }

    /**
     * @inheritDoc
     */
    public function getFailedText(): string
    {
        return (string) __('The overview could not be loaded. Reload the page to try again.');
    }
}
