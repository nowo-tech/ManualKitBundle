<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\Render;

use InvalidArgumentException;
use Nowo\ManualKitBundle\Render\CommentBlocks;
use Nowo\ManualKitBundle\Render\DeviceBlocks;
use Nowo\ManualKitBundle\Render\DeviceViews;
use Nowo\ManualKitBundle\Render\ManualRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeviceViewsTest extends TestCase
{
    public function testDefaultsAreOffWithThePhoneSuffix(): void
    {
        $devices = new DeviceViews();

        self::assertFalse($devices->enabled);
        self::assertSame(768, $devices->breakpoint);
        self::assertSame('.phone', $devices->imageSuffix);
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function names(): iterable
    {
        yield 'phone' => ['phone', true];
        yield 'desktop' => ['desktop', true];
        yield 'tablet' => ['tablet', false];
        yield 'capitalised' => ['Phone', false];
        yield 'array' => [['phone'], false];
        yield 'null' => [null, false];
    }

    #[DataProvider('names')]
    public function testOnlyAllowListedNamesAreDevices(mixed $name, bool $device): void
    {
        self::assertSame($device, DeviceViews::isDevice($name));
    }

    public function testCompanionNames(): void
    {
        $devices = new DeviceViews(true, 768, '.phone');

        self::assertSame('x.phone.webp', $devices->companion('x.webp'));
        self::assertSame('shot.v2.phone.png', $devices->companion('shot.v2.png'));
        self::assertNull($devices->companion('x.phone.webp'), 'a companion has no companion');
        self::assertNull($devices->companion('noextension'));
        self::assertNull($devices->companion('.webp'));
        self::assertSame('x_m.png', new DeviceViews(true, 768, '_m')->companion('x.png'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidSuffixes(): iterable
    {
        yield 'empty' => [''];
        yield 'no separator' => ['phone'];
        yield 'path' => ['/../x'];
        yield 'dot dot' => ['..'];
        yield 'slash' => ['.ph/one'];
        yield 'too long' => ['.' . str_repeat('a', 33)];
    }

    #[DataProvider('invalidSuffixes')]
    public function testUnsafeSuffixesAreRejected(string $suffix): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DeviceViews(true, 768, $suffix);
    }

    public function testCommentBlocksShareTheAudienceRules(): void
    {
        $markdown = 'a<!-- device: Phone -->b<!-- /device -->c<!--device:desktop-->d<!--/device-->e';

        self::assertSame('a[phone:b]c[desktop:d]e', CommentBlocks::replace($markdown, 'device', static fn (string $value, string $content): string => '[' . $value . ':' . $content . ']'));
        self::assertSame(['x', 'y', 'z'], CommentBlocks::splitAtMarkers('x<!-- device: phone -->y<!-- /device -->z', 'device'));
        self::assertSame(['no markers'], CommentBlocks::splitAtMarkers('no markers', 'device'));
        self::assertSame('kept', CommentBlocks::replace('kept', 'audience', static fn (): string => 'never'));
    }

    public function testTheAudienceFilterIsUnchanged(): void
    {
        $markdown = "A\n<!-- audience: admin -->B<!-- /audience -->\n<!-- audience: github -->C<!-- /audience -->\nD\n<!-- audience admin -->E";

        self::assertSame("A\n\n\nD\n", ManualRenderer::audienceFiltered($markdown, false));
        self::assertSame("A\nB\n\nD\nE", ManualRenderer::audienceFiltered($markdown, true));
        self::assertSame('plain', ManualRenderer::audienceFiltered('plain', false));
    }

    public function testMarkingWrapsOnlyKnownDevicesAndDropsStrayMarkers(): void
    {
        $marked = DeviceBlocks::mark("<!-- device: phone -->P<!-- device: desktop -->N<!-- /device -->S<!-- /device -->\n<!-- device: tablet -->T<!-- /device -->", 'TK');

        self::assertSame("\n\nTKphone\n\nPN\n\nTKend\n\nS\nT", $marked);
    }
}
