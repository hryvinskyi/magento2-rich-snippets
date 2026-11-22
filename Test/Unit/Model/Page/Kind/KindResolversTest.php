<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Page\Kind;

use Hryvinskyi\RichSnippets\Model\Page\Cms\StoreCmsPagesInterface;
use Hryvinskyi\RichSnippets\Model\Page\Kind\HomeKindResolver;
use Hryvinskyi\RichSnippets\Model\Page\Kind\NoEntityKindResolver;
use Hryvinskyi\RichSnippets\Model\Page\Kind\RequestParamKindResolver;
use Magento\Framework\App\RequestInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The entity each kind of page reads from a request
 */
class KindResolversTest extends TestCase
{
    /**
     * @return array<string, array{mixed, int|null}>
     */
    public static function parameters(): array
    {
        return [
            'a positive ID' => ['42', 42],
            'an integer' => [7, 7],
            'no parameter' => [null, null],
            'zero' => ['0', null],
            'a negative number' => ['-3', null],
            'a number with text after it' => ['12abc', null],
            'a list' => [['12'], null],
        ];
    }

    #[DataProvider('parameters')]
    public function testTheEntityIdIsTheRequestParameterWhenItIsAPositiveWholeNumber(mixed $value, ?int $id): void
    {
        $resolver = new RequestParamKindResolver(['view' => 'catalog_product_view'], 'id');

        self::assertSame($id, $resolver->entityId($this->request(['id' => $value]), 1));
        self::assertSame(['catalog_product_view'], $resolver->getActionNames());
    }

    public function testACmsPageIsReadFromItsOwnParameter(): void
    {
        $resolver = new RequestParamKindResolver(['view' => 'cms_page_view'], 'page_id');

        self::assertSame(5, $resolver->entityId($this->request(['id' => '9', 'page_id' => '5']), 1));
    }

    public function testTheHomePageShowsTheStoreViewsConfiguredHomePageWhateverTheRequestSays(): void
    {
        $cmsPages = new class implements StoreCmsPagesInterface {
            public function homePageId(int $storeId): ?int
            {
                return $storeId === 2 ? 11 : null;
            }

            public function noRoutePageId(int $storeId): ?int
            {
                return null;
            }
        };
        $resolver = new HomeKindResolver($cmsPages, ['index' => 'cms_index_index']);

        self::assertSame(11, $resolver->entityId($this->request(['page_id' => '5']), 2));
        self::assertNull($resolver->entityId($this->request([]), 3));
        self::assertSame(['cms_index_index'], $resolver->getActionNames());
    }

    public function testEveryOtherPageShowsNoEntityAndOwnsNoAction(): void
    {
        $resolver = new NoEntityKindResolver();

        self::assertNull($resolver->entityId($this->request(['id' => '5']), 1));
        self::assertSame([], $resolver->getActionNames());
    }

    /**
     * A request with parameters
     *
     * @param array<string, mixed> $params
     * @return RequestInterface
     */
    private function request(array $params): RequestInterface
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $name, mixed $default = null): mixed => $params[$name] ?? $default
        );

        return $request;
    }
}
