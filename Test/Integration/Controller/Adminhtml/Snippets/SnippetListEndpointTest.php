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
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\StoresTestSnippets;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The snippet list through the real admin, as the Snippets page reads it
 *
 * Each row says what the snippet is, where it is output, what it includes and what includes it, and carries the
 * flags of the flag providers: a required property no page can have is bad, a result Google stopped showing is a
 * note. The counts follow the rows. A store view the shop lacks is refused. The parent class adds the access tests
 * for the permission to see the module.
 */
#[AppArea('adminhtml'), AppIsolation(true), DbIsolation(true)]
class SnippetListEndpointTest extends AbstractBackendController
{
    use AsksAsTheSnippetsPage;
    use StoresTestSnippets;

    private const LIST = 'backend/richsnippets/snippets/list';

    /**
     * @var string
     */
    protected $resource = AdminResources::VIEW;

    /**
     * @var string
     */
    protected $uri = self::LIST;

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_GET;

    public function testEachRowSaysWhatTheSnippetIsWhereItIsOutputAndWhatItIncludes(): void
    {
        $this->storeShop();

        $answer = $this->read(self::LIST);

        $rows = array_column((array) $this->at($answer, 'rows'), null, 'code');
        $product = $rows['rs-product'] ?? null;
        self::assertIsArray($product);
        self::assertSame('Product', $product['type']);
        self::assertSame(['product'], $product['pageKinds']);
        self::assertTrue($product['active']);
        self::assertFalse($product['isBlock']);
        self::assertSame(
            '{"type":"rich_snippets/combine","attribute":null,"operator":null,"value":"1","is_value_processed":null,'
            . '"aggregator":"all","conditions":[{"type":"product_type","attribute":null,"operator":"!=",'
            . '"value":"configurable","is_value_processed":null}]}',
            $product['conditions']
        );
        self::assertSame('Product type is not Configurable Product', $product['conditionSummary']);
        self::assertSame('', $this->at($rows, 'rs-policy', 'conditions'));
        self::assertSame(4, $product['propertyCount']);
        self::assertSame([['code' => 'rs-policy', 'name' => 'Return policy']], $product['uses']);
        self::assertSame([], $product['storeNames']);
        self::assertSame('No offer: Google needs one', $this->at($product, 'flags', 'bad'));
        $policy = $rows['rs-policy'] ?? null;
        self::assertIsArray($policy);
        self::assertTrue($policy['isBlock']);
        self::assertSame([['code' => 'rs-product', 'name' => 'Product']], $policy['usedIn']);
        self::assertSame('Google no longer shows FAQ results', $this->at($rows, 'rs-faq', 'flags', 'note'));
        self::assertSame([$this->defaultStoreName()], $this->at($rows, 'rs-faq', 'storeNames'));
    }

    public function testTheCountsFollowTheRows(): void
    {
        $before = $this->read(self::LIST);
        $this->storeShop();

        $after = $this->read(self::LIST);

        $grew = fn (string ...$path): int => $this->number($after, ...$path) - $this->number($before, ...$path);
        self::assertSame(4, $grew('counts', 'all'));
        self::assertSame(1, $grew('counts', 'blocks'));
        self::assertSame(2, $grew('counts', 'on'));
        self::assertSame(1, $grew('counts', 'off'));
        self::assertSame(1, $grew('counts', 'kinds', 'product'));
        self::assertSame(2, $grew('counts', 'kinds', 'cms'));
        self::assertSame(1, $grew('counts', 'flags', 'bad'));
        self::assertSame(1, $grew('counts', 'flags', 'note'));
        self::assertSame(1, $grew('counts', 'attention'));
    }

    public function testAStoreViewListsTheSnippetsItOutputsAndAnUnknownOneIsRefused(): void
    {
        $this->storeShop();
        $storeId = (string) $this->defaultStoreId();

        $answer = $this->read(self::LIST, ['store' => $storeId]);

        $codes = array_column((array) $this->at($answer, 'rows'), 'code');
        self::assertContains('rs-faq', $codes, 'A snippet set for the store view is listed.');
        self::assertContains('rs-product', $codes, 'A snippet set for every store view is listed.');
        self::assertContains('rs-policy', $codes, 'A building block is listed.');
        self::assertSame(['error' => 'store'], $this->read(self::LIST, ['store' => '987654'], 400));
    }

    /**
     * Store a product snippet including a building block, an FAQ for the default store view and a switched-off one
     *
     * @return void
     */
    private function storeShop(): void
    {
        $this->storeSnippet('rs-policy', 'Return policy', [
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => 'DE',
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => 30,
        ], ['block' => true]);
        $this->storeSnippet('rs-product', 'Product', [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => '{{product.name}}',
            'image' => '{{product.image}}',
            'description' => '{{product.short_description}}',
            'hasMerchantReturnPolicy' => ['@include' => 'rs-policy'],
        ], ['kinds' => ['product'], 'conditions' => [['product_type', '!=', 'configurable']], 'active' => true]);
        $this->storeSnippet('rs-faq', 'FAQ', [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => [['@type' => 'Question', 'name' => 'Why?', 'acceptedAnswer' => ['text' => 'Because.']]],
        ], ['kinds' => ['cms'], 'stores' => [$this->defaultStoreId()], 'active' => true]);
        $this->storeSnippet('rs-list', 'Product list', [
            '@type' => 'ItemList',
            'name' => 'Products',
        ], ['kinds' => ['cms']]);
    }

    /**
     * A count in an answer
     *
     * @param array<mixed> $answer
     * @param string ...$path
     * @return int
     */
    private function number(array $answer, string ...$path): int
    {
        $value = $this->at($answer, ...$path);
        self::assertIsInt($value, implode('.', $path));

        return $value;
    }

    /**
     * @return int
     */
    private function defaultStoreId(): int
    {
        return (int) $this->storeManager()->getDefaultStoreView()?->getId();
    }

    /**
     * @return string
     */
    private function defaultStoreName(): string
    {
        return (string) $this->storeManager()->getDefaultStoreView()?->getName();
    }

    /**
     * @return StoreManagerInterface
     */
    private function storeManager(): StoreManagerInterface
    {
        $storeManager = $this->testObjectManager()->get(StoreManagerInterface::class);
        self::assertInstanceOf(StoreManagerInterface::class, $storeManager);

        return $storeManager;
    }
}
