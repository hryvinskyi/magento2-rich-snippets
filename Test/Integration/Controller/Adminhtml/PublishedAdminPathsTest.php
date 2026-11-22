<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Controller\Adminhtml;

use Hryvinskyi\RichSnippets\Controller\Adminhtml\Library\Add as LibraryAdd;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Library\AddRecommended as LibraryAddRecommended;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Library\Index as LibraryPage;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Library\ListAction as LibraryList;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Library\Suggestions as LibrarySuggestions;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Overview\Data as OverviewData;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Overview\Index as OverviewPage;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\BuilderConfig;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\ConditionOptions;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\Delete;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\Duplicate;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\Edit;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\Export;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\Fields;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\ImportCommit;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\ImportPreview;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\IncludeCandidates;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\Index as SnippetsPage;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\ListAction;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\Load;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\NewAction;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\PlacementCount;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\Preview;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\Samples;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\Save;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\SetActive;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\Validate;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Test\CheckRequest;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Test\CheckStatus;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Test\FindingItems;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Test\Findings;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Test\FindingsCsv;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Test\Index as TestPage;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Test\Run;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Test\Search;
use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Backend\Model\Url;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Route\ConfigInterface as RouteConfigInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every admin path the module publishes builds an address whose secret key the request for that address accepts
 *
 * The admin builds the key from the first part of the path it is given, and checks it against the route id the
 * router gives the request, so a path written with the route's front name opens but is refused, sending the person
 * to the dashboard. Each address is built with secret keys on, its front name, controller and action are read back,
 * the front name is resolved to its route id as the router does, and the address's key is compared with the key the
 * admin expects for that request. A path written with the front name is shown to fail the same comparison.
 */
#[AppArea('adminhtml')]
class PublishedAdminPathsTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function publishedPaths(): array
    {
        return [
            'the overview page' => [OverviewPage::ROUTE],
            'the overview' => [OverviewData::ROUTE],
            'the snippets page' => [SnippetsPage::ROUTE],
            'the snippet list' => [ListAction::ROUTE],
            'the switch' => [SetActive::ROUTE],
            'the copy' => [Duplicate::ROUTE],
            'the delete' => [Delete::ROUTE],
            'the export' => [Export::ROUTE],
            'the import preview' => [ImportPreview::ROUTE],
            'the import commit' => [ImportCommit::ROUTE],
            'the editor of a snippet' => [Edit::ROUTE],
            'the editor of a new snippet' => [NewAction::ROUTE],
            'the editor load' => [Load::ROUTE],
            'the editor save' => [Save::ROUTE],
            'the draft check' => [Validate::ROUTE],
            'the draft preview' => [Preview::ROUTE],
            'the field catalog' => [Fields::ROUTE],
            'the builder configuration' => [BuilderConfig::ROUTE],
            'the condition options' => [ConditionOptions::ROUTE],
            'the placement count' => [PlacementCount::ROUTE],
            'the sample pages' => [Samples::ROUTE],
            'the include candidates' => [IncludeCandidates::ROUTE],
            'the test page' => [TestPage::ROUTE],
            'the page search' => [Search::ROUTE],
            'the page test' => [Run::ROUTE],
            'the catalog check status' => [CheckStatus::ROUTE],
            'the catalog check request' => [CheckRequest::ROUTE],
            'the catalog findings' => [Findings::ROUTE],
            'the pages of a finding' => [FindingItems::ROUTE],
            'the finding download' => [FindingsCsv::ROUTE],
            'the library page' => [LibraryPage::ROUTE],
            'the library' => [LibraryList::ROUTE],
            'the suggested answers of a template' => [LibrarySuggestions::ROUTE],
            'the add of a template' => [LibraryAdd::ROUTE],
            'the add of the recommended set' => [LibraryAddRecommended::ROUTE],
        ];
    }

    #[DataProvider('publishedPaths')]
    #[Config(Url::XML_PATH_USE_SECURE_KEY, '1')]
    public function testThePathBuildsAnAddressWhoseKeyItsRequestAccepts(string $route): void
    {
        [$built, $expected] = $this->keys($route);

        self::assertSame($expected, $built, sprintf('The secret key of "%s" is refused by its own request.', $route));
    }

    #[Config(Url::XML_PATH_USE_SECURE_KEY, '1')]
    public function testAPathWrittenWithTheFrontNameFailsTheCheck(): void
    {
        [$routeId, $controller, $action] = explode('/', ListAction::ROUTE);
        $frontName = $this->routes()->getRouteFrontName($routeId, 'adminhtml');
        self::assertNotSame($routeId, $frontName, 'The module\'s front name differs from its route id.');

        [$built, $expected] = $this->keys($frontName . '/' . $controller . '/' . $action);

        self::assertNotSame($expected, $built);
    }

    /**
     * The secret key of the address a path builds, and the key the request for that address is checked against
     *
     * @param string $route `route/controller/action`
     * @return array{0: string, 1: string}
     */
    private function keys(string $route): array
    {
        $url = Bootstrap::getObjectManager()->create(Url::class);
        self::assertInstanceOf(Url::class, $url);
        self::assertTrue($url->useSecretKey(), 'Secret keys are on.');
        $address = $url->getUrl($route);
        $resolver = Bootstrap::getObjectManager()->get(FrontNameResolver::class);
        self::assertInstanceOf(FrontNameResolver::class, $resolver);
        $parts = array_values(array_filter(
            explode('/', (string) parse_url($address, PHP_URL_PATH)),
            static fn (string $part): bool => $part !== ''
        ));
        $start = array_search((string) $resolver->getFrontName(), $parts, true);
        self::assertIsInt($start, sprintf('"%s" is an admin address.', $address));
        [$frontName, $controller, $action] = array_slice($parts, $start + 1, 3) + ['', '', ''];
        $params = array_slice($parts, $start + 4);
        $key = '';
        $count = count($params);
        for ($index = 0; $index + 1 < $count; $index += 2) {
            if ($params[$index] === UrlInterface::SECRET_KEY_PARAM_NAME) {
                $key = $params[$index + 1];
            }
        }
        self::assertNotSame('', $key, sprintf('"%s" carries a secret key.', $address));
        $routeId = $this->routes()->getRouteByFrontName($frontName, 'adminhtml');
        self::assertNotSame('', $routeId, sprintf('The front name "%s" belongs to an admin route.', $frontName));

        return [$key, $url->getSecretKey((string) $routeId, $controller, $action)];
    }

    /**
     * @return RouteConfigInterface
     */
    private function routes(): RouteConfigInterface
    {
        $routes = Bootstrap::getObjectManager()->get(RouteConfigInterface::class);
        self::assertInstanceOf(RouteConfigInterface::class, $routes);

        return $routes;
    }
}
