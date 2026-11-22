<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Requirement;

use Hryvinskyi\JsonTemplate\Api\RecordingTraceFactoryInterface;
use Hryvinskyi\JsonTemplate\Api\ScopeFactoryInterface;
use Hryvinskyi\JsonTemplate\Api\TemplateEngineInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureVerdict;
use Hryvinskyi\RichSnippets\Api\Data\MissingPropertyInterface;
use Hryvinskyi\RichSnippets\Api\Data\OutputCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;
use Hryvinskyi\RichSnippets\Api\Data\ResultTypeRuleInterface;
use Hryvinskyi\RichSnippets\Api\RequirementCheckerInterface;
use Hryvinskyi\RichSnippets\Api\ResultTypeRulePoolInterface;
use Hryvinskyi\RichSnippets\Model\Cache\Type\RichSnippetsCache;
use Hryvinskyi\RichSnippets\Model\Requirement\ResultTypeRulePool;
use Hryvinskyi\RichSnippets\Model\Requirement\Source\ModuleRuleFile;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\ObjectManager\ConfigInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The rule pool is wired from di.xml and loads every rule file the module ships; a rule file another module ships
 * joins it through a source item of its own; the checker resolves through the container and reads the snippets'
 * own engine's compiled templates and recording traces
 */
class RequirementRulesTest extends TestCase
{
    private const FIXTURE_MODULE = 'Hryvinskyi_RichSnippetsRuleFixture';
    private const FIXTURE_SOURCE = 'HryvinskyiRichSnippetsTestFixtureEventRule';
    private const ENGINE = 'HryvinskyiRichSnippetsTemplateEngine';

    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @var array<mixed>|null the pool's sources before a test added one
     */
    private ?array $sources = null;

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->get(RichSnippetsCache::class)->clean();
    }

    protected function tearDown(): void
    {
        if ($this->sources !== null) {
            $this->objectManager->configure(
                [ResultTypeRulePool::class => ['arguments' => ['sources' => $this->sources]]]
            );
        }
        $this->get(RichSnippetsCache::class)->clean();
    }

    public function testThePoolLoadsEveryRuleFileTheModuleShips(): void
    {
        $pool = $this->get(ResultTypeRulePoolInterface::class);

        self::assertSame(
            ['merchant_listing', 'review_stars', 'product_variants', 'breadcrumb', 'organization', 'return_policy',
                'site_name', 'faq', 'shipping_policy'],
            $this->codes($pool->all())
        );
        self::assertSame(['merchant_listing', 'review_stars'], $this->codes($pool->forType('Product')));
        self::assertSame(['organization', 'return_policy'], $this->codes($pool->forType('Organization')));
    }

    public function testARuleFileAnotherModuleShipsJoinsThePool(): void
    {
        $this->registerFixtureModule();
        $config = $this->get(ConfigInterface::class);
        $arguments = $config->getArguments(ResultTypeRulePool::class);
        $sources = $arguments['sources'] ?? null;
        self::assertIsArray($sources);
        $this->sources = $sources;
        $this->objectManager->configure([
            self::FIXTURE_SOURCE => [
                'type' => ModuleRuleFile::class,
                'arguments' => [
                    'moduleName' => self::FIXTURE_MODULE,
                    'fileName' => 'fixture_event.json',
                ],
            ],
            ResultTypeRulePool::class => [
                'arguments' => ['sources' => $sources + ['fixture_event' => ['instance' => self::FIXTURE_SOURCE]]],
            ],
        ]);

        $pool = $this->objectManager->create(ResultTypeRulePool::class);

        self::assertInstanceOf(ResultTypeRulePool::class, $pool);
        self::assertSame(['fixture_event'], $this->codes($pool->forType('Event')));
        self::assertCount(10, $pool->all());
        $rule = $pool->get('fixture_event');
        self::assertNotNull($rule);
        self::assertSame('Event listing', $rule->getLabel());
        self::assertSame(['name', 'startDate', 'location.name'], array_map(
            static fn ($requirement): string => $requirement->getName(),
            $rule->getRequired()
        ));
    }

    public function testATemplateOfTheSnippetsEngineWithoutAnOfferMissesItsPriceAndCurrency(): void
    {
        $template = $this->engine()->compile(
            '{"@context": "https://schema.org", "@type": "Product", "name": "{{ product.name }}",'
            . ' "image": "{{ product.image | url }}", "sku": "{{ product.sku }}"}'
        );

        $missing = $this->get(RequirementCheckerInterface::class)->checkTemplate($template);

        self::assertSame(
            [
                ['merchant_listing', 'offers.price', PropertyTone::Bad],
                ['merchant_listing', 'offers.priceCurrency', PropertyTone::Bad],
                ['review_stars', 'aggregateRating.ratingValue', PropertyTone::Note],
                ['review_stars', 'aggregateRating.reviewCount', PropertyTone::Note],
            ],
            array_map(
                static fn (MissingPropertyInterface $m): array => [$m->getRuleCode(), $m->getPath(), $m->getTone()],
                $missing
            )
        );
    }

    public function testAnEvaluationTraceNamesWhatWasLeftOutAndTheFieldsItRead(): void
    {
        $source = '{"@type": "Product", "name": "{{ product.name }}", "image": "{{ product.image }}",'
            . ' "gtin13": "{{ product.ean }}",'
            . ' "offers": {"@type": "Offer", "price": "{{ product.price }}", "priceCurrency": "EUR"},'
            . ' "aggregateRating": {"@type": "AggregateRating", "ratingValue": "{{ reviews.rating }}",'
            . ' "reviewCount": "{{ reviews.count }}"}}';
        $scope = $this->get(ScopeFactoryInterface::class)->create([
            'product' => ['name' => 'Card sleeve', 'image' => 'https://shop.example/sleeve.jpg', 'ean' => null,
                'price' => '35.00'],
            'reviews' => ['rating' => null, 'count' => null],
        ]);
        $trace = $this->get(RecordingTraceFactoryInterface::class)->create();
        $output = $this->engine()->evaluate($source, $scope, $trace);
        self::assertIsArray($output);

        $check = $this->get(RequirementCheckerInterface::class)->checkOutput('Product', $output, $trace);

        self::assertSame(FeatureVerdict::Met, $this->feature($check, 'merchant_listing')->getVerdict());
        self::assertSame(FeatureVerdict::LeftOut, $this->feature($check, 'review_stars')->getVerdict());
        $leftOut = [];
        foreach ($check->getLeftOut() as $property) {
            $leftOut[$property->getPath()] = $property->getSources();
        }
        self::assertSame(['product.ean'], $leftOut['gtin13'] ?? null);
        self::assertArrayHasKey('aggregateRating', $leftOut);
        self::assertArrayNotHasKey('aggregateRating.ratingValue', $leftOut);
        $sources = [];
        foreach ($this->feature($check, 'merchant_listing')->getMissingRecommended() as $missing) {
            $sources[$missing->getPath()] = $missing->getSources();
        }
        self::assertSame(['product.ean'], $sources['gtin13'] ?? null, 'The GTIN group is named by the member written.');
    }

    /**
     * Make the fixture module's directory known, as an installed module's registration does
     *
     * @return void
     */
    private function registerFixtureModule(): void
    {
        $registrar = $this->get(ComponentRegistrar::class);
        if ($registrar->getPath(ComponentRegistrar::MODULE, self::FIXTURE_MODULE) === null) {
            ComponentRegistrar::register(
                ComponentRegistrar::MODULE,
                self::FIXTURE_MODULE,
                __DIR__ . '/_files/RuleFixtureModule'
            );
        }
    }

    /**
     * The snippets' own template engine
     *
     * @return TemplateEngineInterface
     */
    private function engine(): TemplateEngineInterface
    {
        $engine = $this->objectManager->get(self::ENGINE);
        self::assertInstanceOf(TemplateEngineInterface::class, $engine);

        return $engine;
    }

    /**
     * The check of one kind of result
     *
     * @param OutputCheckInterface $check
     * @param string $code
     * @return FeatureCheckInterface
     */
    private function feature(OutputCheckInterface $check, string $code): FeatureCheckInterface
    {
        foreach ($check->getFeatures() as $feature) {
            if ($feature->getRule()->getCode() === $code) {
                return $feature;
            }
        }
        self::fail('The block was not checked against "' . $code . '".');
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

    /**
     * A service from the container
     *
     * @template T of object
     * @param class-string<T> $type
     * @return T
     */
    private function get(string $type): object
    {
        $service = $this->objectManager->get($type);
        self::assertInstanceOf($type, $service);

        return $service;
    }
}
