<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * What Google asks of one kind of result (a merchant listing, review stars, a breadcrumb …), as data
 *
 * A rule applies to schema.org types, each at an anchor: the empty anchor is the block's root object, any other is
 * the property path of an object inside it that the result lives in (a return policy at `hasMerchantReturnPolicy`
 * of an organization). Its paths are relative to that object.
 *
 * The texts are phrase keys: English sentences the presentation translates.
 *
 * @api
 */
interface ResultTypeRuleInterface
{
    /**
     * The rule's code, unique among the rules (`merchant_listing`)
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * The result's name as a label (`Merchant listing`)
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * The result's name inside a sentence (`a merchant listing`)
     *
     * @return string
     */
    public function getPhrase(): string;

    /**
     * The anchors of the rule, by the schema.org type each applies to
     *
     * @return array<string,string> anchor by type; the empty anchor is the root object
     */
    public function getAnchors(): array;

    /**
     * Where the rule applies inside a block of a type
     *
     * @param string $type a schema.org type
     * @return string|null the anchor, the empty string for the root object; null when the rule does not apply
     */
    public function anchorFor(string $type): ?string;

    /**
     * Whether a block may leave the result out without anything being wrong
     *
     * Review stars of a product with no reviews are one such result.
     *
     * @return bool
     */
    public function isOptional(): bool;

    /**
     * What Google does with the result today
     *
     * @return GoogleStatus
     */
    public function getGoogleStatus(): GoogleStatus;

    /**
     * The properties without which the result is invalid
     *
     * @return list<RequirementInterface>
     */
    public function getRequired(): array;

    /**
     * The properties Google recommends on top
     *
     * @return list<RequirementInterface>
     */
    public function getRecommended(): array;

    /**
     * How much a missing required property matters
     *
     * Bad for a result Google shows that a block may not leave out; a note for an optional result, or one Google
     * does not show.
     *
     * @return PropertyTone
     */
    public function getMissingRequiredTone(): PropertyTone;

    /**
     * The verdict on a block that carries the result's object and misses some of its required properties
     *
     * @param int $missingRequired how many required properties are missing
     * @return FeatureVerdict
     */
    public function verdictFor(int $missingRequired): FeatureVerdict;

    /**
     * The required properties in words, to follow "Required:" (`name, image and an offer with a price and a currency`)
     *
     * @return string
     */
    public function getRequirementText(): string;

    /**
     * The sentence shown when the result's requirements are met
     *
     * @return string
     */
    public function getMetText(): string;

    /**
     * The sentence shown when the result is left out; empty when the rule has none
     *
     * @return string
     */
    public function getLeftOutText(): string;

    /**
     * The sentence about Google's status, for a result Google does not show; empty when the rule has none
     *
     * @return string
     */
    public function getStatusText(): string;
}
