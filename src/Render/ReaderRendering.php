<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Render;

use LogicException;
use Nowo\ManualKitBundle\Audience\ManualAudiences;
use Nowo\ManualKitBundle\Audience\ManualReader;
use Nowo\ManualKitBundle\Audience\ManualRoot;
use Nowo\ManualKitBundle\Model\ManualChapter;
use Nowo\ManualKitBundle\Model\RenderedManualChapter;

/**
 * 1.13: renders a chapter for a reader with whatever renderer is aliased — {@see ReaderAwareManualRendererInterface}
 * when it implements it; else the 1.12 call (`admin` held or not), only in the default root. A 1.12 renderer is never
 * used for another root (audit MK113-M1; the container build already refuses it, this is the runtime guard).
 *
 * @internal
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ReaderRendering
{
    /**
     * The chapter rendered for the reader.
     */
    public static function render(ManualRendererInterface $renderer, ManualChapter $chapter, ManualReader $reader): RenderedManualChapter
    {
        if ($renderer instanceof ReaderAwareManualRendererInterface) {
            return $renderer->renderFor($chapter, $reader);
        }
        if ($reader->root !== ManualRoot::DEFAULT) {
            throw new LogicException('The manual renderer does not implement ' . ReaderAwareManualRendererInterface::class . ': it cannot render the chapters of the root "' . $reader->root . '".');
        }

        return $renderer->render($chapter, $reader->has(ManualAudiences::ADMIN));
    }
}
