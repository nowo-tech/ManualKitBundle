<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\Catalog;

use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\Catalog\TranslationAudit;
use Nowo\ManualKitBundle\Model\TranslationState;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class TranslationAuditTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/manual-audit-' . bin2hex(random_bytes(4));
        mkdir($this->directory . '/es', recursive: true);
        file_put_contents($this->directory . '/manifest.yaml', "chapters:\n  - { slug: README }\n  - { slug: 01-a }\n");
        file_put_contents($this->directory . '/README.md', "# Home\n\n<a id=\"start\"></a>\n\n## Start\n\n## Next <a id=\"next\"></a>\n");
        file_put_contents($this->directory . '/01-a.md', "# First\n");
        file_put_contents($this->directory . '/es/README.md', "# Inicio\n\n## Siguiente <a id=\"next\"></a>\n");
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testReportsStatesAndMissingAnchorsPerConfiguredLocale(): void
    {
        $audit = new TranslationAudit(new ManualCatalog($this->directory, 'en', ['en', 'es']));

        self::assertSame([
            ['locale' => 'es', 'slug' => 'README', 'state' => TranslationState::Outdated, 'missing_anchors' => ['start']],
            ['locale' => 'es', 'slug' => '01-a', 'state' => TranslationState::Missing, 'missing_anchors' => []],
        ], $audit->run());
        self::assertSame([], new TranslationAudit(new ManualCatalog($this->directory))->run(), 'no locale configured');
    }

    public function testStampAddsThenReplacesTheMarker(): void
    {
        $catalog = new ManualCatalog($this->directory, 'en', ['es']);
        $audit   = new TranslationAudit($catalog);

        self::assertFalse($audit->stamp('01-a', 'es'), 'no translation file');
        self::assertFalse($audit->stamp('README', '..'), 'not a translation locale');
        self::assertFalse($audit->stamp('../README', 'es'), 'not a manifest chapter');
        self::assertTrue($audit->stamp('README', 'es'));
        $readme = $catalog->find('README', 'es') ?? self::fail('README');
        self::assertSame([TranslationState::Translated, 'Inicio'], [$readme->translation, $readme->title], 'the heading still follows the marker');

        file_put_contents($this->directory . '/README.md', "# Home, changed\n");
        self::assertSame(TranslationState::Outdated, $catalog->find('README', 'es')->translation);
        self::assertTrue($audit->stamp('README', 'es'));
        self::assertSame(1, substr_count((string) file_get_contents($this->directory . '/es/README.md'), 'translation-of'), 'replaced, not stacked');
        self::assertSame(TranslationState::Translated, $catalog->find('README', 'es')->translation);
    }

    public function testAnchors(): void
    {
        self::assertSame(['a', 'b-2'], TranslationAudit::anchors('<a id="a"></a> x <a  id="b-2" ></a> <a id="c">text</a>'));
    }
}
