<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\Translation;

use LogicException;
use Nowo\ManualKitBundle\Translation\MachineTranslatorInterface;
use Nowo\ManualKitBundle\Translation\MarkdownTranslator;
use PHPUnit\Framework\TestCase;

final class MarkdownTranslatorTest extends TestCase
{
    public function testWithoutAProviderItIsOff(): void
    {
        $markdown = new MarkdownTranslator();

        self::assertFalse($markdown->enabled());
        $this->expectException(LogicException::class);
        $markdown->translate('# Title', 'en', 'es');
    }

    public function testOnlyProseReachesTheServiceAndTheStructureStays(): void
    {
        $service  = new RecordingTranslator();
        $markdown = new MarkdownTranslator($service);
        $source   = <<<'MD'
            <!-- audience: admin -->
            # Using the app

            <a id="filters"></a>

            ## Filter the list <a id="list"></a>
            <!-- tags: search, buscar -->

            Read the [admin chapter](02-admin.md#users), [the README](README.md "Home") or
            [the docs](../PRODUCTION.md#tls), [this section](#filters) and https://example.com/a.
            Run `bin/console app:seed` and open **Settings** then **Administration → Users** or **Unknown**.

            ![Shot](images/shot.png){.manual-img--small width=320}

            - First *item* with _style_ & <kbd>K</kbd>
              continued here
            1. [x] Done ~~old~~
            > A quote

            | Column | `code` |
            |---|:---:|
            | Cell \| pipe | |

            ```bash
            echo "not translated"
            ```

            ---
            <details>
            MD;

        $out = $markdown->translate($source, 'en', 'es', ['Settings' => 'Configuración', 'Administration' => 'Administración', 'Users' => 'Usuarios']);

        self::assertSame(<<<'MD'
            <!-- audience: admin -->
            # [es] Using the app

            <a id="filters"></a>

            ## [es] Filter the list <a id="list"></a>
            <!-- tags: search, buscar -->

            [es] Read the [admin chapter](../02-admin.md#users), [the README](../README.md "Home") or [the docs](../../PRODUCTION.md#tls), [this section](#filters) and https://example.com/a. Run `bin/console app:seed` and open **Configuración** then **Administración → Usuarios** or **Unknown**.

            [es] ![Shot](../images/shot.png){.manual-img--small width=320}

            - [es] First *item* with *style* & <kbd>K</kbd> continued here
            1. [x] [es] Done ~~old~~
            > [es] A quote

            | [es] Column | `code` |
            |---|:---:|
            | [es] Cell \| pipe | |

            ```bash
            echo "not translated"
            ```

            ---
            <details>
            MD, $out);

        $sent = implode("\n", $service->texts);
        self::assertStringNotContainsString('bin/console', $sent);
        self::assertStringNotContainsString('02-admin.md', $sent);
        self::assertStringNotContainsString('not translated', $sent);
        self::assertStringNotContainsString('Configuración', $sent);
        self::assertStringContainsString('<b>Unknown</b>', $sent);
        self::assertStringContainsString('<i>item</i>', $sent);
        self::assertStringContainsString('&amp;', $sent);
        self::assertStringContainsString('<s>old</s>', $sent);
    }

    public function testBatchesAndKeepsSpansTheServiceDropped(): void
    {
        $service  = new RecordingTranslator(dropPlaceholders: true);
        $markdown = new MarkdownTranslator($service);
        $source   = implode("\n\n", array_map(static fn (int $i): string => 'Line ' . $i . ' `code' . $i . '`', range(1, 45)));

        $out = $markdown->translate($source, 'en', 'es');

        self::assertSame(2, $service->calls);
        self::assertStringContainsString('[es] Line 45 `code45`', $out);
    }

    public function testATildeFenceEndsOnlyWithItsOwnMarker(): void
    {
        $markdown = new MarkdownTranslator(new RecordingTranslator());

        self::assertSame("~~~\n```\nraw\n~~~\n[es] After", $markdown->translate("~~~\n```\nraw\n~~~\nAfter", 'en', 'es'));
    }
}

/**
 * Prefixes every text with "[es] " and records what it was sent.
 */
final class RecordingTranslator implements MachineTranslatorInterface
{
    /** @var list<string> */
    public array $texts = [];

    public int $calls = 0;

    public function __construct(private readonly bool $dropPlaceholders = false)
    {
    }

    public function translate(array $texts, string $sourceLocale, string $targetLocale): array
    {
        ++$this->calls;
        $this->texts = [...$this->texts, ...$texts];

        return array_map(fn (string $text): string => '[' . $targetLocale . '] ' . ($this->dropPlaceholders ? (string) preg_replace('/\s*<x i="\d+"\/>/', '', $text) : $text), $texts);
    }
}
