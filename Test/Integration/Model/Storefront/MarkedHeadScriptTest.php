<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Storefront;

use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Hryvinskyi\HeadTagManager\Api\Serializer\HeadElementSerializerInterface;
use Hryvinskyi\RichSnippets\Model\Storefront\Head\MarkedScriptElement;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The head tag manager of the real container creates the snippets' marked script element by its type, and the
 * element survives the block cache's serialization as itself, comment included
 */
#[AppArea('frontend')]
class MarkedHeadScriptTest extends TestCase
{
    protected function setUp(): void
    {
        $this->head()->clear();
    }

    protected function tearDown(): void
    {
        $this->head()->clear();
    }

    public function testTheMarkedElementRoundTripsThroughTheBlockCacheSerialization(): void
    {
        $this->head()->createElement(
            'rich_snippet_script',
            [
                'attributes' => ['type' => 'application/ld+json', 'data-snippet' => 'organization'],
                'content' => '{"@type":"OnlineStore"}',
            ],
            'rich_snippets_organization'
        );
        $element = $this->head()->getElement('rich_snippets_organization');
        self::assertInstanceOf(MarkedScriptElement::class, $element);
        $serializer = Bootstrap::getObjectManager()->get(HeadElementSerializerInterface::class);
        self::assertInstanceOf(HeadElementSerializerInterface::class, $serializer);

        $stored = json_decode(
            (string) json_encode($serializer->serialize(['rich_snippets_organization' => $element])),
            true
        );
        self::assertIsArray($stored);
        $restored = $serializer->unserialize($stored)['rich_snippets_organization'] ?? null;

        self::assertInstanceOf(MarkedScriptElement::class, $restored);
        self::assertStringStartsWith("<!-- rich-snippet: organization -->\n<script", $restored->render());
        self::assertSame($element->render(), $restored->render());
    }

    /**
     * The request's head tag manager
     *
     * @return HeadTagManagerInterface
     */
    private function head(): HeadTagManagerInterface
    {
        $head = Bootstrap::getObjectManager()->get(HeadTagManagerInterface::class);
        self::assertInstanceOf(HeadTagManagerInterface::class, $head);

        return $head;
    }
}
