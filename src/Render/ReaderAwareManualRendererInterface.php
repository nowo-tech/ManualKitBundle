<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Render;

use Nowo\ManualKitBundle\Audience\ManualReader;
use Nowo\ManualKitBundle\Model\ManualChapter;
use Nowo\ManualKitBundle\Model\RenderedManualChapter;

/**
 * 1.13 port: renders a chapter for one reader — a manual root and the audiences of it the user holds. The default
 * {@see ManualRenderer} implements it; the bundle calls it whenever the aliased {@see ManualRendererInterface} does. A
 * renderer that only implements {@see ManualRendererInterface} is called with `render($chapter, $reader->has('admin'))`
 * and never receives named audiences. A replacement must keep the audience rules: a block or a link to a chapter is
 * only rendered for a reader who holds one of its audiences, and the HTML must be sanitised.
 */
interface ReaderAwareManualRendererInterface extends ManualRendererInterface
{
    /**
     * Renders a chapter for this reader.
     */
    public function renderFor(ManualChapter $chapter, ManualReader $reader): RenderedManualChapter;
}
