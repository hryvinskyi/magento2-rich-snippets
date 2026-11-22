<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library\Question;

use Hryvinskyi\AdminUi\Api\Page\PluralPhrasesInterface;
use Hryvinskyi\JsonTemplate\Api\TemplateLiteralsInterface;
use Hryvinskyi\RichSnippets\Api\Data\HintTone;
use Hryvinskyi\RichSnippets\Api\Data\LibraryQuestionInterface;
use Hryvinskyi\RichSnippets\Api\Data\QuestionSuggestionsInterface;
use Hryvinskyi\RichSnippets\Api\QuestionSuggesterInterface;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Store\Model\Store;

/**
 * The shop's profiles on other sites: typed in after adding, or taken from the CMS block with the most profile links
 *
 * Every active CMS block of the store view, and of all store views, is searched for links to the configured profile
 * hosts; the block with the most is offered, the first such block on a tie. Without one, typing them in is the only
 * answer. Typing them in stays the suggestion, since a block's links are worth a look before they are published.
 */
class SocialProfilesSuggester implements QuestionSuggesterInterface
{
    private const TYPE_IN = 'type_in';
    private const BLOCK_PREFIX = 'cms_block:';
    private const STORE_FILTER = 'store_id';

    /**
     * @param BlockRepositoryInterface $blocks
     * @param SearchCriteriaBuilder $criteria
     * @param ProfileLinks $profileLinks
     * @param TemplateLiteralsInterface $literals
     * @param AnswerFactoryInterface $answers
     * @param PluralPhrasesInterface $plurals
     * @param array<string,string> $hosts the hosts that hold profiles, in lower case
     */
    public function __construct(
        private readonly BlockRepositoryInterface $blocks,
        private readonly SearchCriteriaBuilder $criteria,
        private readonly ProfileLinks $profileLinks,
        private readonly TemplateLiteralsInterface $literals,
        private readonly AnswerFactoryInterface $answers,
        private readonly PluralPhrasesInterface $plurals,
        private readonly array $hosts = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function suggest(LibraryQuestionInterface $question, int $storeId): QuestionSuggestionsInterface
    {
        $options = [$this->answers->fragment(
            self::TYPE_IN,
            (string) __('Type them in after adding'),
            [],
            (string) __('The snippet opens with an empty list under sameAs.'),
            HintTone::Ok
        )];
        [$identifier, $links] = $this->richestBlock($storeId);
        if ($links !== []) {
            $options[] = $this->answers->fragment(
                self::BLOCK_PREFIX . $identifier,
                $this->plurals->sentence(
                    count($links),
                    __('Take the %1 link from the CMS block “%2”', count($links), $identifier),
                    __('Take the %1 links from the CMS block “%2”', count($links), $identifier)
                ),
                array_map(fn (string $link): string => $this->literals->text($link), $links),
                $this->plurals->sentence(
                    count($links),
                    __('%1 link found in the CMS block “%2”.', count($links), $identifier),
                    __('%1 links found in the CMS block “%2”.', count($links), $identifier)
                ),
                HintTone::Ok
            );
        }

        return $this->answers->suggestions($question->getCode(), $question->getLabel(), $options, self::TYPE_IN, null);
    }

    /**
     * The identifier and the profile links of the active block with the most of them
     *
     * @param int $storeId
     * @return array{string, list<string>}
     */
    private function richestBlock(int $storeId): array
    {
        $criteria = $this->criteria
            ->addFilter(BlockInterface::IS_ACTIVE, 1)
            ->addFilter(self::STORE_FILTER, [Store::DEFAULT_STORE_ID, $storeId], 'in')
            ->create();
        $richest = ['', []];
        foreach ($this->blocks->getList($criteria)->getItems() as $block) {
            $links = $this->profileLinks->links((string) $block->getContent(), array_values($this->hosts));
            if (count($links) > count($richest[1])) {
                $richest = [(string) $block->getIdentifier(), $links];
            }
        }

        return $richest;
    }
}
