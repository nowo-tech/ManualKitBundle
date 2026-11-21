<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Integration\Worker;

use Nowo\ManualKitBundle\Tests\Support\AudiencesTestKernel;
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
 * 1.13, REQ-RUNTIME-002: ONE kernel, never reset (FrankenPHP worker, kernel reset false). Readers of different
 * audiences and roots take turns — a member, a developer and a portal contact — and each one only ever gets their own
 * chapters and blocks: no reader, audience or root is remembered from one request to the next. Memory stays flat.
 */
final class WorkerAudiencesTest extends TestCase
{
    private const ITERATIONS = 900;
    private const WARM_UP    = 90;
    private const MARGIN     = 4 * 1024 * 1024;

    public function testReadersOfOtherAudiencesAndRootsShareNothingInOneKernel(): void
    {
        $kernel = new AudiencesTestKernel();
        $kernel->boot();

        try {
            $httpKernel = $kernel->getContainer()->get('http_kernel');
            self::assertInstanceOf(HttpKernelInterface::class, $httpKernel);

            $baseline = 0;
            for ($i = 0; $i < self::ITERATIONS; ++$i) {
                if ($i === self::WARM_UP) {
                    gc_collect_cycles();
                    $baseline = memory_get_usage();
                }
                $label = sprintf('iteration %d', $i);
                switch ($i % 3) {
                    case 0:
                        $member = $this->get($httpKernel, '/en/manual/using', 'uma');
                        self::assertSame(200, $member->getStatusCode(), $label);
                        self::assertFalse(str_contains((string) $member->getContent(), 'DEVELOPER-ONLY-text'), $label . ': a developer block reached a member');
                        self::assertSame(404, $this->get($httpKernel, '/en/manual/dev', 'uma')->getStatusCode(), $label);
                        break;
                    case 1:
                        $developer = $this->get($httpKernel, '/en/manual/using', 'dev');
                        self::assertSame(200, $developer->getStatusCode(), $label);
                        self::assertTrue(str_contains((string) $developer->getContent(), 'DEVELOPER-ONLY-text'), $label);
                        self::assertFalse(str_contains((string) $developer->getContent(), 'ADMIN-BLOCK-text'), $label);
                        self::assertSame(200, $this->get($httpKernel, '/en/manual/dev', 'dev')->getStatusCode(), $label);
                        break;
                    default:
                        $contact = $this->get($httpKernel, '/portal/help/portal-guide', 'carla');
                        self::assertSame(200, $contact->getStatusCode(), $label);
                        self::assertTrue(str_contains((string) $contact->getContent(), 'CUSTOMER-ONLY-text'), $label);
                        self::assertFalse(str_contains((string) $contact->getContent(), 'STAFF-NOTE-IN-PORTAL'), $label);
                        self::assertSame(404, $this->get($httpKernel, '/portal/help/using', 'carla')->getStatusCode(), $label);
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
