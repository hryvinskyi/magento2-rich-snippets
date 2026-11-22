<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Editor;

use Hryvinskyi\AdminUi\Api\Page\PluralPhrasesInterface;
use Hryvinskyi\JsonTemplate\Api\Data\TraceOutcome;
use Hryvinskyi\RichSnippets\Api\Data\DraftPreviewInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureVerdict;
use Hryvinskyi\RichSnippets\Api\Data\GoogleStatus;
use Hryvinskyi\RichSnippets\Api\Data\MissingPropertyInterface;
use Hryvinskyi\RichSnippets\Api\Data\OutputCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\PreviewState;
use Hryvinskyi\RichSnippets\Api\Data\ResultTypeRuleInterface;
use Hryvinskyi\RichSnippets\Model\Wording\WordList;

/**
 * Words the checks of a preview as the editor's Checks panel lists them
 *
 * Each row has a tone (`bad`, `warn`, `note`, `ok`), a title, a text and, where the template can take the missing
 * property back, an action naming it, with its icon; the row of a sample that does not meet the conditions quotes
 * them and its action, which names no property, opens the Pages section. An action's `value` and `before` (the
 * property as a template part, and the properties it stands before) are left empty here: CheckActionValues fills
 * them from the library. A required property whose object is
 * missing altogether is named by that object (`offers`), which is what has to be added; one missing inside a
 * present object is named by its path. A result Google no longer shows says so and nothing more. Only properties
 * whose value is empty on the page are listed as left out: a property whose condition is false is meant to be left
 * out.
 *
 * @phpstan-type Action array{label: string, path: string|null, icon: string, value: string|null, before: list<string>}
 * @phpstan-type Row array{tone: string, title: string, text: string, action: Action|null}
 */
class PreviewChecks
{
    private const OK = 'ok';
    private const WARN = 'warn';
    private const NOTE = 'note';
    private const ADD_ICON = 'add';
    private const PAGES_ICON = 'arrow_forward';

    /**
     * @param PluralPhrasesInterface $plurals
     * @param WordList $words
     */
    public function __construct(
        private readonly PluralPhrasesInterface $plurals,
        private readonly WordList $words
    ) {
    }

    /**
     * The rows of a preview's checks
     *
     * @param DraftPreviewInterface $preview
     * @param string $type the draft's schema.org type
     * @param string $sample the sample page's name
     * @return list<Row>
     */
    public function rows(DraftPreviewInterface $preview, string $type, string $sample): array
    {
        $check = $preview->getCheck();

        return match ($preview->getState()) {
            PreviewState::NoType => [$this->row(
                self::NOTE,
                __('Choose a type first'),
                __('The checks follow the type: a Product is compared with what Google needs for a product result.')
            )],
            PreviewState::ConditionsNotMet => [$this->notOutput($preview, $sample)],
            PreviewState::Output => $check === null
                ? []
                : $this->outputRows($check, $preview->getOutput() ?? [], $type, $sample),
            PreviewState::Invalid, PreviewState::Empty => [],
        };
    }

    /**
     * Why the sample page gets no block, and what it gets instead
     *
     * @param DraftPreviewInterface $preview
     * @param string $sample
     * @return Row
     */
    private function notOutput(DraftPreviewInterface $preview, string $sample): array
    {
        $instead = $preview->getInstead()[0] ?? null;

        return $this->row(
            self::NOTE,
            __('Not output for %1', $sample),
            __('The conditions in Pages ask for “%1”.', $preview->getConditionText()) . ' ' . ($instead === null
                ? __('No snippet of this kind is output there.')
                : __('It gets the “%1” snippet instead.', $instead->getName())),
            $this->action((string) __('See the conditions'), null, self::PAGES_ICON)
        );
    }

    /**
     * The rows of a block written on the page
     *
     * @param OutputCheckInterface $check
     * @param array<mixed> $output
     * @param string $type
     * @param string $sample
     * @return list<Row>
     */
    private function outputRows(OutputCheckInterface $check, array $output, string $type, string $sample): array
    {
        $rows = [];
        if (!$check->hasRule()) {
            $rows[] = $this->row(
                self::NOTE,
                __('Google has no result type for %1', $type),
                __(
                    'It is output as written. Any schema.org type is allowed, and other search engines and assistants'
                    . ' may read it.'
                )
            );
        }
        foreach ($check->getFeatures() as $feature) {
            array_push($rows, ...$this->featureRows($feature, $output, $sample));
        }
        $leftOut = [];
        foreach ($check->getLeftOut() as $property) {
            if ($property->getOutcome() === TraceOutcome::Empty) {
                $leftOut[$property->getPath()] = true;
            }
        }
        if ($leftOut !== []) {
            $rows[] = $this->row(
                self::NOTE,
                __('Left out for %1: %2', $sample, $this->words->series(array_map('strval', array_keys($leftOut)))),
                __('These have no value on this page. An empty property is never output.')
            );
        }

        return $rows;
    }

    /**
     * The rows of one kind of result
     *
     * @param FeatureCheckInterface $feature
     * @param array<mixed> $output
     * @param string $sample
     * @return list<Row>
     */
    private function featureRows(FeatureCheckInterface $feature, array $output, string $sample): array
    {
        $rule = $feature->getRule();
        if ($rule->getGoogleStatus() !== GoogleStatus::Ok) {
            return [$this->row(
                $rule->getGoogleStatus() === GoogleStatus::Gone ? self::WARN : self::NOTE,
                __('Google no longer shows %1', __($rule->getPhrase())),
                __($rule->getStatusText())
            )];
        }
        $rows = match ($feature->getVerdict()) {
            FeatureVerdict::Met => [$this->row(
                self::OK,
                __('Meets Google’s requirements for %1', __($rule->getPhrase())),
                __($rule->getMetText())
            )],
            FeatureVerdict::NotMet => [$this->notMet($feature, $output)],
            FeatureVerdict::LeftOut => $rule->getLeftOutText() === '' ? [] : [$this->row(
                self::NOTE,
                __('No %1 for %2', __($rule->getPhrase()), $sample),
                __($rule->getLeftOutText())
            )],
        };
        $recommended = $feature->getMissingRecommended();
        if ($feature->getVerdict() !== FeatureVerdict::LeftOut && $recommended !== []) {
            $rows[] = $this->recommended($rule, $recommended);
        }

        return $rows;
    }

    /**
     * What a result misses of what Google requires
     *
     * @param FeatureCheckInterface $feature
     * @param array<mixed> $output
     * @return Row
     */
    private function notMet(FeatureCheckInterface $feature, array $output): array
    {
        $rule = $feature->getRule();
        $missing = $feature->getMissingRequired();
        $names = [];
        foreach ($missing as $property) {
            $names[$this->absentPart($output, $property->getPath())] = true;
        }
        $names = array_map('strval', array_keys($names));
        $first = $names[0] ?? '';

        return $this->row(
            ($missing[0] ?? null)?->getTone()->value ?? self::NOTE,
            __('Google needs %1 for %2', $this->words->join($names), __($rule->getPhrase())),
            __(
                'Required: %1. Without them Search Console reports the page as invalid.',
                __($rule->getRequirementText())
            ),
            $first === ''
                ? null
                : $this->action((string) __('Add %1', $first), $first, self::ADD_ICON)
        );
    }

    /**
     * What a result misses of what Google recommends
     *
     * One missing property is named, and described by why its rule says it is worth adding when the rule says so;
     * several are counted and listed by path.
     *
     * @param ResultTypeRuleInterface $rule
     * @param non-empty-list<MissingPropertyInterface> $missing
     * @return Row
     */
    private function recommended(ResultTypeRuleInterface $rule, array $missing): array
    {
        $paths = array_map(static fn (MissingPropertyInterface $property): string => $property->getPath(), $missing);
        if (count($paths) > 1) {
            return $this->row(
                self::NOTE,
                $this->plurals->sentence(
                    count($paths),
                    __('Google also recommends %1 more property', count($paths)),
                    __('Google also recommends %1 more properties', count($paths))
                ),
                $this->words->series($paths)
            );
        }
        $why = $this->why($rule, $paths[0]);

        return $this->row(
            self::NOTE,
            __('Google also recommends %1', $paths[0]),
            $why === '' ? $paths[0] : __($why),
            $this->action((string) __('Add it'), $paths[0], self::ADD_ICON)
        );
    }

    /**
     * Why a rule says a recommended property is worth adding; empty when it does not say
     *
     * @param ResultTypeRuleInterface $rule
     * @param string $path
     * @return string
     */
    private function why(ResultTypeRuleInterface $rule, string $path): string
    {
        foreach ($rule->getRecommended() as $requirement) {
            if (in_array($path, $requirement->getPaths(), true)) {
                return $requirement->getWhy();
            }
        }

        return '';
    }

    /**
     * The outermost part of a property path the block does not have: the path itself when its object is there
     *
     * A list on the way stands for its items: a part is there when any item has it.
     *
     * @param array<mixed> $output
     * @param string $path
     * @return string
     */
    private function absentPart(array $output, string $path): string
    {
        $segments = explode('.', $path);
        $values = [$output];
        foreach ($segments as $depth => $segment) {
            $next = [];
            foreach ($values as $value) {
                $items = is_array($value) && array_is_list($value) ? $value : [$value];
                foreach ($items as $item) {
                    if (is_array($item) && array_key_exists($segment, $item)) {
                        $next[] = $item[$segment];
                    }
                }
            }
            if ($next === []) {
                return implode('.', array_slice($segments, 0, $depth + 1));
            }
            $values = $next;
        }

        return $path;
    }

    /**
     * One action, its value left for CheckActionValues to fill
     *
     * @param string $label
     * @param string|null $path
     * @param string $icon
     * @return Action
     */
    private function action(string $label, ?string $path, string $icon): array
    {
        return ['label' => $label, 'path' => $path, 'icon' => $icon, 'value' => null, 'before' => []];
    }

    /**
     * One row
     *
     * @param string $tone
     * @param \Stringable|string $title
     * @param \Stringable|string $text
     * @param Action|null $action
     * @return Row
     */
    private function row(
        string $tone,
        \Stringable|string $title,
        \Stringable|string $text,
        ?array $action = null
    ): array {
        return ['tone' => $tone, 'title' => (string) $title, 'text' => (string) $text, 'action' => $action];
    }
}
