<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Controller\Adminhtml\Snippets;

use Hryvinskyi\RichSnippets\Model\Admin\AdminResources;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\AsksAsTheSnippetsPage;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractBackendController;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Each Snippets endpoint refuses a role without its permission: the list needs the permission to see the module,
 * every write, the export and the import need the permission to change snippets, and the module's other permissions
 * do not stand in for it
 *
 * An endpoint that evaluates a template the caller sends, or answers catalog values for one, needs the permission to
 * change snippets too: the draft check and preview, the sample pages, the placement count, the field catalog with the
 * values of a sample page, and the value suggestions of a condition. Seeing the module still reads what the read-only
 * editor shows: the snippet, the field catalog without values, the builder's configuration, a condition's options and
 * the snippets it can include.
 *
 * The parent class adds the access tests of the list.
 */
#[AppArea('adminhtml'), AppIsolation(true), DbIsolation(true)]
class SnippetEndpointsAccessTest extends AbstractBackendController
{
    use AsksAsTheSnippetsPage;

    private const NO_ACCESS = 403;

    /**
     * @var string
     */
    protected $resource = AdminResources::VIEW;

    /**
     * @var string
     */
    protected $uri = 'backend/richsnippets/snippets/list';

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_GET;

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3?: array<string, string>}>
     */
    public static function endpoints(): array
    {
        $get = HttpRequest::METHOD_GET;
        $post = HttpRequest::METHOD_POST;

        return [
            'the list' => ['backend/richsnippets/snippets/list', $get, AdminResources::VIEW],
            'the switch' => ['backend/richsnippets/snippets/setActive', $post, AdminResources::SNIPPETS],
            'the copy' => ['backend/richsnippets/snippets/duplicate', $post, AdminResources::SNIPPETS],
            'the delete' => ['backend/richsnippets/snippets/delete', $post, AdminResources::SNIPPETS],
            'the export' => ['backend/richsnippets/snippets/export', $get, AdminResources::SNIPPETS],
            'the import preview' => ['backend/richsnippets/snippets/importPreview', $post, AdminResources::SNIPPETS],
            'the import commit' => ['backend/richsnippets/snippets/importCommit', $post, AdminResources::SNIPPETS],
            'the editor load' => ['backend/richsnippets/snippets/load', $get, AdminResources::VIEW],
            'the editor save' => ['backend/richsnippets/snippets/save', $post, AdminResources::SNIPPETS],
            'the draft check' => ['backend/richsnippets/snippets/validate', $post, AdminResources::SNIPPETS],
            'the draft preview' => ['backend/richsnippets/snippets/preview', $post, AdminResources::SNIPPETS],
            'the field catalog' => ['backend/richsnippets/snippets/fields', $get, AdminResources::VIEW],
            'the field catalog with a sample page\'s values' => [
                'backend/richsnippets/snippets/fields',
                $get,
                AdminResources::SNIPPETS,
                ['sample' => 'product:987654'],
            ],
            'the builder configuration' => ['backend/richsnippets/snippets/builderConfig', $get, AdminResources::VIEW],
            'the condition options' => [
                'backend/richsnippets/snippets/conditionOptions',
                $get,
                AdminResources::VIEW,
                ['attribute' => 'color'],
            ],
            'the condition value suggestions' => [
                'backend/richsnippets/snippets/conditionOptions',
                $get,
                AdminResources::SNIPPETS,
                ['attribute' => 'cost', 'q' => '12'],
            ],
            'the placement count' => ['backend/richsnippets/snippets/placementCount', $post, AdminResources::SNIPPETS],
            'the sample pages' => ['backend/richsnippets/snippets/samples', $post, AdminResources::SNIPPETS],
            'the include candidates' => [
                'backend/richsnippets/snippets/includeCandidates',
                $get,
                AdminResources::VIEW,
            ],
        ];
    }

    /**
     * The endpoints the read-only editor of an admin who may only see the module reads
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3?: array<string, string>}>
     */
    public static function viewOnlyReads(): array
    {
        return array_filter(
            self::endpoints(),
            static fn (array $endpoint): bool => $endpoint[2] === AdminResources::VIEW
        );
    }

    /**
     * @param string $path
     * @param string $method
     * @param string $resource
     * @param array<string, string> $query
     * @return void
     */
    #[DataProvider('endpoints')]
    public function testARoleWithoutTheEndpointsPermissionIsRefused(
        string $path,
        string $method,
        string $resource,
        array $query = []
    ): void {
        $this->deny($resource);
        $this->ask($path, $method, $query, ['id' => 1]);

        self::assertSame(self::NO_ACCESS, $this->httpResponse()->getHttpResponseCode());
    }

    /**
     * @param string $path
     * @param string $method
     * @param string $resource
     * @param array<string, string> $query
     * @return void
     */
    #[DataProvider('endpoints')]
    public function testARoleWithTheEndpointsPermissionIsLetThrough(
        string $path,
        string $method,
        string $resource,
        array $query = []
    ): void {
        $this->deny(AdminResources::TEST);
        $this->ask($path, $method, $query, ['id' => 987654]);

        self::assertNotSame(self::NO_ACCESS, $this->httpResponse()->getHttpResponseCode(), $resource);
        self::assertNotSame(404, $this->httpResponse()->getHttpResponseCode(), $path);
    }

    /**
     * @param string $path
     * @param string $method
     * @param string $resource
     * @param array<string, string> $query
     * @return void
     */
    #[DataProvider('viewOnlyReads')]
    public function testARoleThatMayOnlySeeTheModuleReadsWhatTheReadOnlyEditorShows(
        string $path,
        string $method,
        string $resource,
        array $query = []
    ): void {
        $this->deny(AdminResources::SNIPPETS);
        $this->deny(AdminResources::TEST);
        $this->ask($path, $method, $query, ['id' => 987654]);

        self::assertNotSame(self::NO_ACCESS, $this->httpResponse()->getHttpResponseCode(), $resource);
        self::assertNotSame(404, $this->httpResponse()->getHttpResponseCode(), $path);
    }

    /**
     * Ask an endpoint as the island does: a GET with its query, or a POST with a JSON body
     *
     * @param string $path
     * @param string $method
     * @param array<string, string> $query
     * @param array<string, int> $body sent with a POST
     * @return void
     */
    private function ask(string $path, string $method, array $query, array $body): void
    {
        $this->newExchange();
        $this->askAsTheBrowser($method, $method === HttpRequest::METHOD_POST ? $body : '');
        foreach ($query as $name => $value) {
            $this->httpRequest()->setQueryValue($name, $value);
        }

        $this->follow($path);
    }
}
