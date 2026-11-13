<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Render;

use Nowo\ManualKitBundle\Model\ManualChapter;
use Nowo\ManualKitBundle\Model\RenderedManualChapter;

/**
 * Port: renders a chapter to sanitised HTML plus its table of contents.
 *
 * The default implementation is {@see ManualRenderer}; alias this interface to your own service to replace it. A
 * replacement must keep the audience rules: members never receive admin content, and the HTML must be sanitised.
 */
interface ManualRendererInterface
{
    /**
     * Renders a chapter for the given audience.
     */
    public function render(ManualChapter $chapter, bool $admin): RenderedManualChapter;
}
