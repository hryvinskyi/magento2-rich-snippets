<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library;

use Hryvinskyi\JsonTemplate\Api\TemplateEngineInterface;
use Hryvinskyi\RichSnippets\Api\Data\LibraryTemplateInterface;
use Hryvinskyi\RichSnippets\Api\Exception\MalformedLibraryTemplateException;
use Hryvinskyi\RichSnippets\Api\LibraryTemplatePoolInterface;
use Hryvinskyi\RichSnippets\Api\LibraryTemplateSourceInterface;
use Hryvinskyi\RichSnippets\Model\Config\KeptDefinitions;

/**
 * The templates of the sources in di.xml, read once and kept in the module's cache type
 *
 * The decoded definitions are kept as one entry (KeptDefinitions). Every definition is validated before the entry is
 * kept and again whenever the templates are built from it; each template is compiled with its placeholders left out,
 * and may use only building blocks of the library. Within a process the templates are built once.
 */
class LibraryTemplatePool implements LibraryTemplatePoolInterface
{
    private const POOL = 'library_templates';
    private const ENCODE_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * @var array<string, LibraryTemplateInterface>|null the templates by code, in listing order, once built
     */
    private ?array $templates = null;

    /**
     * @param LibraryDefinitionParser $parser
     * @param AnswerSubstitution $substitution
     * @param TemplateEngineInterface $engine the snippets' own engine
     * @param KeptDefinitions $kept
     * @param array<string,string> $groups the groups' labels by code, in the order the library lists them
     * @param array<string,LibraryTemplateSourceInterface> $sources by template code, in the order they are listed
     */
    public function __construct(
        private readonly LibraryDefinitionParser $parser,
        private readonly AnswerSubstitution $substitution,
        private readonly TemplateEngineInterface $engine,
        private readonly KeptDefinitions $kept,
        private readonly array $groups = [],
        private readonly array $sources = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        return array_values($this->templates());
    }

    /**
     * @inheritDoc
     */
    public function get(string $code): ?LibraryTemplateInterface
    {
        return $this->templates()[$code] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function groups(): array
    {
        $groups = [];
        foreach ($this->groups as $code => $label) {
            $groups[(string) $code] = (string) $label;
        }

        return $groups;
    }

    /**
     * The templates by code, in listing order
     *
     * @return array<string, LibraryTemplateInterface>
     * @throws MalformedLibraryTemplateException
     */
    private function templates(): array
    {
        if ($this->templates !== null) {
            return $this->templates;
        }
        $codes = array_map('strval', array_keys($this->sources));
        $definitions = $this->kept->load(self::POOL, $codes);
        if ($definitions !== null) {
            return $this->templates = $this->build($definitions);
        }
        $definitions = $this->read();
        $this->templates = $this->build($definitions);
        $this->kept->keep(self::POOL, $definitions);

        return $this->templates;
    }

    /**
     * The definitions of every source, read from the sources themselves
     *
     * @return array<string, array<mixed>>
     * @throws MalformedLibraryTemplateException
     */
    private function read(): array
    {
        $definitions = [];
        foreach ($this->sources as $code => $source) {
            $definitions[(string) $code] = $source->read();
        }

        return $definitions;
    }

    /**
     * The templates of the definitions, each validated, listed group by group
     *
     * @param array<string,array<mixed>> $definitions
     * @return array<string, LibraryTemplateInterface>
     * @throws MalformedLibraryTemplateException
     */
    private function build(array $definitions): array
    {
        $groups = array_keys($this->groups());
        $templates = [];
        foreach ($definitions as $code => $definition) {
            $template = $this->parser->parse($code, $definition, $groups);
            $this->compile($template);
            $templates[$code] = $template;
        }
        foreach ($templates as $template) {
            $this->checkUses($template, $templates);
        }
        uasort(
            $templates,
            static fn (LibraryTemplateInterface $a, LibraryTemplateInterface $b): int =>
                array_search($a->getGroup(), $groups, true) <=> array_search($b->getGroup(), $groups, true)
        );

        return $templates;
    }

    /**
     * Compile a template with its placeholders left out
     *
     * @param LibraryTemplateInterface $template
     * @return void
     * @throws MalformedLibraryTemplateException when it does not compile
     */
    private function compile(LibraryTemplateInterface $template): void
    {
        try {
            $this->engine->compile(
                (string) json_encode(
                    $this->substitution->withoutAnswers($template->getTemplate()),
                    self::ENCODE_FLAGS | JSON_THROW_ON_ERROR
                )
            );
        } catch (\Throwable $e) {
            throw new MalformedLibraryTemplateException(
                sprintf('The library template "%s" does not compile: %s', $template->getCode(), $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Refuse a template that uses anything but a building block of the library
     *
     * @param LibraryTemplateInterface $template
     * @param array<string,LibraryTemplateInterface> $templates
     * @return void
     * @throws MalformedLibraryTemplateException
     */
    private function checkUses(LibraryTemplateInterface $template, array $templates): void
    {
        foreach ($template->getUses() as $use) {
            if ($use === $template->getCode() || !($templates[$use] ?? null)?->isBlock()) {
                throw new MalformedLibraryTemplateException(sprintf(
                    'The library template "%s" uses "%s", which is no other building block of the library.',
                    $template->getCode(),
                    $use
                ));
            }
        }
    }
}
