<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Library;

use Hryvinskyi\AdminUi\Api\Page\KeyedAdminUrlInterface;
use Hryvinskyi\AdminUi\Api\Page\PageContentInterface;
use Hryvinskyi\RichSnippets\Api\Exception\MalformedLibraryTemplateException;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Library\Add;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Library\AddRecommended;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Library\ListAction;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Library\Suggestions;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * What the Library page hands its island: the library as its list endpoint answers it, so the page draws without a
 * round trip, and the template whose add drawer opens first
 *
 * The data is the library (see LibraryListing) with `"open"`: the code of the template the address names
 * (`?template=<code>`, as the Overview links to a template with a newer version), or null when it names none the
 * library has; and `"unavailable"`: true when a template definition is malformed, so the library could not be read
 * — the page is still drawn, with no template, says the library could not be loaded and offers to read it again,
 * and the fault is logged. The endpoints are the list, the suggested answers of a template, the add and the add of the
 * recommended set. The page links to a new snippet, to the editor of the copy an add made and to the page test, each
 * drawn only for an admin who may open it.
 *
 * @phpstan-import-type Template from LibraryListing
 */
class LibraryPage implements PageContentInterface
{
    /** The screens the library links to, by route path */
    public const ROUTES = [
        'hryvinskyi_richsnippets/snippets/new',
        'hryvinskyi_richsnippets/snippets/edit',
        'hryvinskyi_richsnippets/test/index',
    ];

    /**
     * @param LibraryListing $listing
     * @param RequestInterface $request
     * @param KeyedAdminUrlInterface $urls
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly LibraryListing $listing,
        private readonly RequestInterface $request,
        private readonly KeyedAdminUrlInterface $urls,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getUrls(): array
    {
        return [
            'list' => $this->urls->getUrl(ListAction::ROUTE),
            'suggestions' => $this->urls->getUrl(Suggestions::ROUTE),
            'add' => $this->urls->getUrl(Add::ROUTE),
            'addRecommended' => $this->urls->getUrl(AddRecommended::ROUTE),
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
     * @return array{groups: list<array{code: string, label: string}>, recommended: list<string>,
     *     templates: list<Template>, open: string|null, unavailable: bool}
     */
    public function getData(): array
    {
        try {
            $library = $this->listing->read();
        } catch (MalformedLibraryTemplateException $e) {
            $this->logger->error('The library could not be read for the Library page.', ['exception' => $e]);

            return ['groups' => [], 'recommended' => [], 'templates' => [], 'open' => null, 'unavailable' => true];
        }
        $asked = $this->request->getParam(LibraryRequest::TEMPLATE);
        $open = null;
        foreach ($library['templates'] as $template) {
            if ($template['code'] === $asked) {
                $open = $template['code'];
            }
        }

        return $library + ['open' => $open, 'unavailable' => false];
    }

    /**
     * @inheritDoc
     */
    public function getLoadingText(): string
    {
        return (string) __('Loading the library…');
    }

    /**
     * @inheritDoc
     */
    public function getFailedText(): string
    {
        return (string) __('The library could not be loaded. Reload the page to try again.');
    }
}
