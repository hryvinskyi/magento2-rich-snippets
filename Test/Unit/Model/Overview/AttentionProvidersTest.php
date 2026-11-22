<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Overview;

use Hryvinskyi\RichSnippets\Api\AttentionItemProviderInterface;
use Hryvinskyi\RichSnippets\Api\CatalogCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\AttentionItemInterface;
use Hryvinskyi\RichSnippets\Api\Data\AttentionItemInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\AttentionTargetInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\AttentionTone;
use Hryvinskyi\RichSnippets\Api\Data\LibraryEntryInterface;
use Hryvinskyi\RichSnippets\Api\Data\LibraryTemplateInterface;
use Hryvinskyi\RichSnippets\Api\Data\OriginInterface;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;
use Hryvinskyi\RichSnippets\Api\Data\SetupState;
use Hryvinskyi\RichSnippets\Api\Data\ThemeMarkupState;
use Hryvinskyi\RichSnippets\Api\LibraryInterface;
use Hryvinskyi\RichSnippets\Model\Check\Finding;
use Hryvinskyi\RichSnippets\Model\Overview\Attention\FindingAttention;
use Hryvinskyi\RichSnippets\Model\Overview\Attention\NewerTemplateAttention;
use Hryvinskyi\RichSnippets\Model\Overview\Attention\ThemeMarkupAttention;
use Hryvinskyi\RichSnippets\Model\Overview\AttentionItem;
use Hryvinskyi\RichSnippets\Model\Overview\AttentionItems;
use Hryvinskyi\RichSnippets\Model\Overview\AttentionScope;
use Hryvinskyi\RichSnippets\Model\Overview\AttentionTarget;
use Hryvinskyi\RichSnippets\Model\Snippet;
use Hryvinskyi\RichSnippets\Model\Snippet\Origin;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\BuildsCheckWording;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\PlacementFixture;
use PHPUnit\Framework\TestCase;

/**
 * Each provider of the Overview's "Needs attention" list lists its sort of thing only where it applies, and the list
 * asks the providers in the pool's order
 */
class AttentionProvidersTest extends TestCase
{
    use BuildsCheckWording;

    private const STORE = 2;

    public function testTheInvalidPagesAreTheBadFindingsOfTheLastCheckEachOpeningItsPages(): void
    {
        $provider = $this->findings('bad', 'bad');

        $items = $provider->items($this->scope(SetupState::Live, self::STORE));

        self::assertCount(1, $items);
        self::assertSame('finding-merchant-listing-image', $items[0]->getKey());
        self::assertSame(AttentionTone::Bad, $items[0]->getTone());
        self::assertSame('No image on 2 products', $this->title($items[0]));
        self::assertSame('See them', $items[0]->getActionLabel());
        self::assertSame('hryvinskyi_richsnippets/test/index', $items[0]->getTarget()->getRoute());
        self::assertSame(
            ['mode' => 'catalog', 'finding' => 'merchant-listing-image', 'store' => '2'],
            $items[0]->getTarget()->getParams()
        );
    }

    public function testTheRecommendedGapsAreTheWarningFindingsOfTheShopsDataWithoutAStoreViewOverEveryStoreView(): void
    {
        $items = $this->findings('warn', 'warn', true)->items($this->scope(SetupState::Live, null));

        self::assertSame(['finding-merchant-listing-gtin13-product-ean'], $this->keys($items));
        self::assertSame(AttentionTone::Warn, $items[0]->getTone());
        self::assertSame('EAN is empty on 23 products', $this->title($items[0]));
        self::assertSame(
            ['mode' => 'catalog', 'finding' => 'merchant-listing-gtin13-product-ean'],
            $items[0]->getTarget()->getParams()
        );
    }

    public function testAFindingOfAPropertyTheTemplateNeverWritesIsListedUnlessOnlyDataGapsAre(): void
    {
        $items = $this->findings('warn', 'warn')->items($this->scope(SetupState::Live, null));

        self::assertSame(
            ['finding-merchant-listing-gtin13-product-ean', 'finding-merchant-listing-sku'],
            $this->keys($items)
        );
    }

    public function testNoFindingIsListedWhileTheSnippetsAreOff(): void
    {
        self::assertSame([], $this->findings('bad', 'bad')->items($this->scope(SetupState::Off, null)));
    }

    public function testTheThemeMarkupStillThereIsListedWhetherTheSnippetsAreOnOrOff(): void
    {
        $provider = new ThemeMarkupAttention($this->itemFactory(), $this->targetFactory());

        $off = $provider->items($this->scope(SetupState::Off, self::STORE, ThemeMarkupState::StillThere));
        $live = $provider->items($this->scope(SetupState::Live, null, ThemeMarkupState::StillThere));

        self::assertSame(['theme-markup'], $this->keys($off));
        self::assertSame(['theme-markup'], $this->keys($live));
        self::assertSame(AttentionTone::Warn, $off[0]->getTone());
        self::assertSame('adminhtml/system_config/edit', $off[0]->getTarget()->getRoute());
        self::assertSame(['section' => 'hryvinskyi_rich_snippets', 'store' => '2'], $off[0]->getTarget()->getParams());
        self::assertSame(['section' => 'hryvinskyi_rich_snippets'], $live[0]->getTarget()->getParams());
    }

    public function testTheThemeMarkupIsNotListedOnceRemovedOrBeforeAnythingIsSetUp(): void
    {
        $provider = new ThemeMarkupAttention($this->itemFactory(), $this->targetFactory());

        self::assertSame([], $provider->items($this->scope(SetupState::Live, null, ThemeMarkupState::Removed)));
        self::assertSame([], $provider->items($this->scope(SetupState::Live, null, ThemeMarkupState::NoneSeen)));
        self::assertSame([], $provider->items($this->scope(SetupState::None, null, ThemeMarkupState::StillThere)));
    }

    public function testATemplateWithANewerVersionThanASnippetOfTheScopeOpensItsLibraryCard(): void
    {
        $provider = $this->templates();

        $items = $provider->items($this->scope(SetupState::Live, null, ThemeMarkupState::Removed, [
            $this->snippet(1, new Origin('product', 1)),
        ]));

        self::assertSame(['template-product'], $this->keys($items));
        self::assertSame(AttentionTone::Info, $items[0]->getTone());
        self::assertSame('The Product template has a newer version', $this->title($items[0]));
        self::assertSame('hryvinskyi_richsnippets/library/index', $items[0]->getTarget()->getRoute());
        self::assertSame(['template' => 'product'], $items[0]->getTarget()->getParams());
    }

    public function testATemplateWhoseOlderCopyIsInAnotherStoreViewIsNotListedForThisOne(): void
    {
        $items = $this->templates()->items($this->scope(SetupState::Live, self::STORE, ThemeMarkupState::Removed, [
            $this->snippet(1, new Origin('product', 2)),
            $this->snippet(2, null),
        ]));

        self::assertSame([], $items);
    }

    public function testNoNewerTemplateIsListedWhileTheSnippetsAreOff(): void
    {
        $items = $this->templates()->items($this->scope(SetupState::Off, null, ThemeMarkupState::Removed, [
            $this->snippet(1, new Origin('product', 1)),
        ]));

        self::assertSame([], $items);
    }

    public function testTheListAsksTheProvidersInThePoolsOrder(): void
    {
        $first = $this->createMock(AttentionItemProviderInterface::class);
        $first->method('items')->willReturn([$this->item('b')]);
        $second = $this->createMock(AttentionItemProviderInterface::class);
        $second->method('items')->willReturn([$this->item('a'), $this->item('c')]);

        $pool = new AttentionItems(['first' => $first, 'second' => $second]);

        $items = $pool->items($this->scope(SetupState::Live, null));

        self::assertSame(['b', 'a', 'c'], $this->keys($items));
    }

    public function testAPoolMemberThatIsNotAProviderFailsLoudly(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('broken');

        (new AttentionItems(['broken' => new \stdClass()]))->items($this->scope(SetupState::Live, null));
    }

    /**
     * The findings provider over the design's last check: an image missing on 2 products, an EAN on 23, a SKU the
     * template never writes on 184, reviews left out on 71
     *
     * @param string $findingTone
     * @param string $itemTone
     * @param bool $dataGapsOnly
     * @return FindingAttention
     */
    private function findings(string $findingTone, string $itemTone, bool $dataGapsOnly = false): FindingAttention
    {
        $check = $this->createMock(CatalogCheckInterface::class);
        $check->method('findings')->willReturn([
            new Finding(
                'merchant-listing-image',
                PropertyTone::Bad,
                'merchant_listing',
                'image',
                ['product.image'],
                ['product'],
                2,
                ['product' => 2]
            ),
            new Finding(
                'merchant-listing-gtin13-product-ean',
                PropertyTone::Warn,
                'merchant_listing',
                'gtin13',
                ['product.ean'],
                ['product'],
                23,
                ['product' => 23]
            ),
            new Finding(
                'merchant-listing-sku',
                PropertyTone::Warn,
                'merchant_listing',
                'sku',
                [],
                ['product'],
                184,
                ['product' => 184]
            ),
            new Finding(
                'review-stars-left-out',
                PropertyTone::Note,
                'review_stars',
                '',
                ['reviews.rating'],
                ['product'],
                71,
                ['product' => 71]
            ),
        ]);

        return new FindingAttention(
            $check,
            $this->findingWording(['product.ean' => 'EAN']),
            $this->itemFactory(),
            $this->targetFactory(),
            $findingTone,
            $itemTone,
            $dataGapsOnly
        );
    }

    /**
     * The newer-template provider over a library whose Product template is at version 2 and was added from version 1
     *
     * @return NewerTemplateAttention
     */
    private function templates(): NewerTemplateAttention
    {
        $template = $this->createMock(LibraryTemplateInterface::class);
        $template->method('getCode')->willReturn('product');
        $template->method('getVersion')->willReturn(2);
        $template->method('getName')->willReturn('Product');
        $entry = $this->createMock(LibraryEntryInterface::class);
        $entry->method('getTemplate')->willReturn($template);
        $entry->method('hasNewerVersion')->willReturn(true);
        $current = $this->createMock(LibraryTemplateInterface::class);
        $current->method('getCode')->willReturn('breadcrumbs');
        $current->method('getVersion')->willReturn(1);
        $upToDate = $this->createMock(LibraryEntryInterface::class);
        $upToDate->method('getTemplate')->willReturn($current);
        $upToDate->method('hasNewerVersion')->willReturn(false);
        $library = $this->createMock(LibraryInterface::class);
        $library->method('list')->willReturn([$entry, $upToDate]);

        return new NewerTemplateAttention($library, $this->itemFactory(), $this->targetFactory());
    }

    /**
     * What the Overview knows of a scope
     *
     * @param SetupState $state
     * @param int|null $storeId
     * @param ThemeMarkupState $themeMarkup
     * @param list<Snippet> $snippets
     * @return AttentionScope
     */
    private function scope(
        SetupState $state,
        ?int $storeId,
        ThemeMarkupState $themeMarkup = ThemeMarkupState::Removed,
        array $snippets = []
    ): AttentionScope {
        return new AttentionScope($storeId, $state, $themeMarkup, $snippets);
    }

    /**
     * A product page snippet, added from the library or not
     *
     * @param int $id
     * @param OriginInterface|null $origin
     * @return Snippet
     */
    private function snippet(int $id, ?OriginInterface $origin): Snippet
    {
        return new Snippet(
            $id,
            'product-' . $id,
            'Product ' . $id,
            '{"@type": "Product"}',
            'Product',
            false,
            true,
            ['product'],
            PlacementFixture::none(),
            [0],
            $origin,
            0,
            null,
            ''
        );
    }

    /**
     * An item with a key
     *
     * @param string $key
     * @return AttentionItemInterface
     */
    private function item(string $key): AttentionItemInterface
    {
        return new AttentionItem($key, AttentionTone::Info, $key, [], '', [], '', new AttentionTarget('a/b/c', []));
    }

    /**
     * An item's title in the admin's words
     *
     * @param AttentionItemInterface $item
     * @return string
     */
    private function title(AttentionItemInterface $item): string
    {
        return (string) __($item->getTitle(), ...$item->getTitleArguments());
    }

    /**
     * The keys of some items, in order
     *
     * @param list<AttentionItemInterface> $items
     * @return list<string>
     */
    private function keys(array $items): array
    {
        return array_map(static fn (AttentionItemInterface $item): string => $item->getKey(), $items);
    }

    /**
     * The item factory as Magento generates it, creating the module's item
     *
     * @return AttentionItemInterfaceFactory
     */
    private function itemFactory(): AttentionItemInterfaceFactory
    {
        $factory = $this->createMock(AttentionItemInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(static function (array $data): AttentionItemInterface {
            self::assertInstanceOf(AttentionTone::class, $data['tone'] ?? null);
            self::assertInstanceOf(AttentionTarget::class, $data['target'] ?? null);

            return new AttentionItem(
                self::text($data, 'key'),
                $data['tone'],
                self::text($data, 'title'),
                self::texts($data, 'titleArguments'),
                self::text($data, 'description'),
                self::texts($data, 'descriptionArguments'),
                self::text($data, 'actionLabel'),
                $data['target']
            );
        });

        return $factory;
    }

    /**
     * The target factory as Magento generates it, creating the module's target
     *
     * @return AttentionTargetInterfaceFactory
     */
    private function targetFactory(): AttentionTargetInterfaceFactory
    {
        $factory = $this->createMock(AttentionTargetInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(static function (array $data): AttentionTarget {
            $params = $data['params'] ?? null;
            self::assertIsArray($params);

            return new AttentionTarget(self::text($data, 'route'), array_map(
                static fn (mixed $value): string => is_string($value) ? $value : '',
                array_filter($params, static fn (mixed $key): bool => is_string($key), ARRAY_FILTER_USE_KEY)
            ));
        });

        return $factory;
    }

    /**
     * A text a factory was given
     *
     * @param array<mixed> $data
     * @param string $name
     * @return string
     */
    private static function text(array $data, string $name): string
    {
        self::assertIsString($data[$name] ?? null, $name);

        return $data[$name];
    }

    /**
     * A list of texts a factory was given
     *
     * @param array<mixed> $data
     * @param string $name
     * @return list<string>
     */
    private static function texts(array $data, string $name): array
    {
        $texts = $data[$name] ?? null;
        self::assertIsArray($texts, $name);

        return array_values(array_map(static fn (mixed $text): string => is_string($text) ? $text : '', $texts));
    }
}
