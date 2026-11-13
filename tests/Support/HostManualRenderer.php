<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Support;

use Nowo\ManualKitBundle\Model\ManualChapter;
use Nowo\ManualKitBundle\Model\RenderedManualChapter;
use Nowo\ManualKitBundle\Render\ManualRendererInterface;

/**
 * 1.10: a host renderer replacing the ManualRendererInterface port.
 */
final class HostManualRenderer implements ManualRendererInterface
{
    public function render(ManualChapter $chapter, bool $admin): RenderedManualChapter
    {
        return new RenderedManualChapter($chapter, '<p>host-rendered ' . $chapter->slug . '</p>', []);
    }
}
