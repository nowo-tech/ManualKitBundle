<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Integration\Worker;

use Nowo\ManualKitBundle\Tests\Support\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

use function gc_collect_cycles;
use function memory_get_usage;
use function sprintf;
use function str_contains;

/**
 * REQ-RUNTIME-002: ONE kernel, `services_resetter` / `kernel.reset` never called (FrankenPHP worker, kernel reset
 * false). N iterations alternate a member (alice, `ROLE_USER`) and an administrator (admin, `ROLE_ADMIN`) and two
 * locales through `http_kernel` handle() + terminate(): the member must never see the admin chapter (404, no existence
 * oracle), the administrator always must, each page shows only its own locale and memory must stay flat.
 */
final class WorkerKeptAliveKernelTest extends TestCase
{
    private const ITERATIONS = 1200;
    private const WARM_UP    = 100;
    private const MARGIN     = 4 * 1024 * 1024;

    public function testAlternatingUsersAndLocalesInOneKernelLeakNothingAndKeepMemoryFlat(): void
    {
        $kernel = new TestKernel();
        $kernel->boot();

        try {
            $httpKernel = $kernel->getContainer()->get('http_kernel');
            self::assertInstanceOf(HttpKernelInterface::class, $httpKernel);
            self::assertInstanceOf(TerminableInterface::class, $httpKernel);

            $baseline = 0;
            for ($i = 0; $i < self::ITERATIONS; ++$i) {
                if ($i === self::WARM_UP) {
                    gc_collect_cycles();
                    $baseline = memory_get_usage();
                }

                if ($i % 2 === 0) {
                    $member = $this->get($httpKernel, '/en/manual/01-using', 'alice');
                    self::assertSame(200, $member->getStatusCode(), sprintf('iteration %d', $i));
                    self::assertTrue(str_contains((string) $member->getContent(), 'Using the app'), sprintf('iteration %d', $i));
                    self::assertFalse(str_contains((string) $member->getContent(), 'Administration'), sprintf('iteration %d: admin data visible to a member', $i));

                    $hidden = $this->get($httpKernel, '/en/manual/02-admin', 'alice');
                    self::assertSame(404, $hidden->getStatusCode(), sprintf('iteration %d: admin chapter must stay hidden from a member', $i));
                } else {
                    $admin = $this->get($httpKernel, '/en/manual/02-admin', 'admin');
                    self::assertSame(200, $admin->getStatusCode(), sprintf('iteration %d', $i));
                    self::assertTrue(str_contains((string) $admin->getContent(), 'Administration'), sprintf('iteration %d', $i));

                    $spanish = $this->get($httpKernel, '/es/manual/01-using', 'admin');
                    self::assertSame(200, $spanish->getStatusCode(), sprintf('iteration %d', $i));
                    self::assertTrue(str_contains((string) $spanish->getContent(), 'Usar la aplicación'), sprintf('iteration %d', $i));
                    self::assertFalse(str_contains((string) $spanish->getContent(), 'Using the app'), sprintf('iteration %d: other locale present', $i));
                }
            }

            gc_collect_cycles();
            $growth = memory_get_usage() - $baseline;
            self::assertLessThan(self::MARGIN, $growth, sprintf('memory grew by %d bytes over %d iterations', $growth, self::ITERATIONS - self::WARM_UP));
        } finally {
            $kernel->shutdown();
        }
    }

    private function get(HttpKernelInterface $httpKernel, string $path, string $user): Response
    {
        $request  = Request::create($path, 'GET', [], [], [], ['PHP_AUTH_USER' => $user, 'PHP_AUTH_PW' => 'x']);
        $response = $httpKernel->handle($request);
        self::assertInstanceOf(TerminableInterface::class, $httpKernel);
        $httpKernel->terminate($request, $response);

        return $response;
    }
}
