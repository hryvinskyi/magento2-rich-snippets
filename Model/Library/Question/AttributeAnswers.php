<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library\Question;

use Hryvinskyi\RichSnippets\Api\Data\AnswerOptionInterface;
use Hryvinskyi\RichSnippets\Api\Data\HintTone;
use Hryvinskyi\RichSnippets\Model\Catalog\ProductAttributes;

/**
 * Turns candidate product attributes into answers, each with how many product pages fill it
 *
 * Only attributes with a value table of their own are offered: a column of the product table is never a candidate.
 * An answer reads well (ok) when its attribute is filled at least once and as often as the best candidate; the best
 * candidate — the first of the most filled — is the one to suggest, and there is none when no candidate is filled.
 */
class AttributeAnswers
{
    public const KEY_PREFIX = 'attribute:';

    /**
     * @param ProductAttributes $attributes
     * @param AttributeFillCountsInterface $counts
     * @param AnswerFactoryInterface $answers
     */
    public function __construct(
        private readonly ProductAttributes $attributes,
        private readonly AttributeFillCountsInterface $counts,
        private readonly AnswerFactoryInterface $answers
    ) {
    }

    /**
     * The answers of the candidates that exist, in the order given
     *
     * @param list<string> $codes candidate attribute codes, in the order of preference
     * @param int $storeId
     * @param callable(string):mixed $fragment the fragment that reads an attribute, by its code
     * @param callable(string,string):string $label the answer's label, by the attribute's code and label
     * @param callable(int,int,string):string $hint the hint, by the fill count, the total and the attribute code
     * @return array{options: list<AnswerOptionInterface>, best: string|null, total: int}
     */
    public function answers(array $codes, int $storeId, callable $fragment, callable $label, callable $hint): array
    {
        $total = $this->counts->total($storeId);
        $filled = [];
        foreach ($codes as $code) {
            if ($this->attributes->has($code) && !$this->attributes->isStatic($code)) {
                $filled[$code] = $this->counts->filled($code, $storeId);
            }
        }
        $most = $filled === [] ? 0 : max($filled);
        $options = [];
        $best = null;
        foreach ($filled as $code => $count) {
            $leading = $count > 0 && $count === $most;
            $best ??= $leading ? self::KEY_PREFIX . $code : null;
            $options[] = $this->answers->fragment(
                self::KEY_PREFIX . $code,
                $label($code, $this->attributes->label($code)),
                $fragment($code),
                $hint($count, $total, $code),
                $leading ? HintTone::Ok : HintTone::Warn,
                $count
            );
        }

        return ['options' => $options, 'best' => $best, 'total' => $total];
    }
}
