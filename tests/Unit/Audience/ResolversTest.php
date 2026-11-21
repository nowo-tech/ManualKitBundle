<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\Audience;

use InvalidArgumentException;
use Nowo\ManualKitBundle\Audience\ManualAudiences;
use Nowo\ManualKitBundle\Audience\ManualReaderResolver;
use Nowo\ManualKitBundle\Audience\ManualRoot;
use Nowo\ManualKitBundle\Audience\ManualRootResolver;
use Nowo\ManualKitBundle\Routing\ManualRouteLoader;
use Nowo\ManualKitBundle\Security\ManualAccess;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use function in_array;

/**
 * 1.13: the reader of a root (role, then one isGranted() per audience) and the root of a request (route, firewall).
 */
final class ResolversTest extends TestCase
{
    public function testTheReaderOfTheDefaultRootUsesTheAccessPolicyForMemberAndAdmin(): void
    {
        $audiences = $this->audiences();
        $resolver  = new ManualReaderResolver(new ManualAccess($this->granting('ROLE_USER', 'ROLE_DEVELOPER'), 'ROLE_USER', 'ROLE_ORG_ADMIN'), $audiences, $this->granting('ROLE_USER', 'ROLE_DEVELOPER'));

        self::assertSame(['developer', 'member', 'user'], ($resolver->reader($audiences->defaultRoot()) ?? self::fail('a reader'))->audiences);
        self::assertNull($resolver->reader($audiences->root('portal') ?? self::fail('portal')), 'no ROLE_CRM_CONTACT: no reader of the portal');

        $nobody = new ManualReaderResolver(new ManualAccess($this->granting(), 'ROLE_USER'), $audiences, $this->granting());
        self::assertNull($nobody->reader($audiences->defaultRoot()));
    }

    public function testTheReaderOfAnotherRootUsesItsRoleAndNeverGetsMember(): void
    {
        $audiences = $this->audiences();
        $contact   = $this->granting('ROLE_CRM_CONTACT');
        $resolver  = new ManualReaderResolver(new ManualAccess($contact), $audiences, $contact);
        $portal    = $audiences->root('portal') ?? self::fail('portal');

        $reader = $resolver->reader($portal) ?? self::fail('a reader');
        self::assertSame('portal', $reader->root);
        self::assertSame(['customer'], $reader->audiences);

        $public = new ManualRoot('portal', ['customer', 'member', 'admin'], '');
        self::assertSame([], (new ManualReaderResolver(new ManualAccess($this->granting()), $audiences, $this->granting())->reader($public) ?? self::fail('a public root'))->audiences, 'member is the default root only');
    }

    public function testWithoutAnAuthorizationCheckerOnlyMemberAndAdminCanBeGranted(): void
    {
        $audiences = $this->audiences();
        $everybody = $this->granting('ROLE_USER', 'ROLE_ORG_ADMIN', 'ROLE_DEVELOPER', 'ROLE_CRM_CONTACT');
        $resolver  = new ManualReaderResolver(new ManualAccess($everybody, 'ROLE_USER', 'ROLE_ORG_ADMIN'), $audiences);

        self::assertSame(['admin', 'member'], ($resolver->reader($audiences->defaultRoot()) ?? self::fail('a reader'))->audiences);
        self::assertNull($resolver->reader($audiences->root('portal') ?? self::fail('portal')), 'fail closed');
    }

    public function testTheRootOfARequest(): void
    {
        $audiences = $this->audiences();
        $plain     = new ManualRootResolver($audiences);

        self::assertSame('default', ($plain->resolve(null) ?? self::fail('a root'))->name);
        self::assertSame('default', ($plain->resolve(Request::create('/x')) ?? self::fail('a root'))->name, 'no security: the default root');
        self::assertSame('default', ($plain->forRoute(Request::create('/x')) ?? self::fail('a root'))->name);
        self::assertNull($plain->forRoute($this->routed('portal')), 'the portal lists firewalls; without security it is never served');
        self::assertNull($plain->forRoute($this->routed('nope')), 'an unknown root');
        self::assertNull($plain->forRoute($this->routed(42)));

        $inPortal = new ManualRootResolver($audiences, $this->security('portal'));
        self::assertSame('portal', ($inPortal->resolve(Request::create('/portal/search')) ?? self::fail('a root'))->name, 'the firewall of the portal');
        self::assertSame('portal', ($inPortal->forRoute($this->routed('portal')) ?? self::fail('a root'))->name);
        self::assertNull($inPortal->forRoute(Request::create('/portal/misplaced')), 'the default root in the portal firewall');

        $inMain = new ManualRootResolver($audiences, $this->security('main'));
        self::assertSame('default', ($inMain->resolve(Request::create('/search')) ?? self::fail('a root'))->name);
        self::assertNull($inMain->forRoute($this->routed('portal')), 'the portal in the main firewall');
        self::assertNull($inMain->resolve($this->routed('portal')));

        $outside = new ManualRootResolver($audiences, $this->security(null));
        self::assertSame('default', ($outside->resolve(Request::create('/public')) ?? self::fail('a root'))->name, 'no firewall at all');

        // A root without firewalls is served in any firewall no other root claims.
        $open = new ManualRootResolver(new ManualAudiences(['customer' => 'ROLE_C'], 'member', ['portal' => ['audiences' => ['customer'], 'role' => 'ROLE_C', 'home' => 'README', 'firewalls' => []]]), $this->security('main'));
        self::assertSame('portal', ($open->forRoute($this->routed('portal')) ?? self::fail('a root'))->name);
    }

    public function testTheRouteLoaderNamesAndMarksTheRoutesOfARoot(): void
    {
        $loader = new ManualRouteLoader($this->audiences());
        self::assertTrue($loader->supports('portal', 'nowo_manual'));
        self::assertFalse($loader->supports('portal', 'attribute'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must name a manual root');
        $loader->load('nope', 'nowo_manual');
    }

    private function audiences(): ManualAudiences
    {
        return new ManualAudiences(
            ['user' => 'ROLE_USER', 'admin' => 'ROLE_ORG_ADMIN', 'developer' => 'ROLE_DEVELOPER', 'customer' => 'ROLE_CRM_CONTACT'],
            'user',
            ['portal' => ['audiences' => ['customer'], 'role' => 'ROLE_CRM_CONTACT', 'home' => 'portal-home', 'firewalls' => ['portal']]],
        );
    }

    private function granting(string ...$roles): AuthorizationCheckerInterface
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnCallback(static fn (mixed $attribute): bool => in_array($attribute, $roles, true));

        return $checker;
    }

    private function routed(mixed $root): Request
    {
        $request = Request::create('/help');
        $request->attributes->set(ManualRoot::ATTRIBUTE, $root);

        return $request;
    }

    private function security(?string $firewall): Security
    {
        $security = $this->createStub(Security::class);
        $security->method('getFirewallConfig')->willReturn($firewall === null ? null : new FirewallConfig($firewall, 'security.user_checker'));

        return $security;
    }
}
