<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\Catalog;

use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\Model\ManualChapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * 1.6: what a `parent` in the manifest may not do — go deeper than three levels, loop, point at nothing, hang under the
 * home page, or reach a file outside the manual.
 */
final class ChapterTreeManifestTest extends TestCase
{
    private string $root;
    private string $directory;

    protected function setUp(): void
    {
        $this->root      = sys_get_temp_dir() . '/manual-tree-' . bin2hex(random_bytes(4));
        $this->directory = $this->root . '/manual';
        mkdir($this->directory, recursive: true);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->root);
    }

    public function testDeeperThanThreeLevelsIsLeftOutAndReported(): void
    {
        $this->write("  - { slug: a }\n  - { slug: b, parent: a }\n  - { slug: c, parent: b }\n  - { slug: d, parent: c }\n  - { slug: e, parent: d }\n", ['a', 'b', 'c', 'd', 'e']);
        $catalog = new ManualCatalog($this->directory);

        self::assertSame(['a', 'b', 'c'], $this->slugs($catalog));
        self::assertSame([
            'manifest.yaml: chapter "d" is nested deeper than 3 levels; it is left out.',
            'manifest.yaml: chapter "e" is nested deeper than 3 levels; it is left out.',
        ], $catalog->problems());
    }

    public function testCyclesUnknownParentsAndTheHomePageAreRejected(): void
    {
        $this->write(
            "  - { slug: README }\n  - { slug: a, parent: b }\n  - { slug: b, parent: a }\n  - { slug: self, parent: self }\n"
            . "  - { slug: lost, parent: nowhere }\n  - { slug: under-home, parent: README }\n  - { slug: bad, parent: ['x'] }\n"
            . "  - { slug: bad2, parent: '../etc' }\n  - { slug: ok }\n  - { slug: kid, parent: ok, group: project }\n  - { slug: orphan, parent: lost }\n",
            ['README', 'a', 'b', 'self', 'lost', 'under-home', 'bad', 'bad2', 'ok', 'kid', 'orphan'],
        );
        $catalog = new ManualCatalog($this->directory);

        self::assertSame(['README', 'ok', 'kid'], $this->slugs($catalog));
        self::assertSame('using', ($catalog->find('kid') ?? self::fail('kid'))->group);
        self::assertSame([
            'manifest.yaml: the parent ["x"] is not a chapter slug; the row is left out.',
            'manifest.yaml: the parent "../etc" is not a chapter slug; the row is left out.',
            'manifest.yaml: the parents of "a" form a cycle; it is left out.',
            'manifest.yaml: the parents of "b" form a cycle; it is left out.',
            'manifest.yaml: the parents of "self" form a cycle; it is left out.',
            'manifest.yaml: chapter "lost" has an unknown parent "nowhere"; it is left out.',
            'manifest.yaml: the home page (README) cannot have a parent or children; "under-home" is left out.',
            'manifest.yaml: chapter "orphan" has an unknown parent "nowhere"; it is left out.',
            'manifest.yaml: chapter "kid" sits under "ok" and takes its group; its own group "project" is ignored.',
        ], $catalog->problems());
    }

    public function testANestedFileMustExistInsideTheManual(): void
    {
        mkdir($this->root . '/outside');
        file_put_contents($this->root . '/outside/leak.md', "# Leak\n");
        mkdir($this->directory . '/top');
        symlink($this->root . '/outside/leak.md', $this->directory . '/top/leak.md');
        $this->write("  - { slug: top }\n  - { slug: leak, parent: top }\n  - { slug: ghost, parent: top }\n  - { slug: ghost-kid, parent: ghost }\n", ['top', 'ghost-kid']);
        $catalog = new ManualCatalog($this->directory);

        self::assertSame(['top'], $this->slugs($catalog), 'a link out of the manual, a missing file and its children are left out');
        self::assertSame([
            'manifest.yaml lists "leak", which has no Markdown file (looked in top/ and at the manual root).',
            'manifest.yaml lists "ghost", which has no Markdown file (looked in top/ and at the manual root).',
        ], $catalog->problems());
    }

    /** Audit CR L1: a top-level source linked out of the manual is left out too. */
    public function testAFlatSourceLinkedOutOfTheManualIsLeftOut(): void
    {
        file_put_contents($this->root . '/leak.md', "# Leak\n");
        symlink($this->root . '/leak.md', $this->directory . '/leak.md');
        $this->write("  - { slug: top }\n  - { slug: leak }\n", ['top']);
        $catalog = new ManualCatalog($this->directory);

        self::assertSame(['top'], $this->slugs($catalog));
        self::assertSame(['manifest.yaml: the file of "leak" resolves outside ' . $this->directory . '; it is left out.'], $catalog->problems());
    }

    /** Audit CR I3: a slug or parent with a trailing newline is not a slug. */
    public function testATrailingNewlineIsNotASlug(): void
    {
        $this->write("  - { slug: top }\n  - { slug: kid, parent: \"top\\n\" }\n  - { slug: \"x\\n\" }\n", ['top', 'kid']);
        $catalog = new ManualCatalog($this->directory);

        self::assertSame(['top'], $this->slugs($catalog));
        self::assertSame([
            'manifest.yaml: the parent "top\\n" is not a chapter slug; the row is left out.',
            'manifest.yaml: a chapter needs a slug made of letters, digits and dashes (or an include).',
        ], $catalog->problems());
        self::assertSame(0, preg_match(ManualCatalog::SLUG, "top\n"));
    }

    public function testExternalAndIncludedChaptersMayHangUnderAChapter(): void
    {
        file_put_contents($this->root . '/CHANGES.md', "# Changes\n");
        mkdir($this->root . '/adr');
        file_put_contents($this->root . '/adr/0001-one.md', "# One\n");
        $this->write("  - { slug: top, audience: admin }\n  - { slug: changes, file: ../CHANGES.md, parent: top }\n  - { include: ../adr, parent: changes }\n  - { include: ../adr, parent: '/abs' }\n", ['top']);
        $catalog = new ManualCatalog($this->directory);

        self::assertSame(['top', 'changes', '0001-one'], $this->slugs($catalog));
        $adr = $catalog->find('0001-one') ?? self::fail('adr');
        self::assertSame(['top', 'changes'], $adr->ancestors);
        self::assertTrue($adr->adminOnly);
        self::assertSame('adr', $adr->docsDir);
        self::assertSame('', $adr->folder);
        self::assertSame(['manifest.yaml: the parent "/abs" is not a chapter slug; the row is left out.'], $catalog->problems());
    }

    /**
     * @param list<string> $files chapter slugs that get a Markdown file (`a/b/c.md` follows the tree when it has one)
     */
    private function write(string $rows, array $files): void
    {
        file_put_contents($this->directory . '/manifest.yaml', "chapters:\n" . $rows);
        foreach ($files as $slug) {
            file_put_contents($this->directory . '/' . $slug . '.md', '# ' . ucfirst($slug) . "\n");
        }
    }

    /**
     * @return list<string>
     */
    private function slugs(ManualCatalog $catalog): array
    {
        return array_map(static fn (ManualChapter $chapter): string => $chapter->slug, $catalog->all());
    }
}
