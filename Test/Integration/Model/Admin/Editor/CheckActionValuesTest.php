<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Admin\Editor;

use Hryvinskyi\RichSnippets\Api\Data\LibraryTemplateInterface;
use Hryvinskyi\RichSnippets\Api\LibraryInterface;
use Hryvinskyi\RichSnippets\Api\LibraryTemplatePoolInterface;
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Editor\CheckActionValues;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The values the Checks panel's add actions carry, read from the real library through the real container
 *
 * A Product draft missing its offer gets the library's offer with the properties it stands before, its building
 * block pointed at the shop's own return policy; a property no template has, an action that puts nothing back and a
 * type the library does not know are left as they came; a property the draft already has loses its action, since
 * only the page's data leaves it empty; a question the library asks is answered, never passed on; and the template a
 * stored snippet was added from is looked in before the others of its type.
 */
#[AppArea('adminhtml'), DbIsolation(true)]
class CheckActionValuesTest extends TestCase
{
    /** A Product draft with its name and nothing else */
    private const DRAFT = '{"@context": "https://schema.org", "@type": "Product", "name": "{{product.name}}"}';

    public function testAMissingOfferIsTheLibrarysOfferPlacedBeforeTheRatingAndTheReviews(): void
    {
        [$row] = $this->values()->fill([$this->add('offers')], self::DRAFT, 'Product', null, $this->storeId());

        $action = $row['action'];
        self::assertNotNull($action);
        $offer = $this->decoded($action['value']);
        self::assertSame('Offer', $offer['@type']);
        self::assertSame('{{product.final_price}}', $offer['price']);
        self::assertSame(['aggregateRating', 'review'], $action['before']);
        self::assertSame('Add offers', $action['label'], 'The rest of the action stays.');
    }

    public function testTheOfferOfAProductAddedFromTheLibraryPointsAtTheShopsReturnPolicy(): void
    {
        $product = $this->addedProduct('check-values-' . uniqid());

        [$row] = $this->values()->fill([$this->add('offers')], self::DRAFT, 'Product', $product, $this->storeId());

        $policy = $this->decoded($row['action']['value'] ?? null)['hasMerchantReturnPolicy'] ?? null;
        self::assertIsArray($policy);
        $code = $policy['@include'] ?? null;
        self::assertIsString($code);
        $block = $this->repository()->getByCode($code);
        self::assertTrue($block->isBlock());
        self::assertSame('return-policy', $block->getOrigin()?->getCode());
    }

    public function testWhatNoTemplateHasAndWhatPutsNothingBackStayAsTheyCame(): void
    {
        $note = ['tone' => 'note', 'title' => 'A note', 'text' => '', 'action' => null];
        $pages = [
            'tone' => 'warn',
            'title' => 'Not output',
            'text' => '',
            'action' => [
                'label' => 'See the conditions',
                'path' => null,
                'icon' => 'tune',
                'value' => null,
                'before' => [],
            ],
        ];
        $unknown = $this->add('madeUpProperty');
        $rows = [$note, $pages, $unknown];

        self::assertSame($rows, $this->values()->fill($rows, self::DRAFT, 'Product', null, $this->storeId()));
        self::assertSame(
            [$this->add('offers')],
            $this->values()->fill([$this->add('offers')], self::DRAFT, 'Thing', null, 1)
        );
    }

    public function testAPropertyTheDraftHasIsNotPutBack(): void
    {
        $draft = '{"@type": "Product", "name": "{{product.name}}", "offers": {"@type": "Offer", "price": "1"}}';

        [$top, $inner, $missing] = $this->values()->fill(
            [$this->add('name'), $this->add('offers.price'), $this->add('offers.availability')],
            $draft,
            'Product',
            null,
            $this->storeId()
        );

        self::assertNull($top['action']);
        self::assertNull($inner['action']);
        self::assertSame('Missing name', $top['title'], 'The row stays.');
        $action = $missing['action'];
        self::assertNotNull($action);
        self::assertSame('"{{product.availability}}"', $action['value']);
        self::assertSame(['itemCondition', 'hasMerchantReturnPolicy'], $action['before']);
    }

    public function testAQuestionTheLibraryAsksIsAnsweredAndNeverPassedOn(): void
    {
        $rows = [$this->add('brand'), $this->add('gtin13'), $this->add('description')];

        foreach ($this->values()->fill($rows, self::DRAFT, 'Product', null, $this->storeId()) as $row) {
            $value = $row['action']['value'] ?? null;
            self::assertTrue(
                $value === null || !str_contains($value, '{{?'),
                sprintf('"%s" carries no question: %s', $row['action']['path'] ?? '', (string) $value)
            );
        }
    }

    public function testTheTemplateAStoredSnippetWasAddedFromIsLookedInFirst(): void
    {
        $product = $this->addedProduct('check-values-' . uniqid());
        $pool = Bootstrap::getObjectManager()->get(LibraryTemplatePoolInterface::class);
        self::assertInstanceOf(LibraryTemplatePoolInterface::class, $pool);
        $library = $pool->get('product');
        self::assertInstanceOf(LibraryTemplateInterface::class, $library);
        $other = $this->createStub(LibraryTemplateInterface::class);
        $other->method('getCode')->willReturn('another-product');
        $other->method('getType')->willReturn('Product');
        $other->method('getTemplate')->willReturn(['@type' => 'Product', 'offers' => ['@type' => 'AggregateOffer']]);
        $templates = $this->createStub(LibraryTemplatePoolInterface::class);
        $templates->method('all')->willReturn([$other, $library]);
        $values = Bootstrap::getObjectManager()->create(CheckActionValues::class, ['templates' => $templates]);
        self::assertInstanceOf(CheckActionValues::class, $values);

        [$stored] = $values->fill([$this->add('offers')], self::DRAFT, 'Product', $product, $this->storeId());
        [$fresh] = $values->fill([$this->add('offers')], self::DRAFT, 'Product', null, $this->storeId());

        self::assertSame('Offer', $this->decoded($stored['action']['value'] ?? null)['@type']);
        self::assertSame('AggregateOffer', $this->decoded($fresh['action']['value'] ?? null)['@type']);
        self::assertSame([], $fresh['action']['before'] ?? null);
    }

    /**
     * A row whose action puts the property at the path back
     *
     * @param string $path
     * @return array{tone: string, title: string, text: string,
     *     action: array{label: string, path: string, icon: string, value: null, before: list<string>}}
     */
    private function add(string $path): array
    {
        return [
            'tone' => 'bad',
            'title' => 'Missing ' . $path,
            'text' => '',
            'action' => ['label' => 'Add ' . $path, 'path' => $path, 'icon' => 'add', 'value' => null, 'before' => []],
        ];
    }

    /**
     * The id of a Product snippet the library adds
     *
     * @param string $key
     * @return int
     */
    private function addedProduct(string $key): int
    {
        $library = Bootstrap::getObjectManager()->get(LibraryInterface::class);
        self::assertInstanceOf(LibraryInterface::class, $library);
        $added = $library->add('product', [], $key);
        $product = $added[count($added) - 1] ?? null;
        self::assertNotNull($product);

        return $product->getId();
    }

    /**
     * A JSON object as an array
     *
     * @param string|null $value
     * @return array<mixed>
     */
    private function decoded(?string $value): array
    {
        self::assertIsString($value);
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    private function values(): CheckActionValues
    {
        $values = Bootstrap::getObjectManager()->get(CheckActionValues::class);
        self::assertInstanceOf(CheckActionValues::class, $values);

        return $values;
    }

    private function repository(): SnippetRepositoryInterface
    {
        $repository = Bootstrap::getObjectManager()->get(SnippetRepositoryInterface::class);
        self::assertInstanceOf(SnippetRepositoryInterface::class, $repository);

        return $repository;
    }

    private function storeId(): int
    {
        $stores = Bootstrap::getObjectManager()->get(StoreManagerInterface::class);
        self::assertInstanceOf(StoreManagerInterface::class, $stores);

        return (int) $stores->getDefaultStoreView()?->getId();
    }
}
