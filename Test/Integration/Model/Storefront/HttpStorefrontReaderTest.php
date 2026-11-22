<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Storefront;

use Hryvinskyi\RichSnippets\Api\Data\ReadFailure;
use Hryvinskyi\RichSnippets\Api\Exception\StorefrontReadException;
use Hryvinskyi\RichSnippets\Api\StorefrontReaderInterface;
use Hryvinskyi\RichSnippets\Model\Store\StoreAddresses;
use Hryvinskyi\RichSnippets\Model\Storefront\Reading\HttpStorefrontReader;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\FakeMonotonicClock;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\FixedStoreAddresses;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\ScriptedHttpClient;
use Magento\Framework\HTTP\ClientFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The storefront reader only ever asks the store view's own host, follows a redirect by hand only to that same
 * origin and only a few times, and keeps to its time and size limits
 *
 * The reader is built by the container with the platform's client factory making a scripted client, so the guards
 * run as written while no request leaves the process. The store view's base address is a known host, except where
 * the test proves the address is the store view's own.
 */
#[AppArea('adminhtml')]
class HttpStorefrontReaderTest extends TestCase
{
    private const PAGE = '<html><body>page</body></html>';
    private const FIVE_MB = 5242880;

    /**
     * @var FakeMonotonicClock
     */
    private FakeMonotonicClock $clock;

    /**
     * @var FixedStoreAddresses
     */
    private FixedStoreAddresses $addresses;

    /**
     * Give the store views a known base address
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->addresses = new FixedStoreAddresses($this->storeManager());
    }

    public function testTheAddressIsTheStoreViewsOwnBaseAddressFollowedByThePath(): void
    {
        $addresses = Bootstrap::getObjectManager()->get(StoreAddresses::class);
        self::assertInstanceOf(StoreAddresses::class, $addresses);
        ScriptedHttpClient::reset([['status' => 200, 'body' => self::PAGE]]);

        $this->reader($addresses)->read($this->storeId(), '/checkout/cart');

        self::assertSame(
            $addresses->page('', $this->storeId()) . 'checkout/cart',
            ScriptedHttpClient::requests()[0]['url']
        );
    }

    public function testThePageIsReadAtTheStoreViewsBaseAddressFollowedByThePath(): void
    {
        ScriptedHttpClient::reset([['status' => 200, 'body' => self::PAGE]]);

        $page = $this->reader()->read($this->storeId(), '/checkout/cart?step=1');

        self::assertSame('http://shop.test/checkout/cart?step=1', ScriptedHttpClient::requests()[0]['url']);
        self::assertSame('http://shop.test/checkout/cart?step=1', $page->getUrl());
        self::assertSame('/checkout/cart?step=1', $page->getPath());
        self::assertSame(self::PAGE, $page->getBody());
        self::assertSame([], ScriptedHttpClient::requests()[0]['cookies'], 'One store view needs no store cookie.');
    }

    public function testEveryRequestHasRedirectsOffHttpOnlyAndTheLimits(): void
    {
        ScriptedHttpClient::reset([['status' => 200, 'body' => self::PAGE]]);

        $this->reader()->read($this->storeId(), '/');

        $options = ScriptedHttpClient::requests()[0]['options'];
        self::assertFalse($options[CURLOPT_FOLLOWLOCATION]);
        self::assertSame(CURLPROTO_HTTP | CURLPROTO_HTTPS, $options[CURLOPT_PROTOCOLS]);
        self::assertSame(CURLPROTO_HTTP | CURLPROTO_HTTPS, $options[CURLOPT_REDIR_PROTOCOLS]);
        self::assertSame(5000, $options[CURLOPT_CONNECTTIMEOUT_MS]);
        self::assertSame(15000, $options[CURLOPT_TIMEOUT_MS]);
        self::assertSame(self::FIVE_MB, $options[CURLOPT_MAXFILESIZE]);
        self::assertFalse($options[CURLOPT_NOPROGRESS]);
    }

    public function testAPathThatLooksLikeAHostStaysOnTheStoreViewsHost(): void
    {
        ScriptedHttpClient::reset([['status' => 200, 'body' => self::PAGE]]);

        $this->reader()->read($this->storeId(), '//evil.example/x');

        self::assertSame('http://shop.test/evil.example/x', ScriptedHttpClient::requests()[0]['url']);
    }

    public function testAnAddressWithAHostIsRefusedBeforeAnyRequest(): void
    {
        ScriptedHttpClient::reset([]);

        try {
            $this->reader()->read($this->storeId(), 'http://evil.example/');
            self::fail('An address with a host was read.');
        } catch (\InvalidArgumentException) {
            self::assertSame([], ScriptedHttpClient::requests());
        }
    }

    public function testARedirectToTheSameOriginIsFollowedByHand(): void
    {
        ScriptedHttpClient::reset([
            ['status' => 301, 'headers' => ['Location' => '/bags/new-satchel.html']],
            ['status' => 302, 'headers' => ['location' => 'satchel.html?x=1']],
            ['status' => 200, 'body' => self::PAGE],
        ]);

        $page = $this->reader()->read($this->storeId(), '/bags/field-satchel.html');

        self::assertSame(
            ['http://shop.test/bags/field-satchel.html', 'http://shop.test/bags/new-satchel.html',
                'http://shop.test/bags/satchel.html?x=1'],
            array_column(ScriptedHttpClient::requests(), 'url')
        );
        self::assertSame('/bags/satchel.html?x=1', $page->getPath());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function elsewhere(): array
    {
        return [
            'another host' => ['https://evil.example/'],
            'another host without a scheme' => ['//evil.example/x'],
            'a backslash a browser reads as a slash' => ['/\\evil.example/x'],
            'another scheme on the same host' => ['https://shop.test/'],
            'another port on the same host' => ['http://shop.test:8080/'],
            'a user name before the host' => ['http://shop.test@evil.example/'],
            'a user name on the same host' => ['http://user@shop.test/'],
            'a scheme that is not http' => ['ftp://shop.test/file'],
            'a script address' => ['javascript:alert(1)'],
            'a target with a line break' => ["/x\r\nSet-Cookie: a=b"],
        ];
    }

    #[DataProvider('elsewhere')]
    public function testARedirectAwayFromTheStoreViewsOriginIsRefusedAndNotRequested(string $location): void
    {
        ScriptedHttpClient::reset([['status' => 302, 'headers' => ['Location' => $location]]]);

        $failure = $this->failure('/');

        self::assertSame(ReadFailure::RedirectElsewhere, $failure->getReason());
        self::assertSame(302, $failure->getHttpStatus());
        self::assertCount(1, ScriptedHttpClient::requests());
    }

    public function testNoMoreThanThreeRedirectsAreFollowed(): void
    {
        ScriptedHttpClient::reset(array_fill(0, 4, ['status' => 302, 'headers' => ['Location' => '/again']]));

        self::assertSame(ReadFailure::TooManyRedirects, $this->failure('/')->getReason());
        self::assertCount(4, ScriptedHttpClient::requests());
    }

    public function testABodyLargerThanTheLimitIsRefused(): void
    {
        ScriptedHttpClient::reset([['status' => 200, 'body' => str_repeat('a', self::FIVE_MB + 1)]]);

        self::assertSame(ReadFailure::TooLarge, $this->failure('/')->getReason());
    }

    public function testTheTransferIsAbortedAsSoonAsItPassesTheLimit(): void
    {
        ScriptedHttpClient::reset([['progress' => [1024, self::FIVE_MB, self::FIVE_MB + 1, self::FIVE_MB * 2]]]);

        self::assertSame(ReadFailure::TooLarge, $this->failure('/')->getReason());
        self::assertSame([0, 0, 1], ScriptedHttpClient::progressAnswers());
    }

    public function testAnAnswerOtherThanSuccessIsAFailureWithItsStatus(): void
    {
        ScriptedHttpClient::reset([['status' => 503, 'body' => 'Service Unavailable']]);

        $failure = $this->failure('/');

        self::assertSame([ReadFailure::HttpStatus, 503], [$failure->getReason(), $failure->getHttpStatus()]);
    }

    public function testARequestTheClientGivesUpOnSaysWhetherItTimedOut(): void
    {
        ScriptedHttpClient::reset([
            ['fail' => 'Could not resolve host: shop.test'],
            ['fail' => 'Connection timed out after 5001 milliseconds'],
        ]);

        self::assertSame(ReadFailure::Unreachable, $this->failure('/')->getReason());
        self::assertSame(ReadFailure::Timeout, $this->failure('/')->getReason());
    }

    public function testNoRequestStartsAfterTheDeadlineOfTheWholeRead(): void
    {
        $reader = $this->reader();
        ScriptedHttpClient::reset(
            [
                ['status' => 302, 'headers' => ['Location' => '/a'], 'advance' => 9.5],
                ['status' => 302, 'headers' => ['Location' => '/b'], 'advance' => 5.6],
            ],
            $this->clock
        );

        try {
            $reader->read($this->storeId(), '/');
            self::fail('A read past its deadline went on.');
        } catch (StorefrontReadException $e) {
            self::assertSame(ReadFailure::Timeout, $e->getReason());
        }
        $requests = ScriptedHttpClient::requests();
        self::assertCount(2, $requests);
        self::assertSame(5500, $requests[1]['options'][CURLOPT_TIMEOUT_MS], 'The second request gets what is left.');
    }

    public function testAStoreViewWhoseAddressIsNotHttpIsNotRead(): void
    {
        ScriptedHttpClient::reset([]);
        $this->addresses->useBase('ftp://shop.test/');

        self::assertSame(ReadFailure::NotHttp, $this->failure('/')->getReason());
        self::assertSame([], ScriptedHttpClient::requests());
    }

    /**
     * The failure of a read that must fail
     *
     * @param string $path
     * @return StorefrontReadException
     */
    private function failure(string $path): StorefrontReadException
    {
        try {
            $this->reader()->read($this->storeId(), $path);
        } catch (StorefrontReadException $e) {
            return $e;
        }
        self::fail('The read did not fail.');
    }

    /**
     * The reader as the container builds it, its client factory making scripted clients and its clock in the test's
     * hands
     *
     * @param StoreAddresses|null $addresses the store views' addresses; the known host when not given
     * @return StorefrontReaderInterface
     */
    private function reader(?StoreAddresses $addresses = null): StorefrontReaderInterface
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->clock = new FakeMonotonicClock();
        $reader = $objectManager->create(HttpStorefrontReader::class, [
            'clients' => $objectManager->create(ClientFactory::class, ['instanceName' => ScriptedHttpClient::class]),
            'clock' => $this->clock,
            'addresses' => $addresses ?? $this->addresses,
        ]);
        self::assertInstanceOf(StorefrontReaderInterface::class, $reader);

        return $reader;
    }

    /**
     * The default store view's ID
     *
     * @return int
     */
    private function storeId(): int
    {
        return (int) $this->storeManager()->getDefaultStoreView()?->getId();
    }

    /**
     * The store manager
     *
     * @return StoreManagerInterface
     */
    private function storeManager(): StoreManagerInterface
    {
        $stores = Bootstrap::getObjectManager()->get(StoreManagerInterface::class);
        self::assertInstanceOf(StoreManagerInterface::class, $stores);

        return $stores;
    }
}
