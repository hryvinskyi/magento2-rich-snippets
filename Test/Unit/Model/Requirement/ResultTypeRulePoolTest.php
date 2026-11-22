<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Requirement;

use Hryvinskyi\RichSnippets\Api\Data\FeatureVerdict;
use Hryvinskyi\RichSnippets\Api\Data\GoogleStatus;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;
use Hryvinskyi\RichSnippets\Api\Data\ResultTypeRuleInterface;
use Hryvinskyi\RichSnippets\Api\Exception\MalformedRuleException;
use Hryvinskyi\RichSnippets\Model\Config\KeptDefinitions;
use Hryvinskyi\RichSnippets\Model\Requirement\ResultTypeRulePool;
use Hryvinskyi\RichSnippets\Model\Requirement\RuleDefinitionParser;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\DefinitionSource;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\InMemoryCache;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\ModuleRules;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\RuleFactoryFake;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The pool builds the rules of its sources in their order, finds the rules of a type with their anchors, keeps the
 * definitions in the cache for the next process, and refuses a malformed definition when the rules are loaded
 */
class ResultTypeRulePoolTest extends TestCase
{
    public function testEveryRuleFileOfTheModuleLoadsInThePoolsOrder(): void
    {
        $codes = array_map(
            static fn (ResultTypeRuleInterface $rule): string => $rule->getCode(),
            ModuleRules::pool()->all()
        );

        self::assertSame(ModuleRules::CODES, $codes);
    }

    public function testTheRulesOfATypeComeWithTheAnchorTheyApplyAt(): void
    {
        $pool = ModuleRules::pool();

        self::assertSame(['merchant_listing', 'review_stars'], $this->codes($pool->forType('Product')));
        self::assertSame(['product_variants'], $this->codes($pool->forType('ProductGroup')));
        self::assertSame(['organization', 'return_policy'], $this->codes($pool->forType('OnlineStore')));
        self::assertSame(['return_policy'], $this->codes($pool->forType('MerchantReturnPolicy')));
        self::assertSame(['shipping_policy'], $this->codes($pool->forType('ShippingService')));
        self::assertSame([], $pool->forType('Thing'));
        $policy = $pool->get('return_policy');
        self::assertNotNull($policy);
        self::assertSame('hasMerchantReturnPolicy', $policy->anchorFor('OnlineStore'));
        self::assertSame('', $policy->anchorFor('MerchantReturnPolicy'));
        self::assertNull($policy->anchorFor('Product'));
    }

    public function testRuleFlagsDecideTheToneOfAMissingRequiredPropertyAndTheVerdict(): void
    {
        $pool = ModuleRules::pool();
        $listing = $pool->get('merchant_listing');
        $stars = $pool->get('review_stars');
        $faq = $pool->get('faq');
        self::assertNotNull($listing);
        self::assertNotNull($stars);
        self::assertNotNull($faq);

        self::assertSame(PropertyTone::Bad, $listing->getMissingRequiredTone());
        self::assertSame(PropertyTone::Note, $stars->getMissingRequiredTone());
        self::assertSame(PropertyTone::Note, $faq->getMissingRequiredTone());
        self::assertSame(GoogleStatus::Gone, $faq->getGoogleStatus());
        self::assertSame(FeatureVerdict::NotMet, $listing->verdictFor(4));
        self::assertSame(FeatureVerdict::LeftOut, $stars->verdictFor(2));
        self::assertSame(FeatureVerdict::NotMet, $stars->verdictFor(1));
        self::assertSame(FeatureVerdict::Met, $stars->verdictFor(0));
        self::assertTrue($listing->getRecommended()[2]->isAnyOf());
        self::assertSame('gtin', $listing->getRecommended()[2]->getName());
    }

    public function testTheNextProcessReadsTheRulesFromTheCacheNotTheSources(): void
    {
        $cache = new InMemoryCache();
        $first = ModuleRules::source('site_name');
        $this->pool(['site_name' => $first], $cache)->all();
        $second = ModuleRules::source('site_name');

        $rules = $this->pool(['site_name' => $second], $cache)->all();

        self::assertSame(1, $first->reads);
        self::assertSame(0, $second->reads);
        self::assertSame('Site name', $rules[0]->getLabel());
    }

    public function testRulesKeptForAnotherSetOfCodesAreNotUsed(): void
    {
        $cache = new InMemoryCache();
        $this->pool(['site_name' => ModuleRules::source('site_name')], $cache)->all();
        $added = ModuleRules::source('faq');

        $rules = $this->pool(['site_name' => ModuleRules::source('site_name'), 'faq' => $added], $cache)->all();

        self::assertSame(1, $added->reads);
        self::assertCount(2, $rules);
    }

    public function testAMalformedDefinitionFailsTheLoadAndNothingIsKept(): void
    {
        $cache = new InMemoryCache();
        $pool = $this->pool(
            ['site_name' => ModuleRules::source('site_name'), 'broken' => new DefinitionSource(['label' => 'x'])],
            $cache
        );

        try {
            $pool->forType('WebSite');
            self::fail('A malformed definition was loaded.');
        } catch (MalformedRuleException $e) {
            self::assertStringContainsString('"broken"', $e->getMessage());
        }
        self::assertSame([], $cache->identifiers());
    }

    /**
     * @return array<string, array{array<mixed>, string}>
     */
    public static function malformedDefinitions(): array
    {
        $valid = [
            'label' => 'Event',
            'phrase' => 'an event',
            'appliesTo' => [['type' => 'Event']],
            'required' => ['name'],
            'texts' => ['requirement' => 'a name', 'met' => 'A name.'],
        ];

        return [
            'an unknown key' => [$valid + ['requird' => []], 'requird'],
            'an unknown text' => [
                array_replace($valid, ['texts' => ['requirement' => 'a', 'met' => 'A', 'x' => 'y']]),
                'texts.x',
            ],
            'no texts' => [array_diff_key($valid, ['texts' => true]), 'texts'],
            'no met text' => [array_replace($valid, ['texts' => ['requirement' => 'a']]), 'texts.met'],
            'a status Google does not have' => [$valid + ['googleStatus' => 'maybe'], 'googleStatus'],
            'an optional flag that is not a boolean' => [$valid + ['optional' => 'yes'], 'optional'],
            'no type' => [array_replace($valid, ['appliesTo' => []]), 'appliesTo'],
            'a type named twice' => [
                array_replace($valid, ['appliesTo' => [['type' => 'Event'], ['type' => 'Event']]]),
                'appliesTo[1].type',
            ],
            'a name that is not a type' => [array_replace($valid, ['appliesTo' => [['type' => 'event']]]), 'event'],
            'an anchor that is not a path' => [
                array_replace($valid, ['appliesTo' => [['type' => 'Event', 'at' => 'a b']]]),
                'a b',
            ],
            'a path of another shape' => [array_replace($valid, ['required' => ['offers..price']]), 'required[0]'],
            'an any-of group of one' => [
                array_replace($valid, ['recommended' => [['anyOf' => ['gtin']]]]),
                'recommended[0].anyOf',
            ],
            'an entry that is a number' => [array_replace($valid, ['required' => [3]]), 'required[0]'],
            'requirements that are not a list' => [array_replace($valid, ['required' => ['a' => 'name']]), 'required'],
            'an empty label' => [array_replace($valid, ['label' => ' ']), 'label'],
            'an entry with a path and a group' => [
                array_replace($valid, ['required' => [['path' => 'name', 'anyOf' => ['a', 'b']]]]),
                'required[0]',
            ],
            'an entry with neither a path nor a group' => [
                array_replace($valid, ['required' => [['missing' => 'No name']]]),
                'required[0]',
            ],
            'an entry with an unknown key' => [
                array_replace($valid, ['required' => [['path' => 'name', 'title' => 'No name']]]),
                'required[0].title',
            ],
            'a title that is not text' => [
                array_replace($valid, ['required' => [['path' => 'name', 'missing' => 3]]]),
                'required[0].missing',
            ],
            'a blank title' => [
                array_replace($valid, ['required' => [['path' => 'name', 'missing' => ' ']]]),
                'required[0]',
            ],
            'a reason that is not text' => [
                array_replace($valid, ['recommended' => [['path' => 'image', 'why' => ['x']]]]),
                'recommended[0].why',
            ],
        ];
    }

    /**
     * @param array<mixed> $definition
     * @param string $named
     */
    #[DataProvider('malformedDefinitions')]
    public function testAMalformedDefinitionIsRefusedNamingWhatIsWrong(array $definition, string $named): void
    {
        $this->expectException(MalformedRuleException::class);
        $named = preg_quote($named, '/');
        $this->expectExceptionMessageMatches('/"event".*' . $named . '|' . $named . '.*"event"/s');

        (new RuleDefinitionParser(new RuleFactoryFake()))->parse('event', $definition);
    }

    public function testAWellFormedDefinitionFromAnotherModuleBecomesARule(): void
    {
        $rule = (new RuleDefinitionParser(new RuleFactoryFake()))->parse('event', [
            'label' => 'Event',
            'phrase' => 'an event',
            'appliesTo' => [['type' => 'Event'], ['type' => 'Place', 'at' => 'event']],
            'optional' => true,
            'googleStatus' => 'none',
            'required' => ['name', ['anyOf' => ['startDate', 'eventSchedule']]],
            'recommended' => ['location.name'],
            'texts' => ['requirement' => 'a name and a date', 'met' => 'A name and a date.', 'status' => 'No result.'],
        ]);

        self::assertSame(['Event' => '', 'Place' => 'event'], $rule->getAnchors());
        self::assertTrue($rule->isOptional());
        self::assertSame(GoogleStatus::None, $rule->getGoogleStatus());
        self::assertSame(['startDate', 'eventSchedule'], $rule->getRequired()[1]->getPaths());
        self::assertSame('No result.', $rule->getStatusText());
        self::assertSame('', $rule->getLeftOutText());
    }

    public function testAnEntryCanGiveTheTitleShownWhenItIsMissingAndWhyItIsWorthAdding(): void
    {
        $rule = (new RuleDefinitionParser(new RuleFactoryFake()))->parse('event', [
            'label' => 'Event',
            'phrase' => 'an event',
            'appliesTo' => [['type' => 'Event']],
            'required' => [
                ['path' => 'name', 'missing' => 'No name'],
                ['anyOf' => ['startDate', 'eventSchedule'], 'missing' => 'No date'],
                'location',
            ],
            'recommended' => [['path' => 'image'], ['path' => 'offers', 'why' => 'Shows the price.']],
            'texts' => ['requirement' => 'a name and a date', 'met' => 'A name and a date.'],
        ]);

        [$name, $date, $location] = $rule->getRequired();
        self::assertSame(['name'], $name->getPaths());
        self::assertSame('No name', $name->getMissingTitle());
        self::assertSame(['startDate', 'eventSchedule'], $date->getPaths());
        self::assertSame('No date', $date->getMissingTitle());
        self::assertSame('', $location->getMissingTitle(), 'A bare path gives no title.');
        self::assertSame('', $rule->getRecommended()[0]->getMissingTitle());
        self::assertSame('', $rule->getRecommended()[0]->getWhy());
        self::assertSame('Shows the price.', $rule->getRecommended()[1]->getWhy());
    }

    /**
     * A pool over sources and a cache
     *
     * @param array<string, DefinitionSource> $sources
     * @param InMemoryCache $cache
     * @return ResultTypeRulePool
     */
    private function pool(array $sources, InMemoryCache $cache): ResultTypeRulePool
    {
        return new ResultTypeRulePool(
            new RuleDefinitionParser(new RuleFactoryFake()),
            new KeptDefinitions($cache, new Json()),
            $sources
        );
    }

    /**
     * The codes of rules, in order
     *
     * @param list<ResultTypeRuleInterface> $rules
     * @return list<string>
     */
    private function codes(array $rules): array
    {
        return array_map(static fn (ResultTypeRuleInterface $rule): string => $rule->getCode(), $rules);
    }
}
