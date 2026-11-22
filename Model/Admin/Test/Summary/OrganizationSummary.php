<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Test\Summary;

use Hryvinskyi\AdminUi\Api\Page\PluralPhrasesInterface;
use Magento\Framework\Phrase;

/**
 * An organization or a shop as what it carries: its address, its profiles and its return policy
 * ("The shop, with its address, two profiles and the return policy.")
 *
 * Each combination of the three is a sentence of its own, so a language orders and inflects the whole as it needs;
 * only the subject and the number of profiles are filled in. What the organization is called in the sentence is
 * configured per type ("The shop" for an online store). Up to five profiles are spelled out; more are counted in the
 * plural form the admin's language gives the number.
 */
class OrganizationSummary implements BlockSummaryInterface
{
    private const ADDRESS = 'address';
    private const PROFILES = 'profiles';
    private const RETURN_POLICY = 'returns';

    /**
     * @param PluralPhrasesInterface $plurals
     * @param string $subject the phrase that opens the sentence (`The shop`)
     */
    public function __construct(
        private readonly PluralPhrasesInterface $plurals,
        private readonly string $subject = 'The organization'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function summarize(array $object): string
    {
        $count = $this->profileCount($object['sameAs'] ?? null);
        $carried = array_keys(array_filter([
            self::ADDRESS => $this->has($object, 'address'),
            self::PROFILES => $count > 0,
            self::RETURN_POLICY => $this->has($object, 'hasMerchantReturnPolicy'),
        ]));

        return $this->sentences((string) __($this->subject), $count > 0 ? $this->profiles($count) : '')
            [implode(' ', $carried)]->render();
    }

    /**
     * The sentence for each combination of what the object carries, keyed by the carried parts in order
     *
     * @param string $subject
     * @param string $profiles
     * @return array<string, Phrase>
     */
    private function sentences(string $subject, string $profiles): array
    {
        return [
            '' => __('%1.', $subject),
            self::ADDRESS => __('%1, with its address.', $subject),
            self::PROFILES => __('%1, with %2.', $subject, $profiles),
            self::RETURN_POLICY => __('%1, with the return policy.', $subject),
            self::ADDRESS . ' ' . self::PROFILES => __('%1, with its address and %2.', $subject, $profiles),
            self::ADDRESS . ' ' . self::RETURN_POLICY => __(
                '%1, with its address and the return policy.',
                $subject
            ),
            self::PROFILES . ' ' . self::RETURN_POLICY => __(
                '%1, with %2 and the return policy.',
                $subject,
                $profiles
            ),
            self::ADDRESS . ' ' . self::PROFILES . ' ' . self::RETURN_POLICY => __(
                '%1, with its address, %2 and the return policy.',
                $subject,
                $profiles
            ),
        ];
    }

    /**
     * A number of profiles in words: spelled out up to five, as figures above
     *
     * @param int $count
     * @return string
     */
    private function profiles(int $count): string
    {
        $spelled = [
            1 => __('one profile'),
            2 => __('two profiles'),
            3 => __('three profiles'),
            4 => __('four profiles'),
            5 => __('five profiles'),
        ][$count] ?? null;

        return $spelled === null
            ? $this->plurals->sentence($count, __('%1 profile', $count), __('%1 profiles', $count))
            : $spelled->render();
    }

    /**
     * Whether the object carries a property with a value
     *
     * @param array<mixed> $object
     * @param string $property
     * @return bool
     */
    private function has(array $object, string $property): bool
    {
        $value = $object[$property] ?? null;

        return $value !== null && $value !== '' && $value !== [];
    }

    /**
     * How many profile addresses `sameAs` lists
     *
     * @param mixed $sameAs
     * @return int
     */
    private function profileCount(mixed $sameAs): int
    {
        if (is_string($sameAs)) {
            return $sameAs === '' ? 0 : 1;
        }

        return is_array($sameAs) ? count(array_filter($sameAs, static fn (mixed $url): bool => is_string($url))) : 0;
    }
}
