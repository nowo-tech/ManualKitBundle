<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Translation;

/**
 * A machine translation service (DeepL, LibreTranslate, …) used by `nowo:manual:translate`.
 *
 * Each text is a small XML fragment: `<b>`, `<i>` and `<s>` carry the Markdown emphasis and `<x i="N"/>` marks a
 * protected span (code, a link target, an anchor…) that must come back untouched.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface MachineTranslatorInterface
{
    /**
     * @param list<string> $texts
     *
     * @return list<string> the translations, in the same order
     */
    public function translate(array $texts, string $sourceLocale, string $targetLocale): array;
}
