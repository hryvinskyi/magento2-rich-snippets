<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Controller\Adminhtml\Snippets;

use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Admin\AdminResources;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\AsksAsTheSnippetsPage;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\StoresTestSnippets;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The Snippets page's writes on one snippet through the real admin, as the island sends them
 *
 * The switch takes the state wanted, so a repeat changes nothing more, and a building block cannot be switched on; a
 * copy is named "(copy)", placed like the original and switched off, and a copy repeated with its key creates one
 * snippet; a delete removes a snippet nothing includes and is refused, naming them, for one others include. Every
 * answer is the list after the write. A write without the form key is refused before it runs. The parent class
 * adds the access tests for the permission to change snippets, on the switch.
 */
#[AppArea('adminhtml'), AppIsolation(true), DbIsolation(true)]
class SnippetWritesEndpointTest extends AbstractBackendController
{
    use AsksAsTheSnippetsPage;
    use StoresTestSnippets;

    private const SET_ACTIVE = 'backend/richsnippets/snippets/setActive';
    private const DUPLICATE = 'backend/richsnippets/snippets/duplicate';
    private const DELETE = 'backend/richsnippets/snippets/delete';

    /**
     * @var string
     */
    protected $resource = AdminResources::SNIPPETS;

    /**
     * @var string
     */
    protected $uri = self::SET_ACTIVE;

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_POST;

    /**
     * Send requests the way the browser does, the parent's access tests included
     *
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->askAsTheBrowser(HttpRequest::METHOD_POST, ['id' => 987654, 'active' => true]);
    }

    public function testTheSwitchTakesTheStateWantedAndARepeatChangesNothingMore(): void
    {
        $id = $this->storedPage('rs-switch')->getId();

        $on = $this->post(self::SET_ACTIVE, ['id' => $id, 'active' => true]);
        $again = $this->post(self::SET_ACTIVE, ['id' => $id, 'active' => true]);

        self::assertNull($on['refused']);
        self::assertSame($id, $on['id']);
        self::assertNull($again['refused']);
        self::assertTrue($this->stored((int) $id)->isActive());
        self::assertTrue($this->rowOf($again, 'rs-switch')['active'] ?? null);

        $this->post(self::SET_ACTIVE, ['id' => $id, 'active' => false]);

        self::assertFalse($this->stored((int) $id)->isActive());
    }

    public function testABuildingBlockIsNotSwitchedOn(): void
    {
        $id = $this->storedBlock('rs-block')->getId();

        $answer = $this->post(self::SET_ACTIVE, ['id' => $id, 'active' => true]);

        self::assertSame('block_cannot_be_switched_on', $this->at($answer, 'refused', 'reason'));
        self::assertNull($answer['id']);
        self::assertFalse($this->stored((int) $id)->isActive());
    }

    public function testASwitchOfASnippetThatIsGoneIsRefusedAsUnknownAndABadBodyAnswers400(): void
    {
        $gone = $this->post(self::SET_ACTIVE, ['id' => 987654, 'active' => true]);

        self::assertSame('unknown_snippet', $this->at($gone, 'refused', 'reason'));
        self::assertSame(['error' => 'active'], $this->post(self::SET_ACTIVE, ['id' => 1, 'active' => 'yes'], 400));
        self::assertSame(['error' => 'id'], $this->post(self::SET_ACTIVE, ['active' => true], 400));
    }

    public function testACopyIsNamedAsACopyPlacedLikeTheOriginalAndSwitchedOff(): void
    {
        $original = $this->storedPage('rs-original', true);

        $answer = $this->post(self::DUPLICATE, ['id' => $original->getId(), 'idempotencyKey' => 'rs-copy-1']);

        self::assertNull($answer['refused']);
        $copyId = $answer['id'];
        self::assertIsInt($copyId);
        $copy = $this->stored($copyId);
        self::assertSame('Original (copy)', $copy->getName());
        self::assertNotSame($original->getCode(), $copy->getCode());
        self::assertFalse($copy->isActive());
        self::assertSame($original->getTemplate(), $copy->getTemplate());
        self::assertSame($original->getPageKinds(), $copy->getPageKinds());
        self::assertEquals($original->getConditions(), $copy->getConditions());
        self::assertSame($original->getSortOrder(), $copy->getSortOrder());
        self::assertNotNull($this->rowOf($answer, $copy->getCode()));
    }

    public function testACopyRepeatedWithItsKeyCreatesOneSnippetAndAnswersTheFirst(): void
    {
        $original = $this->storedPage('rs-original');
        $before = count($this->repository()->list());

        $first = $this->post(self::DUPLICATE, ['id' => $original->getId(), 'idempotencyKey' => 'rs-copy-2']);
        $replay = $this->post(self::DUPLICATE, ['id' => $original->getId(), 'idempotencyKey' => 'rs-copy-2']);
        $second = $this->post(self::DUPLICATE, ['id' => $original->getId(), 'idempotencyKey' => 'rs-copy-3']);

        self::assertSame($first['id'], $replay['id']);
        self::assertSame($before + 2, count($this->repository()->list()));
        self::assertIsInt($second['id']);
        self::assertSame('Original (copy) 2', $this->stored($second['id'])->getName());
    }

    public function testAKeyOfAnotherShapeAnswers400(): void
    {
        $original = $this->storedPage('rs-original');

        $answer = $this->post(self::DUPLICATE, ['id' => $original->getId(), 'idempotencyKey' => 'no spaces'], 400);

        self::assertSame(['error' => 'key_invalid'], $answer);
    }

    public function testADeleteRemovesASnippetNothingIncludes(): void
    {
        $id = (int) $this->storedPage('rs-gone')->getId();

        $answer = $this->post(self::DELETE, ['id' => $id]);

        self::assertNull($answer['refused']);
        self::assertNull($answer['id']);
        self::assertNull($this->rowOf($answer, 'rs-gone'));
        $this->expectException(NoSuchEntityException::class);
        $this->repository()->get($id);
    }

    public function testADeleteOfAnIncludedSnippetIsRefusedNamingTheSnippetsThatIncludeIt(): void
    {
        $block = $this->storedBlock('rs-policy');
        $this->storeSnippet('rs-org', 'Organization', [
            '@type' => 'OnlineStore',
            'hasMerchantReturnPolicy' => ['@include' => 'rs-policy'],
        ], ['kinds' => ['home']]);
        $this->storeSnippet('rs-item', 'Product', [
            '@type' => 'Product',
            'hasMerchantReturnPolicy' => ['@include' => 'rs-policy'],
        ], ['kinds' => ['product']]);

        $answer = $this->post(self::DELETE, ['id' => $block->getId()]);

        self::assertSame('in_use', $this->at($answer, 'refused', 'reason'));
        self::assertSame(
            [['code' => 'rs-item', 'name' => 'Product'], ['code' => 'rs-org', 'name' => 'Organization']],
            $this->at($answer, 'refused', 'includers')
        );
        self::assertSame('rs-policy', $this->stored((int) $block->getId())->getCode());
    }

    public function testAWriteWithoutTheFormKeyIsRefusedBeforeItRuns(): void
    {
        $id = (int) $this->storedPage('rs-kept')->getId();

        $this->assertPostWithoutFormKeyIsRefused(self::DELETE, ['id' => $id]);

        self::assertSame('rs-kept', $this->stored($id)->getCode());
    }

    /**
     * Store a page snippet for product pages where the product is not configurable
     *
     * @param string $code
     * @param bool $active
     * @return SnippetInterface
     */
    private function storedPage(string $code, bool $active = false): SnippetInterface
    {
        return $this->storeSnippet($code, 'Original', [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => '{{product.name}}',
        ], [
            'kinds' => ['product'],
            'conditions' => [['product_type', '!=', 'configurable']],
            'active' => $active,
            'sortOrder' => 7,
        ]);
    }

    /**
     * Store a building block
     *
     * @param string $code
     * @return SnippetInterface
     */
    private function storedBlock(string $code): SnippetInterface
    {
        return $this->storeSnippet($code, 'Return policy', [
            '@type' => 'MerchantReturnPolicy',
            'merchantReturnDays' => 30,
        ], ['block' => true]);
    }

    /**
     * The row of a code in an answer's list; null when it is not listed
     *
     * @param array<mixed> $answer
     * @param string $code
     * @return array<mixed>|null
     */
    private function rowOf(array $answer, string $code): ?array
    {
        foreach ((array) ($answer['rows'] ?? []) as $row) {
            if (is_array($row) && ($row['code'] ?? null) === $code) {
                return $row;
            }
        }

        return null;
    }

    /**
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
}
