<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Requirement;

use Hryvinskyi\RichSnippets\Api\Data\FeatureVerdict;
use Hryvinskyi\RichSnippets\Api\Data\GoogleStatus;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;
use Hryvinskyi\RichSnippets\Api\Data\RequirementInterface;
use Hryvinskyi\RichSnippets\Api\Data\ResultTypeRuleInterface;
use Hryvinskyi\RichSnippets\Api\Exception\MalformedRuleException;

/**
 * The requirement rule of one kind of Google result
 *
 * A rule cannot exist without a code, a label, a phrase, the required texts and at least one type to apply to; each
 * type is a schema.org type name and each anchor empty or a property path.
 *
 * A required property is bad to miss only for a result Google shows that a block may not leave out; for an optional
 * result, or one Google does not show, it is a note. An optional result whose every required property is missing is
 * left out rather than not met.
 */
class ResultTypeRule implements ResultTypeRuleInterface
{
    public const CODE_PATTERN = '/\A[a-z][a-z0-9_]{0,63}\z/';
    public const TYPE_PATTERN = '/\A[A-Z][A-Za-z0-9]*\z/';

    /**
     * @param string $code
     * @param string $label
     * @param string $phrase
     * @param array<string,string> $anchors anchor by schema.org type, the empty anchor for the root object
     * @param bool $optional
     * @param GoogleStatus $googleStatus
     * @param list<RequirementInterface> $required
     * @param list<RequirementInterface> $recommended
     * @param string $requirementText
     * @param string $metText
     * @param string $leftOutText
     * @param string $statusText
     * @throws MalformedRuleException when a value breaks the rule's shape
     */
    public function __construct(
        private readonly string $code,
        private readonly string $label,
        private readonly string $phrase,
        private readonly array $anchors,
        private readonly bool $optional,
        private readonly GoogleStatus $googleStatus,
        private readonly array $required,
        private readonly array $recommended,
        private readonly string $requirementText,
        private readonly string $metText,
        private readonly string $leftOutText = '',
        private readonly string $statusText = ''
    ) {
        if (preg_match(self::CODE_PATTERN, $code) !== 1) {
            throw new MalformedRuleException(sprintf('"%s" is not a rule code.', $code));
        }
        foreach (['label' => $label, 'phrase' => $phrase, 'requirement text' => $requirementText,
                     'met text' => $metText] as $name => $text) {
            if (trim($text) === '') {
                throw new MalformedRuleException(sprintf('The rule "%s" has no %s.', $code, $name));
            }
        }
        if ($anchors === []) {
            throw new MalformedRuleException(sprintf('The rule "%s" applies to no type.', $code));
        }
        foreach ($anchors as $type => $anchor) {
            if (preg_match(self::TYPE_PATTERN, $type) !== 1) {
                throw new MalformedRuleException(sprintf('The rule "%s" names "%s", not a type.', $code, $type));
            }
            if ($anchor !== '' && preg_match(Requirement::PATH_PATTERN, $anchor) !== 1) {
                throw new MalformedRuleException(sprintf('The rule "%s" anchors at "%s".', $code, $anchor));
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @inheritDoc
     */
    public function getPhrase(): string
    {
        return $this->phrase;
    }

    /**
     * @inheritDoc
     */
    public function getAnchors(): array
    {
        return $this->anchors;
    }

    /**
     * @inheritDoc
     */
    public function anchorFor(string $type): ?string
    {
        return $this->anchors[$type] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function isOptional(): bool
    {
        return $this->optional;
    }

    /**
     * @inheritDoc
     */
    public function getGoogleStatus(): GoogleStatus
    {
        return $this->googleStatus;
    }

    /**
     * @inheritDoc
     */
    public function getRequired(): array
    {
        return $this->required;
    }

    /**
     * @inheritDoc
     */
    public function getRecommended(): array
    {
        return $this->recommended;
    }

    /**
     * @inheritDoc
     */
    public function getMissingRequiredTone(): PropertyTone
    {
        return $this->optional || $this->googleStatus !== GoogleStatus::Ok ? PropertyTone::Note : PropertyTone::Bad;
    }

    /**
     * @inheritDoc
     */
    public function verdictFor(int $missingRequired): FeatureVerdict
    {
        if ($missingRequired <= 0) {
            return FeatureVerdict::Met;
        }

        return $this->optional && $missingRequired >= count($this->required)
            ? FeatureVerdict::LeftOut
            : FeatureVerdict::NotMet;
    }

    /**
     * @inheritDoc
     */
    public function getRequirementText(): string
    {
        return $this->requirementText;
    }

    /**
     * @inheritDoc
     */
    public function getMetText(): string
    {
        return $this->metText;
    }

    /**
     * @inheritDoc
     */
    public function getLeftOutText(): string
    {
        return $this->leftOutText;
    }

    /**
     * @inheritDoc
     */
    public function getStatusText(): string
    {
        return $this->statusText;
    }
}
