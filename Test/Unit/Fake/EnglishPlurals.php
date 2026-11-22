<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Hryvinskyi\AdminUi\Model\Page\IcuPluralCategories;
use Hryvinskyi\AdminUi\Model\Page\PluralPhrases;

/**
 * The admin kit's count texts for an admin working in English, by the CLDR rules of PHP's intl extension
 */
class EnglishPlurals extends PluralPhrases
{
    public function __construct()
    {
        parent::__construct(new AdminTranslator('en_US'), new IcuPluralCategories());
    }
}
