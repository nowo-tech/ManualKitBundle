<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\Audience;

use Nowo\ManualKitBundle\Audience\ManualAudiences;
use Nowo\ManualKitBundle\Audience\ManualReader;
use Nowo\ManualKitBundle\Audience\ManualRoot;
use Nowo\ManualKitBundle\Model\ManualChapter;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * 1.13: audiences, roots and readers — plain values, no request or user state.
 */
final class AudienceModelTest extends TestCase
{
    public function testTheDefaultConfigurationIsMemberAndAdmin(): void
    {
        $audiences = new ManualAudiences();

        self::assertSame(['admin', 'member'], $audiences->names());
        self::assertSame('member', $audiences->defaultAudience());
        self::assertSame('ROLE_ADMIN', $audiences->attribute('admin'));
        self::assertNull($audiences->attribute('member'));
        self::assertFalse($audiences->isKnown('github'));
        self::assertSame([], $audiences->configErrors());
        self::assertSame([], $audiences->firewalls());
        self::assertNull($audiences->root('portal'));
        $root = $audiences->root('default') ?? self::fail('the default root');
        self::assertTrue($root->isDefault());
        self::assertSame(['admin', 'member'], $root->audiences);
        self::assertSame('ROLE_USER', $root->role);
        self::assertSame('README', $root->home);
    }

    public function testRootsTakeTheirAudiencesOutOfTheDefaultRoot(): void
    {
        $audiences = $this->beacon();

        self::assertSame(['admin', 'developer', 'member', 'user'], $audiences->defaultRoot()->audiences);
        $portal = $audiences->root('portal') ?? self::fail('the portal root');
        self::assertFalse($portal->isDefault());
        self::assertSame(['customer'], $portal->audiences);
        self::assertSame('ROLE_CRM_CONTACT', $portal->role);
        self::assertSame('portal-home', $portal->home);
        self::assertSame(['portal'], $portal->firewalls);
        self::assertTrue($portal->serves('customer'));
        self::assertFalse($portal->serves('user'));
        self::assertSame(['default', 'portal'], array_map(static fn (ManualRoot $root): string => $root->name, $audiences->roots()));
        self::assertSame(['portal' => 'portal'], $audiences->firewalls());
        self::assertSame([], $audiences->configErrors());
    }

    public function testRouteNamesOfARoot(): void
    {
        $portal = new ManualRoot('portal', ['customer']);
        self::assertSame('nowo_manual.portal.chapter', $portal->routeName('nowo_manual_chapter'));
        self::assertSame('nowo_manual.portal.image', $portal->routeName('nowo_manual_image'));
        self::assertSame('app_home', $portal->routeName('app_home'), 'not a manual route');
        self::assertSame('nowo_manual_chapter', new ManualRoot(ManualRoot::DEFAULT, ['member'])->routeName('nowo_manual_chapter'));
    }

    public function testAReaderSeesAChapterWhenTheyHoldAnAudienceOfEveryLevel(): void
    {
        $audiences = $this->beacon();
        $dev       = $this->chapter('dev', [['developer']]);
        $devChild  = $this->chapter('dev-child', [['developer'], ['user']]);
        $shared    = $this->chapter('shared', [['admin', 'developer']]);
        $everyone  = $this->chapter('everyone', [['user', 'customer']]);
        $developer = new ManualReader('default', ['user', 'developer']);
        $member    = new ManualReader('default', ['member', 'user']);
        $contact   = new ManualReader('portal', ['customer']);

        self::assertTrue($audiences->canSee($dev, $developer));
        self::assertTrue($audiences->canSee($devChild, $developer));
        self::assertTrue($audiences->canSee($shared, $developer));
        self::assertFalse($audiences->canSee($dev, $member));
        self::assertFalse($audiences->canSee($devChild, new ManualReader('default', ['developer'])), 'the own level needs user');
        self::assertTrue($audiences->canSee($everyone, $contact));
        self::assertFalse($audiences->canSee($dev, $contact));
        self::assertTrue($audiences->belongsTo($everyone, $audiences->defaultRoot()));
        self::assertFalse($audiences->belongsTo($this->chapter('portal', [['customer']]), $audiences->defaultRoot()));
    }

    public function testReaders(): void
    {
        $reader = new ManualReader('default', ['user', 'admin', 'user']);
        self::assertSame(['admin', 'user'], $reader->audiences);
        self::assertSame('default:admin,user', $reader->key());
        self::assertTrue($reader->has('admin'));
        self::assertFalse($reader->has('developer'));
        self::assertTrue($reader->holdsAny(['developer', 'user']));
        self::assertFalse($reader->holdsAny(['developer']));
        self::assertSame('default:admin,member', ManualReader::legacy(true)->key());
        self::assertSame('default:member', ManualReader::legacy(false)->key());
    }

    public function testParsing(): void
    {
        self::assertSame(['admin'], ManualAudiences::parse('admin'));
        self::assertSame(['admin', 'developer'], ManualAudiences::parse(' admin , developer, admin '));
        self::assertSame(['user', 'customer'], ManualAudiences::parse(['user', 'customer']));
        self::assertNull(ManualAudiences::parse([]));
        self::assertNull(ManualAudiences::parse(''));
        self::assertNull(ManualAudiences::parse('admin,'));
        self::assertNull(ManualAudiences::parse(['admin', 3]));
        self::assertNull(ManualAudiences::parse(7));
        self::assertSame(['admin', 'developer'], ManualAudiences::parseBlock('Admin ,DEVELOPER'));
        self::assertSame('"a", "b"', ManualAudiences::quote(['a', 'b']));
    }

    public function testChapterAudiences(): void
    {
        self::assertSame([['member']], $this->chapter('a', [])->audienceLevels(), 'a 1.12 chapter');
        self::assertSame(['member'], $this->chapter('a', [])->audiences());
        self::assertSame([['admin']], new ManualChapter('b', 'using', true, 'tabler:book', 'B', '/tmp/b.md')->audienceLevels());
        self::assertSame(['user'], $this->chapter('c', [['developer'], ['user']])->audiences());
    }

    private function beacon(): ManualAudiences
    {
        return new ManualAudiences(
            ['user' => 'ROLE_USER', 'admin' => 'ROLE_ORG_ADMIN', 'developer' => 'ROLE_DEVELOPER', 'customer' => 'ROLE_CRM_CONTACT'],
            'user',
            ['portal' => ['audiences' => ['customer'], 'role' => 'ROLE_CRM_CONTACT', 'home' => 'portal-home', 'firewalls' => ['portal']]],
        );
    }

    /**
     * @param list<list<string>> $levels
     */
    private function chapter(string $slug, array $levels): ManualChapter
    {
        return new ManualChapter($slug, 'using', false, 'tabler:book', $slug, '/tmp/' . $slug . '.md', audienceLevels: $levels);
    }
}
