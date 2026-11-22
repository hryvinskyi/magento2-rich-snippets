<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\I18n;

use Hryvinskyi\AdminUi\Api\Page\PagePhrasesInterface;
use Hryvinskyi\AdminUi\Api\Page\PluralCategoriesInterface;
use Hryvinskyi\RichSnippets\Model\Admin\AdminResources;
use Hryvinskyi\RichSnippets\Model\Admin\Editor\EditorPhrases;
use Hryvinskyi\RichSnippets\Model\Admin\Library\LibraryPhrases;
use Hryvinskyi\RichSnippets\Model\Admin\Overview\OverviewPhrases;
use Hryvinskyi\RichSnippets\Model\Admin\Snippets\SnippetsPhrases;
use Hryvinskyi\RichSnippets\Model\Admin\Test\TestPhrases;
use Hryvinskyi\RichSnippets\Model\Wording\CountWords;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\AsksAsTheSnippetsPage;
use Magento\Backend\Model\Locale\Manager as LocaleManager;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractBackendController;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The wording of every island page in Ukrainian, as the real admin prints it
 *
 * The administrator is put on the Ukrainian interface language the way the admin's own language switch does, and the
 * page is opened by the real admin. The same page's wording is then read in the shop's own language from the service
 * that words the page. Every phrase the page handed its island must differ from its English text, except the names
 * and codes that are the same in every language. A count text arrives in every form Ukrainian sorts whole numbers
 * into: the `one` and `other` keys English has too, and `few` and `many`, which the island picks by the count. An
 * admin page holds one island per request, so each test opens one page.
 */
#[AppArea('adminhtml'), AppIsolation(true), DbIsolation(true)]
class UkrainianPhrasesTest extends AbstractBackendController
{
    use AsksAsTheSnippetsPage;

    private const PAGE = 'backend/richsnippets/overview/index';
    private const LOCALE = 'uk_UA';
    private const SHOP_LOCALE = 'en_US';

    /**
     * @var string
     */
    protected $resource = AdminResources::VIEW;

    /**
     * @var string
     */
    protected $uri = self::PAGE;

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_GET;

    /**
     * Names and codes written the same in every language
     */
    private const SAME_IN_EVERY_LANGUAGE = [
        'Rich Snippets',
        'FAQ',
        'GTIN (EAN, UPC)',
        'Product',
        'Offer',
        'ID',
        'SKU',
        'Linden & Co.',
        'schema.org/%1',
    ];

    /**
     * @return array<string, array{string, string, class-string<PagePhrasesInterface>}> the admin path of each island
     *     page, the island's name and the service that words the page
     */
    public static function islandPages(): array
    {
        return [
            'overview' => [self::PAGE, 'rich-snippets-overview', OverviewPhrases::class],
            'snippets' => ['backend/richsnippets/snippets/index', 'rich-snippets-snippets', SnippetsPhrases::class],
            'library' => ['backend/richsnippets/library/index', 'rich-snippets-library', LibraryPhrases::class],
            'new snippet' => ['backend/richsnippets/snippets/new', 'rich-snippets-editor', EditorPhrases::class],
            'test' => ['backend/richsnippets/test/index', 'rich-snippets-test', TestPhrases::class],
        ];
    }

    /**
     * @param string $path
     * @param string $island
     * @param class-string<PagePhrasesInterface> $wording
     * @return void
     */
    #[DataProvider('islandPages')]
    public function testNoPhraseOfThePageStaysEnglish(string $path, string $island, string $wording): void
    {
        $this->switchInterfaceLanguage(self::LOCALE);
        $ukrainian = $this->phrasesOnThePage($path, $island);
        $this->switchInterfaceLanguage(self::SHOP_LOCALE);
        $english = $this->phrasesOf($wording);

        self::assertNotSame([], $english);
        self::assertSame(array_keys($english), array_keys(array_diff_key($ukrainian, $this->furtherForms($english))));
        self::assertSame(
            array_keys($this->furtherForms($english)),
            array_keys(array_intersect_key($ukrainian, $this->furtherForms($english))),
            'Every count text has its few and many forms on ' . $path
        );
        $untranslated = [];
        foreach ($english as $key => $text) {
            if ($ukrainian[$key] === $text && !$this->isSameInEveryLanguage($text)) {
                $untranslated[$key] = $text;
            }
        }
        self::assertSame([], $untranslated, 'Phrases still in English on ' . $path);
    }

    /**
     * @return void
     */
    public function testThePageIsWordedInTheLanguageOfTheAdministrator(): void
    {
        $this->switchInterfaceLanguage(self::LOCALE);
        $ukrainian = $this->phrasesOnThePage(self::PAGE, 'rich-snippets-overview');
        $this->switchInterfaceLanguage(self::SHOP_LOCALE);
        $english = $this->phrasesOf(OverviewPhrases::class);

        self::assertSame('Store view', $english['page.store.label'] ?? null);
        self::assertNotSame('Store view', $ukrainian['page.store.label'] ?? null);
        self::assertNotSame('', $ukrainian['page.store.label'] ?? '');
    }

    /**
     * The island picks a count text's form by the count: 3 and 5 take Ukrainian's few and many forms, which the page
     * document carries translated, and which differ from each other and from the English
     *
     * @return void
     */
    public function testACountTakesTheUkrainianFormOfItsNumber(): void
    {
        $this->switchInterfaceLanguage(self::LOCALE);
        $phrases = $this->phrasesOnThePage('backend/richsnippets/snippets/index', 'rich-snippets-snippets');
        $this->switchInterfaceLanguage(self::SHOP_LOCALE);

        $three = $this->counted($phrases, 'snippets.row.properties', 3);
        $five = $this->counted($phrases, 'snippets.row.properties', 5);

        self::assertStringStartsWith('3 ', $three);
        self::assertStringStartsWith('5 ', $five);
        self::assertNotSame($this->withoutNumber($three), $this->withoutNumber($five));
        self::assertNotSame('3 properties', $three);
        self::assertNotSame('5 properties', $five);
    }

    /**
     * A sentence the server builds with a count takes the form of its number too
     *
     * @return void
     */
    public function testAServerSentenceTakesTheUkrainianFormOfItsNumber(): void
    {
        $this->switchInterfaceLanguage(self::LOCALE);
        $counts = $this->testObjectManager()->get(CountWords::class);
        self::assertInstanceOf(CountWords::class, $counts);
        $pages = [1 => $counts->pages(1), 3 => $counts->pages(3), 5 => $counts->pages(5), 21 => $counts->pages(21)];
        $this->switchInterfaceLanguage(self::SHOP_LOCALE);

        $forms = array_map(fn (string $text): string => $this->withoutNumber($text), $pages);
        self::assertCount(3, array_unique($forms), 'one, few and many are three forms');
        self::assertSame($forms[1], $forms[21], 'Ukrainian 21 takes the form of 1');
        self::assertNotSame('5 pages', $pages[5]);
    }

    /**
     * A counted text without the number it starts with
     *
     * @param string $text
     * @return string
     */
    private function withoutNumber(string $text): string
    {
        return (string) preg_replace('/^\d+ /u', '', $text);
    }

    /**
     * The few and many keys Ukrainian adds for every count text the English phrases have
     *
     * @param array<string, string> $english
     * @return array<string, true>
     */
    private function furtherForms(array $english): array
    {
        $forms = [];
        foreach (array_keys($english) as $key) {
            if (str_ends_with($key, '.other') && isset($english[substr($key, 0, -strlen('other')) . 'one'])) {
                $base = substr($key, 0, -strlen('.other'));
                $forms[$base . '.few'] = true;
                $forms[$base . '.many'] = true;
            }
        }

        return $forms;
    }

    /**
     * A count text worded for a number as the island words it: the form of the number's category in Ukrainian, with
     * the number in place of `%1`
     *
     * @param array<string, string> $phrases
     * @param string $key
     * @param int $count
     * @return string
     */
    private function counted(array $phrases, string $key, int $count): string
    {
        $categories = $this->testObjectManager()->get(PluralCategoriesInterface::class);
        self::assertInstanceOf(PluralCategoriesInterface::class, $categories);
        $form = $phrases[$key . '.' . $categories->categoryOf(self::LOCALE, $count)] ?? null;
        self::assertIsString($form, $key . ' for ' . $count);

        return str_replace('%1', (string) $count, $form);
    }

    /**
     * Whether a phrase carries no wording of its own: a name or code, or nothing but placeholders and punctuation
     *
     * @param string $text
     * @return bool
     */
    private function isSameInEveryLanguage(string $text): bool
    {
        if (in_array($text, self::SAME_IN_EVERY_LANGUAGE, true)) {
            return true;
        }

        return preg_match('/\p{L}/u', (string) preg_replace('/%[A-Za-z0-9_]+/', '', $text)) !== 1;
    }

    /**
     * Put the signed-in administrator on an interface language, as the admin's own language switch does: the user's
     * interface locale, and the translator's dictionary for it
     *
     * @param string $locale
     * @return void
     */
    private function switchInterfaceLanguage(string $locale): void
    {
        $manager = $this->testObjectManager()->get(LocaleManager::class);
        self::assertInstanceOf(LocaleManager::class, $manager);
        $manager->switchBackendInterfaceLocale($locale);
    }

    /**
     * The phrases an island page handed its island, read from the page the real admin prints
     *
     * @param string $path
     * @param string $island
     * @return array<string, string>
     */
    private function phrasesOnThePage(string $path, string $island): array
    {
        $this->newExchange();
        $this->httpRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->follow($path);

        self::assertSame(200, $this->httpResponse()->getHttpResponseCode(), $path);
        $document = new \DOMDocument();
        $errors = libxml_use_internal_errors(true);
        $document->loadHTML((string) $this->httpResponse()->getBody());
        libxml_clear_errors();
        libxml_use_internal_errors($errors);
        $nodes = (new \DOMXPath($document))->query('//script[@data-role="' . $island . '-config"]');
        self::assertInstanceOf(\DOMNodeList::class, $nodes);
        $node = $nodes->item(0);
        self::assertInstanceOf(\DOMElement::class, $node, $island);
        $phrases = $this->at(json_decode($node->textContent, true), 'page', 'phrases');
        self::assertIsArray($phrases, $island);

        $texts = [];
        foreach ($phrases as $key => $text) {
            self::assertIsString($text, (string) $key);
            $texts[(string) $key] = $text;
        }

        return $texts;
    }

    /**
     * What the service that words a page says in the language active now
     *
     * @param class-string<PagePhrasesInterface> $wording
     * @return array<string, string>
     */
    private function phrasesOf(string $wording): array
    {
        $service = $this->testObjectManager()->get($wording);
        self::assertInstanceOf(PagePhrasesInterface::class, $service);

        return $service->getPhrases();
    }
}
