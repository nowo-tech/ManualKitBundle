<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Unit\DependencyInjection;

use Nowo\ManualKitBundle\DependencyInjection\Configuration;
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
}
