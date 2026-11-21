<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\DependencyInjection;

use Nowo\ManualKitBundle\Audience\AudienceManifestValidator;
use Nowo\ManualKitBundle\Audience\ManualAudiences;
use Nowo\ManualKitBundle\CacheWarmer\AudienceCacheWarmer;
use Nowo\ManualKitBundle\DependencyInjection\NowoManualKitExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use UnexpectedValueException;

use function array_filter;
use function array_map;
use function basename;
use function dirname;
use function file_put_contents;
use function glob;
use function is_dir;
use function mkdir;
use function realpath;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * 1.13: the audiences configuration — `admin_role` as an alias of `audiences.admin`, the roots — is checked when the
 * container is built, and so is every audience the manifest and the chapter blocks name (deny by default, loudly).
 */
final class AudiencesWiringTest extends TestCase
{
    private const string FIXTURE = '/tests/Fixtures/audiences/manual';

    private ?string $temp = null;

    protected function tearDown(): void
    {
        if ($this->temp !== null && is_dir($this->temp)) {
            foreach (glob($this->temp . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($this->temp);
        }
    }

    public function testByDefaultAdminIsRoleAdminAndMemberIsTheDefaultAudience(): void
    {
        $container = $this->load([]);

        self::assertSame('ROLE_ADMIN', $container->getParameter('nowo_manual_kit.admin_role'));
        self::assertSame(['admin' => 'ROLE_ADMIN'], $container->getParameter('nowo_manual_kit.audiences'));
        self::assertSame([['admin' => 'ROLE_ADMIN'], 'member', [], 'ROLE_USER', []], $container->getDefinition(ManualAudiences::class)->getArguments());
    }

    public function testAdminRoleIsAnAliasOfAudiencesAdmin(): void
    {
        self::assertSame(['admin' => 'ROLE_ORG_ADMIN'], $this->load(['admin_role' => 'ROLE_ORG_ADMIN'])->getParameter('nowo_manual_kit.audiences'));
        self::assertSame('ROLE_ORG_ADMIN', $this->load(['audiences' => ['admin' => 'ROLE_ORG_ADMIN']])->getParameter('nowo_manual_kit.admin_role'));
        self::assertSame('ROLE_X', $this->load(['admin_role' => 'ROLE_X', 'audiences' => ['admin' => 'ROLE_X']])->getParameter('nowo_manual_kit.admin_role'), 'the same value twice is fine');
    }

    public function testAdminRoleAndAudiencesAdminThatDisagreeStopTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('"nowo_manual_kit.admin_role" ("ROLE_A") and "nowo_manual_kit.audiences.admin" ("ROLE_B") disagree');

        $this->load(['admin_role' => 'ROLE_A', 'audiences' => ['admin' => 'ROLE_B']]);
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidConfigurations')]
    public function testAnInvalidAudiencesConfigurationStopsTheBuild(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        $this->load($config);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidConfigurations(): iterable
    {
        $portal = static fn (array $root): array => ['audiences' => ['customer' => 'ROLE_CRM_CONTACT'], 'roots' => ['portal' => [...['audiences' => ['customer'], 'role' => 'ROLE_CRM_CONTACT', 'firewalls' => ['portal']], ...$root]]];

        yield 'member redefined' => [['audiences' => ['member' => 'ROLE_X']], 'cannot define "member"'];
        yield 'github redefined' => [['audiences' => ['github' => 'ROLE_X']], 'cannot define "github"'];
        yield 'malformed name' => [['audiences' => ['Dev-Team' => 'ROLE_X']], 'cannot define "Dev-Team"'];
        yield 'empty attribute' => [['audiences' => ['dev' => ' ']], 'needs a non-empty role'];
        yield 'empty admin_role' => [['admin_role' => ''], 'must be a non-empty role'];
        yield 'unknown default audience' => [['default_audience' => 'user'], '"nowo_manual_kit.default_audience" "user" must be'];
        yield 'default audience of another root' => [[...$portal([]), 'default_audience' => 'customer'], '"nowo_manual_kit.default_audience" "customer" must be'];
        yield 'root named default' => [['audiences' => ['customer' => 'ROLE_C'], 'roots' => ['default' => ['audiences' => ['customer'], 'role' => 'ROLE_C', 'firewalls' => ['portal']]]], 'cannot define "default"'];
        yield 'root lists member' => [$portal(['audiences' => ['member']]), '"nowo_manual_kit.roots.portal.audiences" lists "member"'];
        yield 'root lists an unknown audience' => [$portal(['audiences' => ['customers']]), '"nowo_manual_kit.roots.portal.audiences" lists "customers"'];
        yield 'root without audiences' => [$portal(['audiences' => []]), 'should have at least 1 element'];
        yield 'root with a bad home' => [$portal(['home' => '../x']), 'must be a chapter slug'];
        yield 'root with a bad role' => [$portal(['role' => 5]), 'must be a role / security attribute string'];
        yield 'root without firewalls' => [['audiences' => ['customer' => 'ROLE_C'], 'roots' => ['portal' => ['audiences' => ['customer'], 'role' => 'ROLE_C']]], 'The child config "firewalls" under "nowo_manual_kit.roots.portal" must be configured'];
        yield 'root with no firewall' => [$portal(['firewalls' => []]), 'should have at least 1 element'];
        yield 'a firewall of the default root and a root' => [[...$portal([]), 'default_firewalls' => ['main', 'portal']], 'The firewall "portal" is listed by the roots "default" and "portal"'];
        yield 'a firewall serving two roots' => [['audiences' => ['customer' => 'ROLE_C', 'partner' => 'ROLE_P'], 'roots' => [
            'portal'   => ['audiences' => ['customer'], 'role' => 'ROLE_C', 'firewalls' => ['portal']],
            'partners' => ['audiences' => ['partner'], 'role' => 'ROLE_P', 'firewalls' => ['portal']],
        ]], 'The firewall "portal" is listed by the roots "portal" and "partners"'];
    }

    public function testTheDefaultRootCanBePinnedToFirewalls(): void
    {
        $container = $this->load(['default_firewalls' => ['main']]);

        self::assertSame(['main'], $container->getParameter('nowo_manual_kit.default_firewalls'));
        self::assertSame(['main'], $container->getDefinition(ManualAudiences::class)->getArgument(4));
    }

    public function testTheFixtureManualBuildsWithItsAudiencesAndItsFilesAreTracked(): void
    {
        $container = $this->load([
            'directory'        => '%kernel.project_dir%' . self::FIXTURE,
            'audiences'        => ['user' => 'ROLE_USER', 'developer' => 'ROLE_DEVELOPER', 'customer' => 'ROLE_CRM_CONTACT'],
            'default_audience' => 'user',
            'roots'            => ['portal' => ['audiences' => ['customer'], 'role' => 'ROLE_CRM_CONTACT', 'firewalls' => ['portal']]],
        ]);

        $root    = dirname(__DIR__, 3) . self::FIXTURE;
        $tracked = array_map(static fn (object $resource): string => (string) $resource, $container->getResources());
        self::assertContains($root . '/using.md', $tracked);
        self::assertContains($root . '/dev/dev-child.md', $tracked);
        $directories = array_filter($container->getResources(), static fn (object $resource): bool => $resource instanceof DirectoryResource && $resource->getResource() === $root);
        self::assertCount(1, $directories, 'a new or removed chapter rebuilds a debug container');
    }

    public function testTheFixtureManualWithoutItsAudiencesStopsTheBuild(): void
    {
        try {
            $this->load(['directory' => '%kernel.project_dir%' . self::FIXTURE]);
            self::fail('The container should not build.');
        } catch (InvalidConfigurationException $e) {
            self::assertStringContainsString('Unknown manual audiences', $e->getMessage());
            self::assertStringContainsString('manifest.yaml: "dev" names the unknown audience "developer".', $e->getMessage());
            self::assertStringContainsString('manifest.yaml: "everyone" names the unknown audience "user", "customer".', $e->getMessage());
            self::assertStringContainsString('using.md: a block names the unknown audience "developer".', $e->getMessage());
            self::assertStringContainsString('portal-guide.md: a block names the unknown audience "user".', $e->getMessage());
        }
    }

    public function testAMalformedOrUnknownAudienceStopsTheBuild(): void
    {
        $directory = $this->manual("chapters:\n  - { slug: README }\n  - { slug: a, audience: [] }\n  - { slug: b, audience: admins }\n", ['README' => "# Home\n\n<!-- audience: github -->\nfine\n<!-- /audience -->\n", 'a' => "# A\n", 'b' => "# B\n\n<!-- audience: admin, ops -->\nx\n<!-- /audience -->\n"]);

        try {
            $this->load(['directory' => $directory]);
            self::fail('The container should not build.');
        } catch (InvalidConfigurationException $e) {
            self::assertStringContainsString('manifest.yaml: the audience of "a" is not a name, "a, b" or a list of names.', $e->getMessage());
            self::assertStringContainsString('manifest.yaml: "b" names the unknown audience "admins".', $e->getMessage());
            self::assertStringContainsString('b.md: a block names the unknown audience "ops".', $e->getMessage());
            self::assertStringNotContainsString('github', $e->getMessage(), 'github blocks are always allowed');
        }
    }

    public function testChaptersFromOutsideTheManualAreCheckedTooAndAnUnreadableManifestIsLeftToTheCatalog(): void
    {
        $directory = $this->manual("chapters:\n  - { slug: README }\n", ['README' => "# Home\n"]);
        $external  = $directory . '-outside.md';
        file_put_contents($external, "# Outside\n\n<!-- audience: partners -->\nx\n<!-- /audience -->\n");
        file_put_contents($directory . '/manifest.yaml', "chapters:\n  - { slug: README }\n  - { slug: outside, file: ../" . basename($external) . " }\n");

        try {
            $errors = AudienceManifestValidator::validate($directory, new ManualAudiences())['errors'];
            self::assertSame([realpath($external) . ': a block names the unknown audience "partners".'], $errors);
        } finally {
            unlink($external);
        }

        file_put_contents($directory . '/manifest.yaml', "chapters: [ {\n");
        self::assertSame([], AudienceManifestValidator::validate($directory, new ManualAudiences())['errors'], 'nowo:manual:check reports it');
    }

    public function testAnEnvDirectoryIsCheckedAtCacheWarmup(): void
    {
        $directory = $this->manual("chapters:\n  - { slug: README, audience: staff }\n", ['README' => "# Home\n"]);
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', dirname(__DIR__, 3));
        $container->setParameter('kernel.enabled_locales', ['en']);
        $container->setParameter('env(MANUAL_DIR)', $directory);
        new NowoManualKitExtension()->load([['directory' => '%env(MANUAL_DIR)%']], $container);
        self::assertTrue($container->hasDefinition(AudienceCacheWarmer::class), 'the build cannot read it');

        $warmer = new AudienceCacheWarmer($directory, new ManualAudiences());
        self::assertFalse($warmer->isOptional());
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('manifest.yaml: "README" names the unknown audience "staff"');
        $warmer->warmUp(sys_get_temp_dir());
    }

    public function testTheWarmerAcceptsAValidManual(): void
    {
        self::assertSame([], new AudienceCacheWarmer(dirname(__DIR__, 3) . '/tests/Fixtures/manual', new ManualAudiences())->warmUp(sys_get_temp_dir()));
        self::assertSame([], new AudienceCacheWarmer('/nowhere', new ManualAudiences())->warmUp(sys_get_temp_dir()), 'no manifest: nothing to check');
    }

    /**
     * @param array<string, string> $chapters slug => Markdown
     */
    private function manual(string $manifest, array $chapters): string
    {
        $this->temp = sys_get_temp_dir() . '/nowo-manual-audiences-' . uniqid('', true);
        mkdir($this->temp);
        file_put_contents($this->temp . '/manifest.yaml', $manifest);
        foreach ($chapters as $slug => $markdown) {
            file_put_contents($this->temp . '/' . $slug . '.md', $markdown);
        }

        return $this->temp;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', dirname(__DIR__, 3));
        $container->setParameter('kernel.enabled_locales', ['en']);
        new NowoManualKitExtension()->load([$config], $container);

        return $container;
    }
}
