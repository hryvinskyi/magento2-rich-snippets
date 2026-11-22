<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Field;

use Hryvinskyi\RichSnippets\Api\CollectionInterface;
use Hryvinskyi\RichSnippets\Api\Data\FieldInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\FieldFactoryInterface;
use Hryvinskyi\RichSnippets\Api\FieldResolverInterface;
use Hryvinskyi\RichSnippets\Model\Collection\CollectionPool;
use Hryvinskyi\RichSnippets\Model\Field\Field;
use Hryvinskyi\RichSnippets\Model\Field\FieldCatalog;
use Hryvinskyi\RichSnippets\Model\Field\Group\ConfiguredFieldGroup;
use Hryvinskyi\RichSnippets\Model\Field\ItemAliases;
use Hryvinskyi\RichSnippets\Model\Field\Value\FieldValues;
use Hryvinskyi\RichSnippets\Model\Field\Value\FieldValuesFactory;
use Hryvinskyi\RichSnippets\Model\Page\PageEntity;
use PHPUnit\Framework\TestCase;

/**
 * The catalog lists each path once, built-ins before attributes; a root's values resolve lazily, once per key
 */
class FieldCatalogTest extends TestCase
{
    /**
     * @var array<string, int> how often each path was resolved
     */
    private array $resolved = [];

    public function testABuiltInFieldShadowsTheAttributeOfTheSamePathWhereverItsGroupIsListed(): void
    {
        $catalog = new FieldCatalog(
            [
                'product' => $this->group('product', ['product.name' => 'Name', 'product.image' => 'Main image']),
                'product_attributes' => $this->group(
                    'product_attributes',
                    ['product.image' => 'Base', 'product.ean' => 'EAN', 'product.special_to_date' => 'Special To']
                ),
                'price' => $this->group('price', ['product.special_to_date' => 'Special price end date']),
            ],
            ['product_attributes']
        );

        self::assertSame(
            ['product.name', 'product.image', 'product.ean', 'product.special_to_date'],
            array_map(static fn (FieldInterface $field): string => $field->getPath(), $catalog->getFields())
        );
        self::assertSame('Main image', $catalog->getField('product.image')?->getLabel());
        self::assertSame('price', $catalog->getField('product.special_to_date')?->getGroupCode());
        self::assertSame('resolved product.ean for 7', $catalog->resolve('product.ean', 7, 1));
        self::assertNull($catalog->resolve('product.unknown', 7, 1));
    }

    public function testAPathAboveDeeperFieldsIsABranch(): void
    {
        $catalog = new FieldCatalog(['store' => $this->group('store', ['store.address.city' => 'City'])]);

        self::assertTrue($catalog->isBranch('store.address'));
        self::assertFalse($catalog->isBranch('store'));
        self::assertFalse($catalog->isBranch('store.address.city'));
    }

    public function testARootsValuesResolveEachKeyOnceAndStepIntoBranchesAndLists(): void
    {
        $catalog = new FieldCatalog([
            'store' => $this->group('store', ['store.name' => 'Name', 'store.address.city' => 'City']),
        ]);
        $gallery = $this->createMock(CollectionInterface::class);
        $gallery->method('getPath')->willReturn('store.gallery');
        $gallery->method('getItemName')->willReturn('image');
        $gallery->method('items')->willReturn([['url' => 'https://shop.example/a.jpg']]);
        $collections = new CollectionPool(['gallery' => $gallery]);
        $page = new PageEntity('home', null, 1);
        $values = $this->values($catalog, $collections, $page, 'store');

        self::assertTrue($values->has('name'));
        self::assertTrue($values->has('address'));
        self::assertTrue($values->has('gallery'));
        self::assertFalse($values->has('phone'));
        $first = $values->get('name');
        $second = $values->get('name');
        self::assertSame('resolved store.name for 1', $first);
        self::assertSame($first, $second);
        self::assertSame(1, $this->resolved['store.name']);
        $address = $values->get('address');
        self::assertInstanceOf(FieldValues::class, $address);
        self::assertSame('resolved store.address.city for 1', $address->get('city'));
        self::assertSame([['url' => 'https://shop.example/a.jpg']], $values->get('gallery'));
        self::assertNull($values->get('phone'));
        self::assertSame('image', (new ItemAliases($collections))->resolve('store.gallery'));
        self::assertNull((new ItemAliases($collections))->resolve('store.name'));
    }

    public function testAFieldWithoutAResolverIsCarriedByItsListsItems(): void
    {
        $group = new ConfiguredFieldGroup($this->fieldFactory(), 'review', 'Review', ['product'], [
            'nickname' => ['path' => 'review.nickname', 'label' => 'Nickname'],
        ]);
        $field = $group->getFields()[0];

        self::assertSame(['product'], $field->getPageKinds());
        self::assertSame('Review', $field->getGroupLabel());
        self::assertNull($group->resolve($field, 1, 1));
    }

    public function testKindsOfPageKeyedAsTheContainerConfigurationWritesThemAreListedUnkeyed(): void
    {
        $group = new ConfiguredFieldGroup(
            $this->fieldFactory(),
            'product',
            'Product',
            ['product' => 'product', 'category' => 'category'],
            ['name' => ['path' => 'product.name', 'label' => 'Name']]
        );

        self::assertSame(['product', 'category'], $group->getFields()[0]->getPageKinds());
    }

    public function testAFieldDefinitionWithoutAPathIsRefused(): void
    {
        $this->expectException(\LogicException::class);

        (new ConfiguredFieldGroup($this->fieldFactory(), 'broken', 'Broken', [], ['x' => ['label' => 'X']]))
            ->getFields();
    }

    /**
     * A configured group whose every field resolves to a string naming its path and subject
     *
     * @param string $code
     * @param array<string, string> $labels by path
     * @return ConfiguredFieldGroup
     */
    private function group(string $code, array $labels): ConfiguredFieldGroup
    {
        $resolver = $this->createMock(FieldResolverInterface::class);
        $resolver->method('resolve')->willReturnCallback(
            function (FieldInterface $field, int $subjectId): string {
                $this->resolved[$field->getPath()] = ($this->resolved[$field->getPath()] ?? 0) + 1;

                return 'resolved ' . $field->getPath() . ' for ' . $subjectId;
            }
        );
        $definitions = [];
        foreach ($labels as $path => $label) {
            $definitions[$path] = ['path' => $path, 'label' => $label, 'resolver' => $resolver];
        }

        return new ConfiguredFieldGroup($this->fieldFactory(), $code, ucfirst($code), [], $definitions);
    }

    /**
     * A field factory creating fields directly
     *
     * @return FieldFactoryInterface
     */
    private function fieldFactory(): FieldFactoryInterface
    {
        $factory = $this->createMock(FieldFactoryInterface::class);
        $factory->method('create')->willReturnCallback(
            static fn (
                string $path,
                string $label,
                string $groupCode,
                string $groupLabel,
                ?string $attributeCode = null,
                array $pageKinds = []
            ): FieldInterface => new Field(
                $path,
                $label,
                $groupCode,
                $groupLabel,
                $attributeCode,
                array_values(array_filter($pageKinds, 'is_string'))
            )
        );

        return $factory;
    }

    /**
     * The values of a root on a page, branches created by a factory that builds them directly
     *
     * @param FieldCatalog $catalog
     * @param CollectionPool $collections
     * @param PageEntityInterface $page
     * @param string $prefix
     * @return FieldValues
     */
    private function values(
        FieldCatalog $catalog,
        CollectionPool $collections,
        PageEntityInterface $page,
        string $prefix
    ): FieldValues {
        $branches = $this->createMock(FieldValuesFactory::class);
        $branches->method('create')->willReturnCallback(
            static function (array $data) use ($catalog, $collections, $branches): FieldValues {
                self::assertInstanceOf(PageEntityInterface::class, $data['page']);
                self::assertIsInt($data['subjectId']);
                self::assertIsString($data['prefix']);

                return new FieldValues(
                    $catalog,
                    $collections,
                    $branches,
                    $data['page'],
                    $data['subjectId'],
                    $data['prefix']
                );
            }
        );

        return new FieldValues($catalog, $collections, $branches, $page, $page->getStoreId(), $prefix);
    }
}
