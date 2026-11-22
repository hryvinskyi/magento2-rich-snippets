<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Editor;

use Hryvinskyi\JsonTemplate\Api\Data\CompiledTemplateInterface;
use Hryvinskyi\JsonTemplate\Api\TemplateEngineInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every template the editor's builder prints compiles in the engine the snippets run on, and reads the same
 * variables and includes as the template it was printed from
 *
 * The vectors (`Test/_vectors/builder/*.json`) are the ones the builder's own test reads each template from and
 * prints back: `template` as written, `printed` the builder's form of it.
 */
#[AppArea('frontend')]
class BuilderVectorsCompileTest extends TestCase
{
    private const VECTORS = __DIR__ . '/../../../_vectors/builder';
    private const ENGINE = 'HryvinskyiRichSnippetsTemplateEngine';

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function vectors(): array
    {
        $vectors = [];
        foreach (glob(self::VECTORS . '/*.json') ?: [] as $file) {
            $vector = json_decode((string) file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
            self::assertIsArray($vector);
            $template = $vector['template'] ?? null;
            $printed = $vector['printed'] ?? null;
            self::assertIsString($template, $file);
            self::assertIsString($printed, $file);
            $vectors[basename($file, '.json')] = [$template, $printed];
        }
        self::assertNotSame([], $vectors, 'The builder vectors are found.');

        return $vectors;
    }

    #[DataProvider('vectors')]
    public function testThePrintedTemplateCompilesAndReadsWhatTheTemplateReads(string $template, string $printed): void
    {
        $written = $this->engine()->compile($template);
        $built = $this->engine()->compile($printed);

        self::assertSame($this->reads($written), $this->reads($built));
    }

    /**
     * What a compiled template reads: its variable roots and paths, and the snippets it includes, each sorted
     *
     * @param CompiledTemplateInterface $compiled
     * @return array{roots: list<string>, paths: list<string>, includes: list<string>}
     */
    private function reads(CompiledTemplateInterface $compiled): array
    {
        $roots = $compiled->getRoots();
        $paths = $compiled->getPaths();
        $includes = $compiled->getIncludes();
        sort($roots);
        sort($paths);
        sort($includes);

        return ['roots' => $roots, 'paths' => $paths, 'includes' => $includes];
    }

    /**
     * @return TemplateEngineInterface
     */
    private function engine(): TemplateEngineInterface
    {
        $engine = Bootstrap::getObjectManager()->get(self::ENGINE);
        self::assertInstanceOf(TemplateEngineInterface::class, $engine);

        return $engine;
    }
}
