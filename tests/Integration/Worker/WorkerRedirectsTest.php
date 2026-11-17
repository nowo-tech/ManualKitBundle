<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Integration\Worker;

use Nowo\ManualKitBundle\Tests\Functional\ChapterRedirectsTest;
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
 * 1.11, REQ-RUNTIME-002: the redirect map in ONE kernel that is never reset (FrankenPHP worker, kernel reset false).
 * N iterations alternate a member and an administrator, two locales, a redirect to a member chapter, a redirect to
 * an admin chapter and a page carrying the anchor map: every answer must match the reader and the locale of its own
 * request (nothing carried over from the previous one) and memory must stay flat.
 */
final class WorkerRedirectsTest extends TestCase
{
    private const ITERATIONS = 1000;
    private const WARM_UP    = 100;
    private const MARGIN     = 4 * 1024 * 1024;

    public function testRedirectsInAKeptAliveKernelLeakNothingAndKeepMemoryFlat(): void
    {
        $kernel = new TestKernel(ChapterRedirectsTest::CONFIG);
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
                $locale = $i % 4 < 2 ? 'en' : 'es';

                if ($i % 2 === 0) {
                    $moved = $this->get($httpKernel, '/' . $locale . '/manual/04-split', 'alice');
                    self::assertSame(301, $moved->getStatusCode(), sprintf('iteration %d', $i));
                    self::assertSame('/' . $locale . '/manual/setup?moved_from=04-split', $moved->headers->get('Location'), sprintf('iteration %d: locale of this request', $i));

                    $hidden = $this->get($httpKernel, '/' . $locale . '/manual/03-old-admin', 'alice');
                    self::assertSame(404, $hidden->getStatusCode(), sprintf('iteration %d: a member never learns where the admin chapter went', $i));

                    $page = (string) $this->get($httpKernel, '/' . $locale . '/manual/setup?moved_from=04-split', 'alice')->getContent();
                    self::assertTrue(str_contains($page, '/' . $locale . '/manual/setup/deploy#docker'), sprintf('iteration %d', $i));
                    self::assertFalse(str_contains($page, 'ops#secret'), sprintf('iteration %d: the admin target leaked to a member', $i));
                } else {
                    $admin = $this->get($httpKernel, '/' . $locale . '/manual/03-old-admin', 'admin');
                    self::assertSame(301, $admin->getStatusCode(), sprintf('iteration %d', $i));
                    self::assertSame('/' . $locale . '/manual/ops', $admin->headers->get('Location'), sprintf('iteration %d', $i));

                    $page = (string) $this->get($httpKernel, '/' . $locale . '/manual/setup', 'admin')->getContent();
                    self::assertTrue(str_contains($page, '/' . $locale . '/manual/ops#secret'), sprintf('iteration %d', $i));
                    self::assertFalse(str_contains($page, 'moved_from') || str_contains($page, 'deploy#docker'), sprintf('iteration %d: the previous request\'s marker leaked', $i));
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
