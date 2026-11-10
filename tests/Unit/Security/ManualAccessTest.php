<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\Security;

use Nowo\ManualKitBundle\Model\ManualChapter;
use Nowo\ManualKitBundle\Security\AllowAllManualChapterAccessChecker;
use Nowo\ManualKitBundle\Security\ManualAccess;
use Nowo\ManualKitBundle\Security\ManualAccessInterface;
use Nowo\ManualKitBundle\Security\ManualChapterAccessCheckerInterface;
use Nowo\ManualKitBundle\Security\ReadableChapters;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ManualAccessTest extends TestCase
{
    public function testRolesAndAPublicManual(): void
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnCallback(static fn (mixed $attribute): bool => $attribute === 'ROLE_EDITOR');

        self::assertFalse(new ManualAccess($checker)->canRead());
        self::assertFalse(new ManualAccess($checker)->isAdmin());
        self::assertTrue(new ManualAccess($checker, 'ROLE_EDITOR')->canRead());
        self::assertTrue(new ManualAccess($checker, '', 'ROLE_EDITOR')->canRead(), 'empty role: public');
        self::assertTrue(new ManualAccess($checker, '', 'ROLE_EDITOR')->isAdmin());
    }

    public function testChapterAccessKeepsTheAdminOnlyRuleAndAsksTheHostRule(): void
    {
        $member = $this->createStub(AuthorizationCheckerInterface::class);
        $member->method('isGranted')->willReturnCallback(static fn (mixed $attribute): bool => $attribute === 'ROLE_USER');
        $admin = $this->createStub(AuthorizationCheckerInterface::class);
        $admin->method('isGranted')->willReturn(true);
        $noBilling = new class implements ManualChapterAccessCheckerInterface {
            public function canReadChapter(ManualChapter $chapter): bool
            {
                return $chapter->group !== 'billing';
            }
        };

        $using   = $this->chapter('using', 'using');
        $billing = $this->chapter('billing', 'billing');
        $ops     = $this->chapter('ops', 'administration', adminOnly: true);

        // Default (no host rule, as before 1.9): only the admin-only rule.
        self::assertTrue(new ManualAccess($member)->canReadChapter($billing));
        self::assertFalse(new ManualAccess($member)->canReadChapter($ops));
        self::assertTrue(new ManualAccess($admin)->canReadChapter($ops));
        self::assertTrue(new ManualAccess($member, chapters: new AllowAllManualChapterAccessChecker())->canReadChapter($using));

        self::assertFalse(new ManualAccess($member, chapters: $noBilling)->canReadChapter($billing));
        self::assertFalse(new ManualAccess($admin, chapters: $noBilling)->canReadChapter($billing), 'admins too');
        self::assertFalse(new ManualAccess($member, chapters: $noBilling)->canReadChapter($ops), 'the admin-only rule still applies first');
        self::assertTrue(new ManualAccess($member, chapters: $noBilling)->canReadChapter($using));
    }

    public function testReadableChaptersHidesTheSubPagesOfARefusedParent(): void
    {
        $access = new class implements ManualAccessInterface {
            public function canRead(): bool
            {
                return true;
            }

            public function isAdmin(): bool
            {
                return false;
            }

            public function canReadChapter(ManualChapter $chapter): bool
            {
                return $chapter->slug !== 'guide';
            }
        };
        $guide   = $this->chapter('guide', 'using');
        $install = $this->chapter('install', 'using', ancestors: ['guide']);
        $docker  = $this->chapter('docker', 'using', ancestors: ['guide', 'install']);
        $faq     = $this->chapter('faq', 'using');
        $all     = [$guide, $install, $docker, $faq];

        self::assertSame([$faq], ReadableChapters::filter($all, $all, $access));
        self::assertSame([], ReadableChapters::filter([$docker], $all, $access), 'the parent comes from the whole list');
    }

    /**
     * @param list<string> $ancestors
     */
    private function chapter(string $slug, string $group, bool $adminOnly = false, array $ancestors = []): ManualChapter
    {
        return new ManualChapter($slug, $group, $adminOnly, 'tabler:book', ucfirst($slug), '/tmp/' . $slug . '.md', ancestors: $ancestors);
    }
}
