<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Library;

use Hryvinskyi\RichSnippets\Model\Library\CopyNames;
use Hryvinskyi\RichSnippets\Model\Library\Question\ProfileLinks;
use PHPUnit\Framework\TestCase;

/**
 * Profile links are found in HTML by host, and a copy takes the first free name of its sequence
 */
class ProfileLinksAndCopyNamesTest extends TestCase
{
    private const HOSTS = ['instagram.com', 'x.com'];

    public function testOnlyAbsoluteLinksOnAProfileHostOrItsSubdomainsCount(): void
    {
        $html = '<a href="https://www.instagram.com/linden">I</a>'
            . "<a href='https://x.com/linden?ref=a&amp;b=1'>X</a>"
            . '<a href="https://notx.com/linden">no</a>'
            . '<a href="/about-us">no</a>'
            . '<a href="mailto:hi@x.com">no</a>'
            . '<a href="{{store url=\'x\'}}">no</a>'
            . '<a HREF = "https://www.instagram.com/linden">again</a>';

        self::assertSame(
            ['https://www.instagram.com/linden', 'https://x.com/linden?ref=a&b=1'],
            (new ProfileLinks())->links($html, self::HOSTS)
        );
    }

    public function testHtmlWithoutLinksHasNone(): void
    {
        self::assertSame([], (new ProfileLinks())->links('<p>No links here.</p>', self::HOSTS));
    }

    public function testAFreeNameIsKeptAndATakenOneGetsTheFirstFreeNumberFromTwo(): void
    {
        $names = new CopyNames();

        self::assertSame('Product', $names->next('Product', ['Organization' => true]));
        self::assertSame('Product 2', $names->next('Product', ['Product' => true]));
        self::assertSame('Product 4', $names->next('Product', ['Product' => true, 'Product 2' => true,
            'Product 3' => true]));
        self::assertSame('Product 2', $names->next('Product', ['Product' => true, 'Product 3' => true]));
    }
}
