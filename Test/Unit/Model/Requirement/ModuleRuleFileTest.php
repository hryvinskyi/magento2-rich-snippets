<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Requirement;

use Hryvinskyi\RichSnippets\Api\Exception\MalformedRuleException;
use Hryvinskyi\RichSnippets\Model\Config\ModuleJsonFile;
use Hryvinskyi\RichSnippets\Model\Requirement\Source\ModuleRuleFile;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

/**
 * A rule file is read from the rule directory of its module's etc directory as a JSON object; a file that is not
 * an object, not JSON, of an unknown module or named outside the directory fails loudly
 */
class ModuleRuleFileTest extends TestCase
{
    /**
     * @var string the etc directory of the test's module
     */
    private string $etc;

    protected function setUp(): void
    {
        $this->etc = sys_get_temp_dir() . '/rich-snippets-rule-file-' . bin2hex(random_bytes(6));
        mkdir($this->etc . '/' . ModuleRuleFile::DIRECTORY, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->etc . '/' . ModuleRuleFile::DIRECTORY . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->etc . '/' . ModuleRuleFile::DIRECTORY);
        rmdir($this->etc . '/rich_snippets');
        rmdir($this->etc);
    }

    public function testTheFileOfTheModuleIsReadAsAnObject(): void
    {
        $this->put('event.json', '{"label": "Event"}');

        self::assertSame(['label' => 'Event'], $this->source('Vendor_Events', 'event.json')->read());
    }

    public function testAFileThatIsNotAnObjectIsRefused(): void
    {
        $this->put('list.json', '["label"]');

        $this->expectException(MalformedRuleException::class);
        $this->expectExceptionMessage('does not hold a JSON object');

        $this->source('Vendor_Events', 'list.json')->read();
    }

    public function testAFileThatIsNotJsonIsRefused(): void
    {
        $this->put('broken.json', '{"label": ');

        $this->expectException(MalformedRuleException::class);
        $this->expectExceptionMessage('cannot be read as JSON');

        $this->source('Vendor_Events', 'broken.json')->read();
    }

    public function testAMissingFileIsRefused(): void
    {
        $this->expectException(MalformedRuleException::class);

        $this->source('Vendor_Events', 'missing.json')->read();
    }

    public function testAFileNameThatLeavesTheRuleDirectoryIsRefused(): void
    {
        $this->expectException(MalformedRuleException::class);
        $this->expectExceptionMessage('is not a plain JSON file name');

        $this->source('Vendor_Events', '../../di.xml')->read();
    }

    public function testAnUnknownModuleIsRefused(): void
    {
        $this->expectException(MalformedRuleException::class);
        $this->expectExceptionMessage('"Vendor_Unknown"');

        $this->source('Vendor_Unknown', 'event.json')->read();
    }

    /**
     * A source of a file of a module whose etc directory is the test's
     *
     * @param string $module
     * @param string $file
     * @return ModuleRuleFile
     */
    private function source(string $module, string $file): ModuleRuleFile
    {
        $dirs = $this->createStub(Dir::class);
        $dirs->method('getDir')->willReturnCallback(
            function (string $name, string $type): string {
                self::assertSame(Dir::MODULE_ETC_DIR, $type);
                if ($name !== 'Vendor_Events') {
                    throw new \InvalidArgumentException('Unknown module ' . $name);
                }

                return $this->etc;
            }
        );

        return new ModuleRuleFile(new ModuleJsonFile($dirs, new File(), new Json()), $module, $file);
    }

    /**
     * Write a rule file
     *
     * @param string $name
     * @param string $content
     * @return void
     */
    private function put(string $name, string $content): void
    {
        file_put_contents($this->etc . '/' . ModuleRuleFile::DIRECTORY . '/' . $name, $content);
    }
}
