<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\Redirect;

use InvalidArgumentException;
use Nowo\ManualKitBundle\Redirect\ChapterRedirectMap;
use Nowo\ManualKitBundle\Redirect\RedirectMapCompiler;
use Nowo\ManualKitBundle\Redirect\RedirectTarget;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_map;
use function implode;

/**
 * 1.11: validation (internal slugs only, no cycle), chain collapsing and the precomputed anchor lookups.
 */
final class RedirectMapCompilerTest extends TestCase
{
    /**
     * @return iterable<string, array{0: array<mixed>, 1: string}>
     */
    public static function invalidMaps(): iterable
    {
        yield 'absolute URL target' => [['old' => 'https://evil.example/x'], 'targets are internal chapter slugs'];
        yield 'protocol-relative target' => [['old' => '//evil.example'], 'never a URL, a scheme or "//"'];
        yield 'scheme target' => [['old' => 'javascript:alert(1)'], 'targets are internal chapter slugs'];
        yield 'path target' => [['old' => '../etc/passwd'], 'targets are internal chapter slugs'];
        yield 'empty anchor' => [['old' => 'new#'], 'targets are internal chapter slugs'];
        yield 'non-string target' => [['old' => ['new']], 'the target must be a chapter slug, got array'];
        yield 'bad old path' => [['/old' => 'new'], 'Redirect "/old": the old path must be a chapter slug'];
        yield 'self loop' => [['old' => 'old'], 'Redirect cycle: old → old.'];
        yield 'two-step cycle' => [['a' => 'b', 'b' => 'a'], 'Redirect cycle: a → b → a.'];
        yield 'anchor self loop' => [['a#x' => 'a#x'], 'Redirect cycle: a#x → a#x.'];
        yield 'chapter into its own anchor' => [['a' => 'a#x'], 'Redirect cycle: a → a#x → a#x.'];
        yield 'cycle through a moved anchor' => [['a' => 'b', 'b' => 'c', 'b#x' => 'a#x'], 'Redirect cycle: a#x → b#x → a#x.'];
    }

    /**
     * @param array<mixed> $rules
     */
    #[DataProvider('invalidMaps')]
    public function testInvalidMapsAreReported(array $rules, string $error): void
    {
        $errors = RedirectMapCompiler::compile($rules)->errors;

        self::assertNotSame([], $errors);
        self::assertStringContainsString($error, implode(' ', $errors));

        $this->expectException(InvalidArgumentException::class);
        ChapterRedirectMap::fromRules($rules);
    }

    public function testChainsAreCollapsedToTheFinalTarget(): void
    {
        $compiled = RedirectMapCompiler::compile([
            'a'     => 'b',
            'b'     => 'c',
            'c'     => 'd#top',
            'x#one' => 'b#one',
            '404'   => 'd',
        ]);

        self::assertSame([], $compiled->errors);
        self::assertSame(['404' => 'd', 'a' => 'd#top', 'b' => 'd#top', 'c' => 'd#top'], $compiled->chapters);
        self::assertSame(['a' => 'd#top', 'b' => 'd#top', 'x#one' => 'd#top'], $compiled->chains);
        self::assertSame('d#top', RedirectMapCompiler::resolvePosition('a#any', $compiled->rules), 'a target anchor wins over the fragment');
        self::assertSame('d#any', RedirectMapCompiler::resolvePosition('404#any', $compiled->rules), 'the fragment travels');
        self::assertNull(RedirectMapCompiler::resolvePosition('d', $compiled->rules));
        self::assertNull(RedirectMapCompiler::resolvePosition('d#top', $compiled->rules));
        self::assertNull(RedirectMapCompiler::resolvePosition('a', ['a' => 'b', 'b' => 'a']), 'a cycle resolves nowhere');
        self::assertSame('b#x', RedirectMapCompiler::resolvePosition('a#x', ['a' => 'b', 'b' => 'c'], static fn (string $slug): bool => $slug === 'b'), 'the walk stops at an existing chapter');
    }

    public function testAnchorLookupsPerLandingChapter(): void
    {
        $map = ChapterRedirectMap::fromRules([
            'old'          => 'new',
            'old#install'  => 'deploy#docker',
            'old#same'     => 'new#same',
            'old#renamed'  => 'new#fresh',
            'older'        => 'old',
            'older#legacy' => 'faq',
            'merged'       => 'new#top',
            'merged#x'     => 'other#y',
            'plain'        => 'new#top',
            'new#gone'     => 'faq#gone',
            'faq#q'        => 'faq#questions',
        ]);

        self::assertSame(['install' => 'deploy#docker', 'renamed' => 'new#fresh'], $this->strings($map->movedAnchors('new', 'old')), 'an anchor that lands on the same anchor needs no script');
        self::assertSame(['install' => 'deploy#docker', 'legacy' => 'faq', 'renamed' => 'new#fresh'], $this->strings($map->movedAnchors('new', 'older')), 'the anchors of every chapter of the chain');
        self::assertSame(['' => 'new#top', 'x' => 'other#y'], $this->strings($map->movedAnchors('new', 'merged')), 'no fragment in the 301: the script places a reader who came without one');
        self::assertSame([], $map->movedAnchors('new', 'plain'));
        self::assertSame([], $map->movedAnchors('faq', 'old'), 'only on the landing chapter');
        self::assertSame(['gone' => 'faq#gone'], $this->strings($map->ownAnchors('new')));
        self::assertSame(['q' => 'faq#questions'], $this->strings($map->ownAnchors('faq')));
        self::assertSame([], $map->ownAnchors('old'), 'the anchors of a moved chapter are not own anchors');

        self::assertTrue($map->hasMovedAnchors('old'));
        self::assertTrue($map->hasMovedAnchors('merged'));
        self::assertFalse($map->hasMovedAnchors('plain'));
        self::assertFalse($map->hasMovedAnchors('new'));
        self::assertEquals(new RedirectTarget('new', 'top'), $map->chapterTarget('plain'));
        self::assertEquals(new RedirectTarget('new'), $map->chapterTarget('older'));
        self::assertNull($map->chapterTarget('new'));
        self::assertSame('deploy#docker', $map->rules()['old#install']);
    }

    public function testAnEmptyMapRedirectsNothing(): void
    {
        $map = new ChapterRedirectMap();

        self::assertNull($map->chapterTarget('any'));
        self::assertFalse($map->hasMovedAnchors('any'));
        self::assertSame([], $map->movedAnchors('a', 'b'));
        self::assertSame([], $map->ownAnchors('a'));
        self::assertSame([], $map->rules());
    }

    public function testAccentedAnchorsAreAcceptedButSlugsStayAscii(): void
    {
        $map = ChapterRedirectMap::fromRules(['guide#configuración' => 'setup#configuración']);
        self::assertSame(['configuración' => 'setup#configuración'], $this->strings($map->ownAnchors('guide')));

        self::assertNotSame([], RedirectMapCompiler::compile(['guía' => 'guide'])->errors);
    }

    /**
     * @param array<string, RedirectTarget> $targets
     *
     * @return array<string, string>
     */
    private function strings(array $targets): array
    {
        return array_map(static fn (RedirectTarget $target): string => $target->toString(), $targets);
    }
}
