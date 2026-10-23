<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\Catalog;

use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\Model\ManualChapter;
use Nowo\ManualKitBundle\Model\TranslationState;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class ManualCatalogTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/manual-catalog-' . bin2hex(random_bytes(4));
        mkdir($this->directory . '/es', recursive: true);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testReadsTheManifestInOrderWithAudienceTitleAndDefaults(): void
    {
        $this->write([
            "chapters:\n  - { slug: README, audience: member, icon: 'tabler:book' }\n  - { slug: 01-a, group: using, tags: [first, primero, '', 7] }\n  - { slug: 02-b, group: administration, audience: admin }\n",
            'README' => "# Home\n",
            '01-a'   => "<!-- a comment -->\n\n# First chapter\n\nBody\n",
            '02-b'   => "No heading\n",
        ]);
        $catalog = new ManualCatalog($this->directory);

        self::assertSame($this->directory, $catalog->directory());
        self::assertSame('en', $catalog->sourceLocale());
        self::assertSame([], $catalog->locales());
        $all = $catalog->all();
        self::assertSame(['README', '01-a', '02-b'], array_map(static fn (ManualChapter $c): string => $c->slug, $all));
        self::assertTrue($all[0]->isHome());
        self::assertSame('Home', $all[0]->title);
        self::assertSame('using', $all[0]->group);
        self::assertSame('tabler:book', $all[1]->icon, 'default icon');
        self::assertSame('First chapter', $all[1]->title, 'leading comments are skipped');
        self::assertSame(['first', 'primero', '7'], $all[1]->tags, 'blank tags dropped, scalars as text');
        self::assertSame([], $all[0]->tags);
        self::assertSame('02-b', $all[2]->title, 'falls back to the slug');
        self::assertTrue($all[2]->adminOnly);
        self::assertSame([TranslationState::Source, 'en'], [$all[1]->translation, $all[1]->locale]);

        self::assertCount(2, $catalog->visible(false));
        self::assertCount(3, $catalog->visible(true));
        self::assertSame('01-a', $catalog->find('01-a')?->slug);
        self::assertNull($catalog->find('99-missing'));

        [$previous, $next] = $catalog->neighbours($all[1], false);
        self::assertSame('README', $previous?->slug);
        self::assertNull($next, 'the admin chapter is not a neighbour for members');
        self::assertSame('02-b', $catalog->neighbours($all[1], true)[1]?->slug);
        self::assertSame([null, null], $catalog->neighbours($all[2], false));
    }

    public function testResolvesTranslationsPerLocale(): void
    {
        $this->write([
            "chapters:\n  - { slug: README }\n  - { slug: 01-a }\n  - { slug: 02-b }\n",
            'README' => "# Home\n",
            '01-a'   => "# First\n",
            '02-b'   => "# Second\n",
        ]);
        file_put_contents($this->directory . '/es/README.md', '<!-- translation-of: ' . ManualCatalog::sourceHash("# Home\n") . " -->\n# Inicio\n");
        file_put_contents($this->directory . '/es/01-a.md', "<!-- translation-of: 000000000000 -->\n# Primero\n");
        $catalog = new ManualCatalog($this->directory, 'en', ['en', 'es']);

        self::assertFalse($catalog->translates(null));
        self::assertFalse($catalog->translates('en'));
        self::assertFalse($catalog->translates('fr'), 'not configured');
        self::assertTrue($catalog->translates('es'));

        $es = $catalog->all('es');
        self::assertSame([TranslationState::Translated, 'es', 'Inicio'], [$es[0]->translation, $es[0]->locale, $es[0]->title]);
        self::assertSame([TranslationState::Outdated, 'Primero'], [$es[1]->translation, $es[1]->title]);
        self::assertSame([TranslationState::Missing, 'en', 'Second'], [$es[2]->translation, $es[2]->locale, $es[2]->title]);
        self::assertSame(TranslationState::Source, $catalog->all('fr')[0]->translation);
        self::assertSame($this->directory . '/es/01-a.md', $catalog->translationPath('01-a', 'es'));
        self::assertSame($this->directory . '/01-a.md', $catalog->sourcePath('01-a'));
    }

    public function testAnEmptyManifestHasNoChapters(): void
    {
        $this->write(["# nothing\n"]);

        self::assertSame([], new ManualCatalog($this->directory)->all());
    }

    public function testABadSlugOrAMissingFileLeavesTheChapterOutAsAProblem(): void
    {
        $this->write(["chapters:\n  - { slug: ../secret }\n  - { slug: 01-gone }\n  - 7\n  - { slug: 02-ok }\n  - { slug: 02-ok }\n", '02-ok' => "# Ok\n"]);
        $catalog = new ManualCatalog($this->directory);

        self::assertSame(['02-ok'], array_map(static fn (ManualChapter $chapter): string => $chapter->slug, $catalog->all()));
        $problems = implode("\n", $catalog->problems());
        self::assertStringContainsString('a chapter needs a slug', $problems);
        self::assertStringContainsString('"01-gone", which has no Markdown file', $problems);
        self::assertStringContainsString('chapter #2 is not a mapping', $problems);
        self::assertStringContainsString('"02-ok" is listed twice', $problems);
    }

    public function testAMissingOrBrokenManifestIsAProblemNotAnError(): void
    {
        self::assertSame([], new ManualCatalog($this->directory)->all());
        self::assertStringContainsString('not found', new ManualCatalog($this->directory)->problems()[0]);

        $this->write(["chapters: [ { slug: x\n"]);
        self::assertSame([], new ManualCatalog($this->directory)->all());
        self::assertStringContainsString('cannot be parsed', new ManualCatalog($this->directory)->problems()[0]);
    }

    public function testIncludeServesEveryMarkdownFileOfAGlobOrAFolder(): void
    {
        $root = sys_get_temp_dir() . '/manual-include-' . bin2hex(random_bytes(4));
        mkdir($root . '/docs/manual', recursive: true);
        mkdir($root . '/docs/adr');
        mkdir($root . '/docs/ops');
        file_put_contents($root . '/docs/adr/0002 Second.md', "# Second ADR\n");
        file_put_contents($root . '/docs/adr/0001-first.md', "# First ADR\n");
        file_put_contents($root . '/docs/adr/notes.txt', 'x');
        file_put_contents($root . '/docs/ops/RUN.md', "# Runbook\n");
        file_put_contents($root . '/docs/manual/manifest.yaml', "chapters:\n  - { include: '../adr/*', group: administration, audience: admin }\n  - { include: '../ops/' }\n  - { include: '../none/*.md' }\n  - { include: '../../*.md' }\n");
        file_put_contents($root . '/top.md', '# Outside');
        try {
            $catalog  = new ManualCatalog($root . '/docs/manual');
            $chapters = $catalog->all('es');
            self::assertSame(['0001-first', '0002-Second', 'RUN'], array_map(static fn (ManualChapter $chapter): string => $chapter->slug, $chapters));
            self::assertSame(['First ADR', 'Second ADR', 'Runbook'], array_map(static fn (ManualChapter $chapter): string => $chapter->title, $chapters));
            self::assertTrue($chapters[0]->adminOnly);
            self::assertTrue($chapters[0]->external);
            self::assertSame('adr', $chapters[0]->docsDir);
            self::assertSame(TranslationState::Source, $chapters[0]->translation);
            self::assertFalse($chapters[2]->adminOnly);
            $problems = implode("\n", $catalog->problems());
            self::assertStringContainsString('the include "../none/*.md" matches no Markdown file', $problems);
            self::assertStringContainsString('the include "../../*.md" matches no Markdown file', $problems, 'outside the docs root');
        } finally {
            new Filesystem()->remove($root);
        }
    }

    /**
     * @param array<int|string, string> $files index 0 = manifest, slug => Markdown
     */
    private function write(array $files): void
    {
        foreach ($files as $name => $content) {
            file_put_contents($this->directory . '/' . ($name === 0 ? ManualCatalog::MANIFEST : $name . '.md'), $content);
        }
    }

    public function testAnExternalFileMustBeMarkdownInsideTheDocsRoot(): void
    {
        $root = sys_get_temp_dir() . '/manual-external-' . bin2hex(random_bytes(4));
        mkdir($root . '/docs/manual', recursive: true);
        file_put_contents($root . '/docs/notes.txt', 'x');
        file_put_contents($root . '/outside.md', '# Outside');
        try {
            foreach (['../notes.txt', '../../outside.md', '../missing.md'] as $file) {
                file_put_contents($root . '/docs/manual/manifest.yaml', "chapters:\n  - { slug: x, file: '" . $file . "' }\n");
                $catalog = new ManualCatalog($root . '/docs/manual');
                self::assertSame([], $catalog->all(), $file);
                self::assertStringContainsString('must be an existing Markdown file inside', $catalog->problems()[0], $file);
            }
        } finally {
            new Filesystem()->remove($root);
        }
    }
}
