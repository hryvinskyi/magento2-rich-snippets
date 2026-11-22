<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Admin\Library;

use Hryvinskyi\AdminSetupWizard\Api\Exception\InvalidWizardRequestException;
use Hryvinskyi\AdminUi\Api\Page\KeyedAdminUrlInterface;
use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\RichSnippets\Api\Exception\IdempotencyException;
use Hryvinskyi\RichSnippets\Api\Exception\IdempotencyReason;
use Hryvinskyi\RichSnippets\Api\Exception\LibraryException;
use Hryvinskyi\RichSnippets\Api\Exception\LibraryReason;
use Hryvinskyi\RichSnippets\Api\Exception\MalformedLibraryTemplateException;
use Hryvinskyi\RichSnippets\Model\Admin\Library\LibraryListing;
use Hryvinskyi\RichSnippets\Model\Admin\Library\LibraryPage;
use Hryvinskyi\RichSnippets\Model\Admin\Library\LibraryQuestions;
use Hryvinskyi\RichSnippets\Model\Admin\Library\LibraryRequest;
use Hryvinskyi\RichSnippets\Model\Admin\Library\LibraryWrites;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\FaultRecordingLogger;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\LibraryAnswersFixture;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\LibraryShop;
use Magento\Framework\App\RequestInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * The Library page's answers are written exactly as the contract files its browser code is tested against, and the
 * requests they read are refused when they have the wrong shape
 */
class LibraryAnswersContractTest extends TestCase
{
    private const CONTRACTS = __DIR__ . '/../../../../_contract/admin-pages/';

    public function testTheLibraryIsListedAsTheContractWritesIt(): void
    {
        self::assertSame($this->contract('library-list.json'), self::travelled(LibraryAnswersFixture::lists()));
    }

    public function testTheQuestionsAreAnsweredAsTheContractWritesThem(): void
    {
        self::assertSame(
            $this->contract('library-suggestions.json'),
            self::travelled(LibraryAnswersFixture::suggestions())
        );
    }

    public function testTheAddsAreAnsweredAsTheContractWritesThem(): void
    {
        self::assertSame($this->contract('library-add.json'), self::travelled(LibraryAnswersFixture::adds()));
    }

    public function testATemplateNamesTheBuildingBlocksItUsesAndWhetherTheShopHasEach(): void
    {
        $listing = new LibraryListing(LibraryShop::having(['return-policy']), LibraryShop::moduleTemplates());

        $organization = $listing->read()['templates'][0];

        self::assertSame('organization', $organization['code']);
        self::assertSame([
            ['code' => 'return-policy', 'name' => 'Return policy', 'inShop' => true],
            ['code' => 'postal-address', 'name' => 'Postal address', 'inShop' => false],
        ], $organization['uses']);
        self::assertSame([['code' => 'social_profiles', 'label' => 'Social profiles']], $organization['questions']);
    }

    public function testTheNextCopyOfATemplateTheShopHasIsNumbered(): void
    {
        $listing = new LibraryListing(LibraryShop::having(['product']), LibraryShop::moduleTemplates());

        $byCode = array_column($listing->read()['templates'], null, 'code');

        self::assertSame(['Product 2', true], [$byCode['product']['nextName'], $byCode['product']['added']]);
        self::assertSame(['Product with variants', false], [
            $byCode['product-variants']['nextName'],
            $byCode['product-variants']['added'],
        ]);
    }

    /**
     * @return array<string, array{0: array<mixed>, 1: string}>
     */
    public static function badBodies(): array
    {
        return [
            'no template' => [['answers' => [], 'idempotencyKey' => 'k'], 'template'],
            'answers as a list' => [['template' => 'product', 'answers' => ['x'], 'idempotencyKey' => 'k'], 'answers'],
            'an answer that is no key' => [
                ['template' => 'product', 'answers' => ['brand' => 3], 'idempotencyKey' => 'k'],
                'answers',
            ],
            'no idempotency key' => [['template' => 'product', 'answers' => []], 'idempotency_key'],
        ];
    }

    /**
     * @param array<mixed> $body
     * @param string $reason
     */
    #[DataProvider('badBodies')]
    public function testABodyOfTheWrongShapeIsRefusedNamingTheValue(array $body, string $reason): void
    {
        $writes = $this->writes(LibraryShop::having([]));

        try {
            $writes->add($body);
            self::fail('The body is refused.');
        } catch (InvalidWizardRequestException $e) {
            self::assertSame($reason, $e->getReason());
        }
    }

    /**
     * @return array<string, array{0: \Throwable, 1: string}>
     */
    public static function refusals(): array
    {
        return [
            'an unknown answer' => [
                new LibraryException(LibraryReason::AnswerUnknown, ['question' => 'brand', 'answer' => 'x']),
                'answer_unknown',
            ],
            'a key used for another write' => [new IdempotencyException(IdempotencyReason::KeyReused), 'key_reused'],
        ];
    }

    #[DataProvider('refusals')]
    public function testAnAddTheLibraryRefusesAnswers400WithItsReason(\Throwable $refusal, string $reason): void
    {
        $writes = $this->writes(LibraryShop::having([])->refusing($refusal));

        try {
            $writes->add(['template' => 'product', 'answers' => [], 'idempotencyKey' => 'k']);
            self::fail('The add is refused.');
        } catch (InvalidWizardRequestException $e) {
            self::assertSame($reason, $e->getReason());
        }
    }

    public function testACopyWhoseTemplateDoesNotCompileIsARefusalOfTheAnswer(): void
    {
        $writes = $this->writes(LibraryShop::having([])->refusing(new CompileException([])));

        $answer = $writes->addRecommended(['idempotencyKey' => 'k']);

        self::assertSame([], $answer['added']);
        self::assertSame(['reason' => 'template_invalid', 'params' => []], $answer['refused']);
    }

    public function testTheQuestionsOfATemplateTheLibraryDoesNotHaveAnswer400(): void
    {
        $questions = new LibraryQuestions(new LibraryRequest(), LibraryShop::having([]));

        try {
            $questions->of(['template' => 'recipe']);
            self::fail('The template is unknown.');
        } catch (InvalidWizardRequestException $e) {
            self::assertSame('template_unknown', $e->getReason());
        }
    }

    /**
     * @return array<string, array{0: mixed, 1: string|null}>
     */
    public static function addresses(): array
    {
        return [
            'a template the library has' => ['product', 'product'],
            'a template it does not have' => ['recipe', null],
            'no template' => [null, null],
        ];
    }

    #[DataProvider('addresses')]
    public function testThePageOpensTheDrawerOfTheTemplateItsAddressNames(mixed $asked, ?string $open): void
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnMap([['template', null, $asked]]);
        $urls = $this->createStub(KeyedAdminUrlInterface::class);
        $urls->method('getUrl')->willReturnArgument(0);
        $page = new LibraryPage(
            new LibraryListing(LibraryShop::having([]), LibraryShop::moduleTemplates()),
            $request,
            $urls,
            new NullLogger()
        );

        $data = $page->getData();

        self::assertSame($open, $data['open']);
        self::assertFalse($data['unavailable']);
        self::assertCount(13, $data['templates']);
        self::assertSame([
            'list' => 'hryvinskyi_richsnippets/library/list',
            'suggestions' => 'hryvinskyi_richsnippets/library/suggestions',
            'add' => 'hryvinskyi_richsnippets/library/add',
            'addRecommended' => 'hryvinskyi_richsnippets/library/addRecommended',
        ], $page->getUrls());
    }

    public function testAMalformedDefinitionLeavesThePageDrawnWithTheLibraryUnavailableAndTheFaultLogged(): void
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn('product');
        $logger = new FaultRecordingLogger();
        $fault = new MalformedLibraryTemplateException('The library template "product" has no "name".');
        $page = new LibraryPage(
            new LibraryListing(LibraryShop::having([])->broken($fault), LibraryShop::moduleTemplates()),
            $request,
            $this->createStub(KeyedAdminUrlInterface::class),
            $logger
        );

        self::assertSame(
            ['groups' => [], 'recommended' => [], 'templates' => [], 'open' => null, 'unavailable' => true],
            $page->getData()
        );
        self::assertSame([['error', $fault]], $logger->faults);
    }

    /**
     * @param LibraryShop $shop
     * @return LibraryWrites
     */
    private function writes(LibraryShop $shop): LibraryWrites
    {
        return new LibraryWrites(
            new LibraryRequest(),
            $shop,
            new LibraryListing($shop, LibraryShop::moduleTemplates())
        );
    }

    /**
     * A value as it reaches the browser and comes back decoded
     *
     * @param mixed $value
     * @return mixed
     */
    private static function travelled(mixed $value): mixed
    {
        return json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * A contract file, decoded
     *
     * @param string $file
     * @return mixed
     */
    private function contract(string $file): mixed
    {
        return json_decode((string) file_get_contents(self::CONTRACTS . $file), true, 512, JSON_THROW_ON_ERROR);
    }
}
