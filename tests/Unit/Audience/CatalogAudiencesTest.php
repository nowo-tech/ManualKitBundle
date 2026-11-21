<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\Audience;

use Nowo\ManualKitBundle\Audience\ManualAudiences;
use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\Model\ManualChapter;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_put_contents;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * 1.13: the catalog reads the audiences of each manifest row (default audience, lists, inherited levels) and reports
 * unknown or malformed ones for `nowo:manual:check`; such a chapter is shown to nobody but readers of its other
 * audiences.
 */
final class CatalogAudiencesTest extends TestCase
{
    public function testEveryChapterCarriesTheAudiencesOfItsLevels(): void
    {
        $catalog = new ManualCatalog(dirname(__DIR__, 2) . '/Fixtures/audiences/manual', audiences: new ManualAudiences(
            ['user' => 'ROLE_USER', 'developer' => 'ROLE_DEVELOPER', 'customer' => 'ROLE_CRM_CONTACT'],
            'user',
        ));
        $levels = [];
        foreach ($catalog->all() as $chapter) {
            $levels[$chapter->slug] = $chapter->audienceLevels();
        }

        self::assertSame([
            'README'        => [['user']],
            'using'         => [['user']],
            'admin-guide'   => [['admin']],
            'dev'           => [['developer']],
            'dev-child'     => [['developer'], ['user']],
            'shared'        => [['admin', 'developer']],
            'portal-home'   => [['customer']],
            'portal-guide'  => [['customer']],
            'everyone'      => [['user', 'customer']],
            'portal-faq'    => [['customer']],
            'portal-nested' => [['customer']],
            'staff-typo'    => [['user']],
        ], $levels);
        self::assertSame([], $catalog->problems());
        $find = $catalog->find('dev-child') ?? self::fail('dev-child');
        self::assertTrue($find->adminOnly, '1.12 flag: not for every member');
        self::assertSame(['user'], $find->audiences());
    }

    public function testUnknownAndMalformedAudiencesAreReportedAndShownToNobody(): void
    {
        $directory = sys_get_temp_dir() . '/nowo-manual-catalog-audiences-' . uniqid('', true);
        mkdir($directory);
        file_put_contents($directory . '/manifest.yaml', "chapters:\n  - { slug: a, audience: 'admin, staff' }\n  - { slug: b, audience: [3] }\n  - { slug: c }\n");
        foreach (['a', 'b', 'c'] as $slug) {
            file_put_contents($directory . '/' . $slug . '.md', '# ' . $slug . "\n");
        }

        try {
            $catalog  = new ManualCatalog($directory);
            $chapters = [];
            foreach ($catalog->all() as $chapter) {
                $chapters[$chapter->slug] = $chapter;
            }
            self::assertSame([
                'manifest.yaml: chapter "a" names the unknown audience "staff" (known: "admin", "member"); it is shown to nobody but readers of its other audiences.',
                'manifest.yaml: chapter "b" has an invalid audience [3] (a name, "a, b" or a list of names); it is shown to nobody.',
            ], $catalog->problems());
            self::assertSame([['admin', 'staff']], $chapters['a']->audienceLevels());
            self::assertSame([['-']], $chapters['b']->audienceLevels());
            self::assertTrue($chapters['b']->adminOnly);
            self::assertSame([['member']], $chapters['c']->audienceLevels());
            self::assertFalse($chapters['c']->adminOnly);
            self::assertInstanceOf(ManualChapter::class, $catalog->find('b'));
        } finally {
            foreach (['manifest.yaml', 'a.md', 'b.md', 'c.md'] as $file) {
                unlink($directory . '/' . $file);
            }
            rmdir($directory);
        }
    }
}
