<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check;

use Hryvinskyi\AdminUi\Api\Page\PluralPhrasesInterface;
use Hryvinskyi\RichSnippets\Api\Data\FindingInterface;
use Hryvinskyi\RichSnippets\Api\Data\ResultTypeRuleInterface;
use Hryvinskyi\RichSnippets\Api\FieldCatalogInterface;
use Hryvinskyi\RichSnippets\Api\ResultTypeRulePoolInterface;
use Hryvinskyi\RichSnippets\Model\Check\Tone\ToneWordingInterface;
use Hryvinskyi\RichSnippets\Model\Wording\CountWords;

/**
 * Says what a finding is about, why it matters and what to do, in the admin's language
 *
 * A finding about the shop's data is named after the field the template read for the property ("EAN is empty"),
 * unless its rule gives the missing requirement a title of its own ("No image"); with neither, after the property's
 * path. A finding whose pages are all products counts products; any other counts pages.
 *
 * Why a gap matters and what to do about it are worded by the wording of the finding's tone in the pool; a finding
 * about a result left out as a whole says so, whatever its tone. A tone without a wording is an error in the
 * configuration and fails loudly.
 */
class FindingWording
{
    private const PRODUCT = 'product';

    /**
     * @param ResultTypeRulePoolInterface $rules
     * @param FieldCatalogInterface $fields
     * @param CountWords $counts
     * @param PluralPhrasesInterface $plurals
     * @param array<string,ToneWordingInterface> $tones by the tone's value
     */
    public function __construct(
        private readonly ResultTypeRulePoolInterface $rules,
        private readonly FieldCatalogInterface $fields,
        private readonly CountWords $counts,
        private readonly PluralPhrasesInterface $plurals,
        private readonly array $tones = []
    ) {
    }

    /**
     * What is missing, short enough for a row's title
     *
     * @param FindingInterface $finding
     * @return string
     */
    public function title(FindingInterface $finding): string
    {
        $rule = $this->rules->get($finding->getRuleCode());
        if ($finding->isResultLeftOut()) {
            return (string) __('%1 left out', __($rule?->getLabel() ?? $finding->getRuleCode()));
        }
        $title = $this->requirementTitle($rule, $finding->getPath());
        if ($title !== '') {
            return (string) __($title);
        }
        $field = $this->fieldLabel($finding);

        return $field === null
            ? (string) __('%1 is missing', $finding->getPath())
            : (string) __('%1 is empty', $field);
    }

    /**
     * Why the gap matters
     *
     * @param FindingInterface $finding
     * @return string
     * @throws \LogicException when no wording is configured for the finding's tone
     */
    public function description(FindingInterface $finding): string
    {
        $rule = $this->rules->get($finding->getRuleCode());
        $phrase = (string) __($rule?->getPhrase() ?? $finding->getRuleCode());
        if ($finding->isResultLeftOut()) {
            $text = $rule?->getLeftOutText() ?? '';

            return $text !== '' ? (string) __($text) : (string) __('%1 is left out. Nothing to fix.', $phrase);
        }

        return $this->tone($finding)->description($finding, $rule, $phrase);
    }

    /**
     * What to do about it
     *
     * @param FindingInterface $finding
     * @return string
     * @throws \LogicException when no wording is configured for the finding's tone
     */
    public function fix(FindingInterface $finding): string
    {
        if ($finding->isResultLeftOut()) {
            return (string) __('Nothing to fix.');
        }

        return $this->tone($finding)->fix($finding, $this->fieldLabel($finding));
    }

    /**
     * A number of the finding's pages, with the noun that fits them ("23 products", "1 page")
     *
     * @param FindingInterface $finding
     * @param int $count
     * @return string
     */
    public function count(FindingInterface $finding, int $count): string
    {
        return $this->allProducts($finding) ? $this->counts->products($count) : $this->counts->pages($count);
    }

    /**
     * That a number of the finding's pages were saved into a file: "23 products were saved as no-image.csv"
     *
     * @param FindingInterface $finding
     * @param int $count
     * @param string $fileName
     * @return string
     */
    public function saved(FindingInterface $finding, int $count, string $fileName): string
    {
        return $this->allProducts($finding)
            ? $this->plurals->sentence(
                $count,
                __('%1 product was saved as %2.', $count, $fileName),
                __('%1 products were saved as %2.', $count, $fileName)
            )
            : $this->plurals->sentence(
                $count,
                __('%1 page was saved as %2.', $count, $fileName),
                __('%1 pages were saved as %2.', $count, $fileName)
            );
    }

    /**
     * Whether every page of the finding is a product page
     *
     * @param FindingInterface $finding
     * @return bool
     */
    public function allProducts(FindingInterface $finding): bool
    {
        $kinds = array_keys(array_filter($finding->getPagesByKind()));

        return $kinds === [self::PRODUCT];
    }

    /**
     * The title the rule gives a missing requirement with the path; empty when it gives none
     *
     * @param ResultTypeRuleInterface|null $rule
     * @param string $path
     * @return string
     */
    private function requirementTitle(?ResultTypeRuleInterface $rule, string $path): string
    {
        foreach ([...($rule?->getRequired() ?? []), ...($rule?->getRecommended() ?? [])] as $requirement) {
            if (in_array($path, $requirement->getPaths(), true)) {
                return $requirement->getMissingTitle();
            }
        }

        return '';
    }

    /**
     * The label of the first field the template read for the property; null when none is a known field
     *
     * @param FindingInterface $finding
     * @return string|null
     */
    private function fieldLabel(FindingInterface $finding): ?string
    {
        foreach ($finding->getSources() as $source) {
            $field = $this->fields->getField($source);
            if ($field !== null && $field->getLabel() !== '') {
                return (string) __($field->getLabel());
            }
        }

        return null;
    }

    /**
     * The wording of the finding's tone
     *
     * @param FindingInterface $finding
     * @return ToneWordingInterface
     * @throws \LogicException when no wording is configured for the tone
     */
    private function tone(FindingInterface $finding): ToneWordingInterface
    {
        $tone = $finding->getTone()->value;
        $wording = $this->tones[$tone] ?? null;
        if (!$wording instanceof ToneWordingInterface) {
            throw new \LogicException(sprintf('No wording is configured for the tone "%s".', $tone));
        }

        return $wording;
    }
}
