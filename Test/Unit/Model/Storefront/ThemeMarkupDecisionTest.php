<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Storefront;

use Hryvinskyi\RichSnippets\Model\Config\OutputSettings;
use Hryvinskyi\RichSnippets\Model\Storefront\OutputPositionPool;
use Hryvinskyi\RichSnippets\Model\Page\PageEntity;
use Hryvinskyi\RichSnippets\Model\Storefront\RenderedBlock;
use Hryvinskyi\RichSnippets\Model\Storefront\ThemeMarkupDecision;
use Hryvinskyi\RichSnippets\Model\Storefront\ThemeMarkupTypes;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

/**
 * The theme's markup goes only from a product page that wrote a Product or ProductGroup snippet, with the setting on,
 * and then only for the types the page's snippets write: the product types count as one, the others as themselves
 */
class ThemeMarkupDecisionTest extends TestCase
{
    public function testAProductPageWithAProductSnippetLosesTheMarkupOfEveryTypeItWrites(): void
    {
        $decision = $this->decision(true);
        $decision->record(new PageEntity('product', 5, 1), [$this->block('Organization'), $this->block('Product')]);

        self::assertEqualsCanonicalizing(['Organization', 'Product', 'ProductGroup'], $decision->replacedTypes());
    }

    public function testAProductGroupSnippetReplacesTheMarkupOfAProductToo(): void
    {
        $decision = $this->decision(true);
        $decision->record(
            new PageEntity('product', 5, 1),
            [$this->block('ProductGroup'), $this->block('BreadcrumbList'), $this->block('BreadcrumbList')]
        );

        self::assertEqualsCanonicalizing(['BreadcrumbList', 'Product', 'ProductGroup'], $decision->replacedTypes());
    }

    public function testAProductPageWithoutSuchASnippetKeepsTheMarkup(): void
    {
        $decision = $this->decision(true);
        $decision->record(new PageEntity('product', 5, 1), [$this->block('BreadcrumbList')]);

        self::assertSame([], $decision->replacedTypes());
    }

    public function testAnotherKindOfPageKeepsTheMarkup(): void
    {
        $decision = $this->decision(true);
        $decision->record(new PageEntity('category', 3, 1), [$this->block('Product')]);

        self::assertSame([], $decision->replacedTypes());
    }

    public function testTheSettingOffKeepsTheMarkup(): void
    {
        $decision = $this->decision(false);
        $decision->record(new PageEntity('product', 5, 1), [$this->block('Product')]);

        self::assertSame([], $decision->replacedTypes());
    }

    public function testNothingIsRemovedBeforeAPageIsRecordedOrAfterTheRequest(): void
    {
        $decision = $this->decision(true);
        self::assertSame([], $decision->replacedTypes());

        $decision->record(new PageEntity('product', 5, 1), [$this->block('Product')]);
        $decision->_resetState();

        self::assertSame([], $decision->replacedTypes());
    }

    public function testALaterPageOfTheRequestReplacesTheEarlierDecision(): void
    {
        $decision = $this->decision(true);
        $decision->record(new PageEntity('product', 5, 1), [$this->block('Product')]);
        $decision->record(new PageEntity('category', 3, 1), [$this->block('Product')]);

        self::assertSame([], $decision->replacedTypes());
    }

    /**
     * A decision for product pages and the two product types, with the removal setting as given
     *
     * @param bool $settingOn
     * @return ThemeMarkupDecision
     */
    private function decision(bool $settingOn): ThemeMarkupDecision
    {
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('isSetFlag')->willReturnCallback(
            static fn (string $path): bool => $path === 'hryvinskyi_rich_snippets/theme/remove_product_markup'
                && $settingOn
        );

        return new ThemeMarkupDecision(
            new OutputSettings($config, new OutputPositionPool()),
            new ThemeMarkupTypes(['product' => 'Product', 'product_group' => 'ProductGroup']),
            ['product' => 'product']
        );
    }

    /**
     * A block of a snippet type
     *
     * @param string $type
     * @return RenderedBlock
     */
    private function block(string $type): RenderedBlock
    {
        return new RenderedBlock(strtolower($type), $type, '{}');
    }
}
