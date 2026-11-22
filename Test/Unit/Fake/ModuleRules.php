<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Hryvinskyi\RichSnippets\Model\Config\KeptDefinitions;
use Hryvinskyi\RichSnippets\Model\Requirement\ResultTypeRulePool;
use Hryvinskyi\RichSnippets\Model\Requirement\RuleDefinitionParser;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * The rule pool over the rule files this module ships, read the way a rule source reads them
 *
 * The files are data the pool and the checker read; tests exercise the reading and checking, never the file text.
 */
class ModuleRules
{
    public const CODES = ['merchant_listing', 'review_stars', 'product_variants', 'breadcrumb', 'organization',
        'return_policy', 'site_name', 'faq', 'shipping_policy'];

    /**
     * A pool of every rule file of the module, in the order di.xml lists them
     *
     * @return ResultTypeRulePool
     */
    public static function pool(): ResultTypeRulePool
    {
        $sources = [];
        foreach (self::CODES as $code) {
            $sources[$code] = self::source($code);
        }

        return new ResultTypeRulePool(
            new RuleDefinitionParser(new RuleFactoryFake()),
            new KeptDefinitions(new InMemoryCache(), new Json()),
            $sources
        );
    }

    /**
     * A source of one of the module's rule files
     *
     * @param string $code
     * @return DefinitionSource
     */
    public static function source(string $code): DefinitionSource
    {
        $file = dirname(__DIR__, 3) . '/etc/rich_snippets/result_types/' . $code . '.json';
        $definition = json_decode((string) file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($definition)) {
            throw new \RuntimeException('The rule file ' . $file . ' holds no object.');
        }

        return new DefinitionSource($definition);
    }
}
