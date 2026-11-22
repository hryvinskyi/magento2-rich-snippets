<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Transfer;

use Hryvinskyi\RichSnippets\Model\Transfer\Portable\LabelledKeys;
use PHPUnit\Framework\TestCase;

/**
 * A row ID from another shop is kept only when it names the same thing here; otherwise the label decides, and a
 * label that names nothing or several things here resolves to nothing
 */
class LabelledKeysTest extends TestCase
{
    private const LABELS = ['4' => 'Default', '9' => 'Bags', '11' => 'Shoes', '12' => 'Shoes'];

    public function testTheKeyIsKeptWhenItsLabelHereIsTheSame(): void
    {
        self::assertSame('9', (new LabelledKeys())->resolve('9', 'Bags', self::LABELS));
        self::assertSame('12', (new LabelledKeys())->resolve('12', 'Shoes', self::LABELS));
    }

    public function testAKeyNamingSomethingElseHereIsResolvedByTheLabel(): void
    {
        self::assertSame('9', (new LabelledKeys())->resolve('4', 'Bags', self::LABELS));
        self::assertSame('4', (new LabelledKeys())->resolve('77', 'Default', self::LABELS));
    }

    public function testALabelNoneOrSeveralHaveResolvesToNothing(): void
    {
        self::assertNull((new LabelledKeys())->resolve('9', 'Belts', self::LABELS));
        self::assertNull((new LabelledKeys())->resolve('4', 'Shoes', self::LABELS));
    }

    public function testAMissingLabelResolvesToNothingEvenForAKeyThatExists(): void
    {
        self::assertNull((new LabelledKeys())->resolve('9', '', self::LABELS));
        self::assertNull((new LabelledKeys())->resolve('5', '', ['5' => '']));
    }
}
