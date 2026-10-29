<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\DependencyInjection;

use Nowo\ManualKitBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testAdminRoleDefaultsToRoleAdmin(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[]]);

        self::assertSame('ROLE_ADMIN', $config['admin_role']);
    }

    /** '' would show admin chapters and blocks to everybody. */
    public function testEmptyAdminRoleIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [['admin_role' => '']]);
    }

    public function testValidValuesAreAccepted(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'source_locale'       => 'pt_BR',
            'locales'             => ['es', 'de-CH', 'zh_Hant_TW'],
            'role'                => '',
            'docs_url'            => 'https://github.com/acme/app/blob/main/docs/',
            'machine_translation' => ['provider' => 'libretranslate', 'url' => 'http://libretranslate:5000', 'timeout' => 5, 'max_duration' => 5],
        ]]);

        self::assertSame('', $config['role']);
        self::assertEquals(5.0, $config['machine_translation']['timeout']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'empty directory' => [['directory' => ''], 'The path "nowo_manual_kit.directory" cannot contain an empty value'];
        yield 'bad source locale' => [['source_locale' => 'English'], 'Invalid "nowo_manual_kit.source_locale" "English": expected a locale such as "en" or "pt_BR".'];
        yield 'bad locale entry' => [['locales' => ['es', '../x']], 'Invalid entry "..\/x" in "nowo_manual_kit.locales"'];
        yield 'non-string role' => [['role' => true], '"nowo_manual_kit.role" must be a role / security attribute string'];
        yield 'relative docs url' => [['docs_url' => 'docs/'], '"nowo_manual_kit.docs_url" must be "" or an absolute http(s) URL, got "docs\/".'];
        yield 'javascript docs url' => [['docs_url' => 'javascript:alert(1)'], '"nowo_manual_kit.docs_url" must be "" or an absolute http(s) URL'];
        yield 'empty template' => [['template' => ''], 'The path "nowo_manual_kit.template" cannot contain an empty value'];
        yield 'empty layout' => [['layout' => ''], 'The path "nowo_manual_kit.layout" cannot contain an empty value'];
        yield 'empty cache' => [['cache' => ''], 'The path "nowo_manual_kit.cache" cannot contain an empty value'];
        yield 'unknown key' => [['colour' => 'blue'], 'Unrecognized option "colour" under "nowo_manual_kit"'];
        yield 'unknown provider' => [['machine_translation' => ['provider' => 'google']], 'Permissible values: "none", "deepl", "libretranslate"'];
        yield 'libretranslate without url' => [['machine_translation' => ['provider' => 'libretranslate']], '"nowo_manual_kit.machine_translation.url" is required when "provider" is "libretranslate"'];
        yield 'deepl without key' => [['machine_translation' => ['provider' => 'deepl']], '"nowo_manual_kit.machine_translation.api_key" is required when "provider" is "deepl" (e.g. %env(DEEPL_API_KEY)%).'];
        yield 'ftp provider url' => [['machine_translation' => ['url' => 'ftp://libre']], '"nowo_manual_kit.machine_translation.url" must be "" or an absolute http(s) URL'];
        yield 'zero timeout' => [['machine_translation' => ['timeout' => 0]], 'The value 0 is too small for path "nowo_manual_kit.machine_translation.timeout". Should be greater than or equal to 1'];
        yield 'deadline below timeout' => [['machine_translation' => ['timeout' => 60, 'max_duration' => 30]], '"nowo_manual_kit.machine_translation.max_duration" must be greater than or equal to "timeout".'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidConfigurations')]
    public function testInvalidValuesAreRejectedWithThePathAndTheAllowedValues(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
