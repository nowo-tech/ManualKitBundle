<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Model;

/**
 * Which file a chapter is served from for the requested locale.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
enum TranslationState: string
{
    /** The requested locale is the manual's source language: the root file. */
    case Source = 'source';
    /** `{locale}/{slug}.md` exists and was written from the current source. */
    case Translated = 'translated';
    /** `{locale}/{slug}.md` exists but its source changed since (`<!-- translation-of: … -->` differs). */
    case Outdated = 'outdated';
    /** No translation yet: the source file is served, with a notice. */
    case Missing = 'missing';
}
