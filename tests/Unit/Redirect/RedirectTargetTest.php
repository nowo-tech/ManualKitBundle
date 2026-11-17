<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\Redirect;

use InvalidArgumentException;
use Nowo\ManualKitBundle\Redirect\RedirectTarget;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RedirectTargetTest extends TestCase
{
    public function testParsesAndPrintsSlugsAndAnchors(): void
    {
        $target = RedirectTarget::fromString('setup#docker');
        self::assertSame(['setup', 'docker', 'setup#docker'], [$target->slug, $target->anchor, $target->toString()]);

        $target = RedirectTarget::fromString('setup');
        self::assertSame(['setup', null, 'setup'], [$target->slug, $target->anchor, $target->toString()]);
        self::assertTrue(RedirectTarget::isValid('a-1#b_2'));
    }

    /**
     * @return iterable<string, array{0: string, 1: string|null}>
     */
    public static function invalid(): iterable
    {
        yield 'url' => ['https://evil.example', null];
        yield 'protocol relative' => ['//evil', null];
        yield 'path' => ['a/b', null];
        yield 'empty' => ['', null];
        yield 'anchor with a slash' => ['a', 'b/c'];
        yield 'empty anchor' => ['a', ''];
    }

    #[DataProvider('invalid')]
    public function testRefusesAnythingButInternalSlugs(string $slug, ?string $anchor): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a chapter slug');

        new RedirectTarget($slug, $anchor);
    }
}
