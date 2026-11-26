<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Search;

/**
 * Synonym alternatives of a manual search text (1.15), for the manual's own search ({@see ManualSearch::search()}).
 * ManualKit's own port: no SearchKit needed. With nowo-tech/search-kit-bundle installed and its synonyms on,
 * {@see SearchKitManualSynonyms} implements it over SearchKit's synonym providers; a host can alias this interface to
 * its own service instead.
 *
 * Contract: return whole-text alternatives (`cambiar contraseña` → `change password`), without the text itself, as plain
 * words; keep it cheap and stateless (it runs on every search; FrankenPHP worker mode). Alternatives only widen what
 * matches: the access rules of the manual still decide what is shown.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface ManualSynonymsInterface
{
    /**
     * The alternatives of a search text.
     *
     * @param string $text The text as typed
     * @param string|null $locale The reader's locale (its synonyms first), or null
     *
     * @return list<string> Alternatives, without the text itself (may be empty)
     */
    public function alternatives(string $text, ?string $locale): array;
}
