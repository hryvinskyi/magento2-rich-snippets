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
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\ReadsTheEditorPage;
use Magento\Backend\Model\Url;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The editor of a new snippet, rendered by the real admin: the same island and endpoints as the editor of a stored
 * snippet, carrying a blank snippet — no id, no name, no type, switched off, set for product pages on every store
 * view, its template only the schema.org context — whatever id the address names
 *
 * The parent class adds the access tests for the module's right to change snippets.
 */
#[AppArea('adminhtml'), AppIsolation(true), DbIsolation(true)]
class NewSnippetPageTest extends AbstractBackendController
{
    use AsksAsTheSnippetsPage;
    use ReadsTheEditorPage;

    private const PAGE = 'backend/richsnippets/snippets/new';

    /**
     * @var string
     */
    protected $resource = AdminResources::SNIPPETS;

    /**
     * @var string
     */
    protected $uri = self::PAGE;

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_GET;

    #[Config(Url::XML_PATH_USE_SECURE_KEY, '1')]
    public function testThePageCarriesABlankSnippet(): void
    {
        $page = $this->openEditor(self::PAGE);

        self::assertSame('Snippets', $this->at($page, 'shell', 'page'));
        self::assertTrue($this->currentTabs($page)['snippets'] ?? false);
        self::assertMatchesRegularExpression(
            '#/richsnippets/snippets/save/key/[0-9a-f]+/#',
            $this->textAt($page, 'urls', 'save')
        );
        self::assertTrue($this->at($page, 'data', 'isNew'));
        self::assertSame([
            'id' => null,
            'code' => '',
            'name' => '',
            'type' => '',
            'template' => "{\n  \"@context\": \"https://schema.org\"\n}",
            'isBlock' => false,
            'active' => false,
            'pageKinds' => ['product'],
            'conditions' => '',
            'storeIds' => [0],
            'origin' => null,
            'updatedAt' => null,
            'updatedBy' => '',
        ], $this->at($page, 'data', 'snippet'));
        self::assertSame([], $this->at($page, 'data', 'usedIn'));
        self::assertSame($this->contractKeys(), array_keys((array) $this->at($page, 'phrases')));
    }

    public function testAnIdInTheAddressStillOpensABlankSnippet(): void
    {
        $page = $this->openEditor(self::PAGE . '/id/1');

        self::assertTrue($this->at($page, 'data', 'isNew'));
        self::assertNull($this->at($page, 'data', 'snippet', 'id'));
    }
}
