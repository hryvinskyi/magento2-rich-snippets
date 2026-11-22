<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Wording;

use Hryvinskyi\RichSnippets\Model\Wording\WordList;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\DictionaryRenderer;
use Magento\Framework\Phrase;
use Magento\Framework\Phrase\RendererInterface;
use PHPUnit\Framework\TestCase;

/**
 * Words joined the way a sentence lists them, every separator a reader sees coming from a translated phrase
 */
class WordListTest extends TestCase
{
    /**
     * The renderer phrases had before the test
     *
     * @var RendererInterface|null
     */
    private ?RendererInterface $renderer = null;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->renderer = Phrase::getRenderer();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        if ($this->renderer !== null) {
            Phrase::setRenderer($this->renderer);
        }
    }

    /**
     * @return void
     */
    public function testEnglishListsReadAsPlainSentences(): void
    {
        Phrase::setRenderer(new DictionaryRenderer([]));
        $list = new WordList();

        self::assertSame(
            ['', 'a', 'a and b', 'a, b and c', 'a, b, c and d'],
            [$list->join([]), $list->join(['a']), $list->join(['a', 'b']), $list->join(['a', 'b', 'c']),
                $list->join(['a', 'b', 'c', 'd'])]
        );
        self::assertSame(
            ['', 'a', 'a or b', 'a, b or c'],
            [$list->either([]), $list->either(['a']), $list->either(['a', 'b']), $list->either(['a', 'b', 'c'])]
        );
        self::assertSame(
            ['', 'a', 'a, b', 'a, b, c'],
            [$list->series([]), $list->series(['a']), $list->series(['a', 'b']), $list->series(['a', 'b', 'c'])]
        );
    }

    /**
     * @return void
     */
    public function testALanguageThatWritesAnotherListCommaGetsItInEveryJoin(): void
    {
        Phrase::setRenderer(new DictionaryRenderer([
            '%1, %2' => '%1; %2',
            '%1 and %2' => '%1 & %2',
            '%1 or %2' => '%1 | %2',
        ]));
        $list = new WordList();

        self::assertSame('a; b & c', $list->join(['a', 'b', 'c']));
        self::assertSame('a; b | c', $list->either(['a', 'b', 'c']));
        self::assertSame('a; b; c', $list->series(['a', 'b', 'c']));
    }
}
