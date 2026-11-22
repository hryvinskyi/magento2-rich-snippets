<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Controller\Adminhtml\Library;

use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Admin\AdminResources;
use Hryvinskyi\RichSnippets\Model\Cache\Type\RichSnippetsCache;
use Hryvinskyi\RichSnippets\Model\ResourceModel\Snippet as SnippetResource;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\AsksAsTheSnippetsPage;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The Library page's endpoints through the real admin, as the island asks them
 *
 * The list and the suggested answers are read with the view permission alone, and have the keys of the contract's
 * examples; a template the library does not have answers 400. An add stores a switched-off copy with the building
 * blocks it uses and answers what it added with the library after it; repeated with its key it adds nothing more and
 * answers the first add, and a new key adds the next numbered copy. The recommended set adds every template of it
 * once. A key of another shape answers 400, and a write without the form key is refused before it runs. The parent
 * class adds the access tests for the permission to change snippets, on the add.
 */
#[AppArea('adminhtml'), AppIsolation(true), DbIsolation(true)]
class LibraryEndpointsTest extends AbstractBackendController
{
    use AsksAsTheSnippetsPage;

    private const LIST = 'backend/richsnippets/library/list';
    private const SUGGESTIONS = 'backend/richsnippets/library/suggestions';
    private const ADD = 'backend/richsnippets/library/add';
    private const ADD_RECOMMENDED = 'backend/richsnippets/library/addRecommended';
    private const CONTRACTS = __DIR__ . '/../../../../_contract/admin-pages/';

    /**
     * @var string
     */
    protected $resource = AdminResources::SNIPPETS;

    /**
     * @var string
     */
    protected $uri = self::ADD;

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_POST;

    /**
     * Start with no snippet and send requests the way the browser does, the parent's access tests included
     *
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $resource = $this->testObjectManager()->get(ResourceConnection::class);
        self::assertInstanceOf(ResourceConnection::class, $resource);
        $resource->getConnection()->delete($resource->getTableName(SnippetResource::TABLE));
        $this->cache()->clean();
        $this->askAsTheBrowser(
            HttpRequest::METHOD_POST,
            ['template' => 'faq', 'answers' => [], 'idempotencyKey' => 'rs-lib-acl']
        );
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->cache()->clean();
        parent::tearDown();
    }

    public function testTheLibraryAndTheSuggestedAnswersAreReadWithTheViewPermissionAloneInTheContractsShape(): void
    {
        $this->deny(AdminResources::SNIPPETS);

        $library = $this->read(self::LIST);
        $answers = $this->read(self::SUGGESTIONS, ['template' => 'product']);

        self::assertCount(13, $this->listAt($library, 'templates'));
        $this->assertSameShape($this->contract('library-list.json')['first'] ?? null, $library);
        self::assertSame('product', $answers['template'] ?? null);
        self::assertSame(
            ['brand', 'gtin', 'description'],
            array_column($this->listAt($answers, 'questions'), 'question')
        );
        $this->assertSameShape($this->contract('library-suggestions.json')['product'] ?? null, $answers);
    }

    public function testTheSuggestedAnswersOfATemplateTheLibraryDoesNotHaveAnswer400(): void
    {
        self::assertSame(
            ['error' => 'template_unknown'],
            $this->read(self::SUGGESTIONS, ['template' => 'recipe'], 400)
        );
    }

    public function testAnAddStoresASwitchedOffCopyWithItsBlocksAndAnswersTheLibraryAfterIt(): void
    {
        $answer = $this->post(
            self::ADD,
            ['template' => 'organization', 'answers' => [], 'idempotencyKey' => 'rs-lib-1']
        );

        self::assertArrayHasKey('refused', $answer);
        self::assertNull($answer['refused']);
        $added = $this->listAt($answer, 'added');
        self::assertSame(['organization'], array_column(
            array_values(array_filter($added, static fn (array $one): bool => ($one['isBlock'] ?? true) === false)),
            'template'
        ));
        self::assertContains('postal-address', array_column($added, 'template'));
        $copy = $this->stored($this->idOf($added, 'organization'));
        self::assertSame('Organization', $copy->getName());
        self::assertFalse($copy->isActive());
        self::assertTrue($this->templateOf($answer, 'organization')['added'] ?? null);
        self::assertSame('Organization 2', $this->templateOf($answer, 'organization')['nextName'] ?? null);
        $this->assertSameShape($this->contract('library-add.json')['added'] ?? null, $answer);
    }

    public function testAnAddRepeatedWithItsKeyAddsNothingMoreAndANewKeyAddsTheNextCopy(): void
    {
        $body = ['template' => 'faq', 'answers' => [], 'idempotencyKey' => 'rs-lib-2'];

        $first = $this->post(self::ADD, $body);
        $replay = $this->post(self::ADD, $body);
        $second = $this->post(self::ADD, ['idempotencyKey' => 'rs-lib-3'] + $body);

        self::assertSame($this->listAt($first, 'added'), $this->listAt($replay, 'added'));
        self::assertCount(2, $this->repository()->list());
        self::assertSame('FAQ 2', $this->stored($this->idOf($this->listAt($second, 'added'), 'faq'))->getName());
    }

    public function testTheRecommendedSetAddsEachOfItsTemplatesOnceAndARepeatAddsNothingMore(): void
    {
        $first = $this->post(self::ADD_RECOMMENDED, ['idempotencyKey' => 'rs-lib-4']);
        $count = count($this->repository()->list());
        $replay = $this->post(self::ADD_RECOMMENDED, ['idempotencyKey' => 'rs-lib-4']);

        $copies = array_values(array_filter(
            $this->listAt($first, 'added'),
            static fn (array $one): bool => ($one['isBlock'] ?? true) === false
        ));
        self::assertSame(
            ['organization', 'website-name', 'breadcrumbs', 'product', 'product-variants'],
            array_column($copies, 'template')
        );
        self::assertSame($count, count($this->repository()->list()));
        self::assertSame($this->listAt($first, 'added'), $this->listAt($replay, 'added'));
        $this->assertSameShape($this->contract('library-add.json')['recommended'] ?? null, $first);
    }

    public function testAnUnknownTemplateABodyOfAnotherShapeAndAKeyOfAnotherShapeAnswer400(): void
    {
        self::assertSame(
            ['error' => 'template_unknown'],
            $this->post(self::ADD, ['template' => 'recipe', 'answers' => [], 'idempotencyKey' => 'rs-lib-5'], 400)
        );
        self::assertSame(
            ['error' => 'template'],
            $this->post(self::ADD, ['answers' => [], 'idempotencyKey' => 'rs-lib-6'], 400)
        );
        self::assertSame(
            ['error' => 'key_invalid'],
            $this->post(self::ADD, ['template' => 'faq', 'answers' => [], 'idempotencyKey' => 'no spaces'], 400)
        );
        self::assertSame([], $this->repository()->list());
    }

    public function testAWriteWithoutTheFormKeyIsRefusedBeforeItRuns(): void
    {
        $this->assertPostWithoutFormKeyIsRefused(
            self::ADD,
            ['template' => 'faq', 'answers' => [], 'idempotencyKey' => 'rs-lib-7']
        );
        $this->assertPostWithoutFormKeyIsRefused(self::ADD_RECOMMENDED, ['idempotencyKey' => 'rs-lib-8']);

        self::assertSame([], $this->repository()->list());
    }

    /**
     * The ID of the snippet an add made for a template
     *
     * @param list<array<mixed>> $added
     * @param string $template
     * @return int
     */
    private function idOf(array $added, string $template): int
    {
        foreach ($added as $one) {
            if (($one['template'] ?? null) === $template && ($one['reused'] ?? true) === false) {
                self::assertIsInt($one['id'] ?? null);

                return $one['id'];
            }
        }
        self::fail('The add made no copy of ' . $template);
    }

    /**
     * One template of the library an answer carries
     *
     * @param array<mixed> $answer
     * @param string $code
     * @return array<mixed>
     */
    private function templateOf(array $answer, string $code): array
    {
        foreach ($this->listAt($answer, 'library', 'templates') as $template) {
            if (($template['code'] ?? null) === $code) {
                return $template;
            }
        }
        self::fail('The library holds no ' . $code);
    }

    /**
     * Whether an answer has the keys of the contract's example at every level, lists compared by their first item
     *
     * @param mixed $contract
     * @param mixed $answer
     * @param string $path
     * @return void
     */
    private function assertSameShape(mixed $contract, mixed $answer, string $path = '$'): void
    {
        if (!is_array($contract) || $contract === [] || $answer === null) {
            return;
        }
        self::assertIsArray($answer, $path);
        if (array_is_list($contract)) {
            if ($answer !== []) {
                self::assertTrue(array_is_list($answer), $path . ' is a list.');
                $this->assertSameShape($contract[0], $answer[0], $path . '[0]');
            }

            return;
        }
        self::assertSame(array_keys($contract), array_keys($answer), $path);
        foreach ($contract as $key => $value) {
            $this->assertSameShape($value, $answer[$key], $path . '.' . $key);
        }
    }

    /**
     * A contract file of the admin pages, decoded
     *
     * @param string $file
     * @return array<mixed>
     */
    private function contract(string $file): array
    {
        $contract = json_decode((string) file_get_contents(self::CONTRACTS . $file), true);
        self::assertIsArray($contract);

        return $contract;
    }

    /**
     * The list at a path of names
     *
     * @param mixed $document
     * @param string|int ...$path
     * @return list<array<mixed>>
     */
    private function listAt(mixed $document, string|int ...$path): array
    {
        $value = $this->at($document, ...$path);
        self::assertIsArray($value, implode('.', $path));
        $items = [];
        foreach ($value as $item) {
            self::assertIsArray($item);
            $items[] = $item;
        }

        return $items;
    }

    /**
     * A snippet as stored
     *
     * @param int $id
     * @return SnippetInterface
     */
    private function stored(int $id): SnippetInterface
    {
        return $this->repository()->get($id);
    }

    /**
     * @return SnippetRepositoryInterface
     */
    private function repository(): SnippetRepositoryInterface
    {
        $repository = $this->testObjectManager()->get(SnippetRepositoryInterface::class);
        self::assertInstanceOf(SnippetRepositoryInterface::class, $repository);

        return $repository;
    }

    /**
     * The module's cache
     *
     * @return RichSnippetsCache
     */
    private function cache(): RichSnippetsCache
    {
        $cache = $this->testObjectManager()->get(RichSnippetsCache::class);
        self::assertInstanceOf(RichSnippetsCache::class, $cache);

        return $cache;
    }
}
