<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Catalog;

use Nowo\ManualKitBundle\Model\ManualChapter;
use Nowo\ManualKitBundle\Model\TranslationState;

use function array_diff;
use function array_values;
use function is_file;

/**
 * What `nowo:manual:check` reports: per locale and chapter, the translation state and the stable anchors
 * (`<a id="…"></a>`) of the source that the translation lacks — the app's links to those sections would break.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class TranslationAudit
{
    private const string ANCHOR = '/<a\s+id="([A-Za-z0-9_-]+)"\s*><\/a>/';

    /**
     * Receives the catalog whose translations are audited.
     */
    public function __construct(
        private ManualCatalog $catalog,
    ) {
    }

    /**
     * @return list<array{locale: string, slug: string, state: TranslationState, missing_anchors: list<string>}>
     */
    public function run(): array
    {
        $rows = [];
        foreach ($this->catalog->locales() as $locale) {
            if (!$this->catalog->translates($locale)) {
                continue;
            }
            foreach ($this->catalog->all($locale) as $chapter) {
                if ($chapter->external) {
                    continue;
                }
                $missing = [];
                if ($chapter->translation !== TranslationState::Missing) {
                    $missing = array_values(array_diff(
                        self::anchors((string) file_get_contents($this->catalog->sourcePath($chapter->slug))),
                        self::anchors((string) file_get_contents($chapter->path)),
                    ));
                }
                $rows[] = ['locale' => $locale, 'slug' => $chapter->slug, 'state' => $chapter->translation, 'missing_anchors' => $missing];
            }
        }

        return $rows;
    }

    /**
     * Records the current source hash in a translation (`<!-- translation-of: … -->` added or replaced on top).
     *
     * @return bool false when the locale is not a translation locale, the chapter is not in the manifest or the
     *              translation file does not exist
     */
    public function stamp(string $slug, string $locale): bool
    {
        $chapter = $this->catalog->find($slug);
        if (!$this->catalog->translates($locale) || !$chapter instanceof ManualChapter || $chapter->external) {
            return false;
        }
        $path = $this->catalog->translationPath($slug, $locale);
        if (!is_file($path)) {
            return false;
        }
        $marker      = '<!-- translation-of: ' . ManualCatalog::sourceHash((string) file_get_contents($this->catalog->sourcePath($slug))) . ' -->';
        $translation = (string) file_get_contents($path);
        $stamped     = preg_match(ManualCatalog::TRANSLATION_MARKER, $translation) === 1
            ? (string) preg_replace(ManualCatalog::TRANSLATION_MARKER, $marker, $translation, 1)
            : $marker . "\n" . $translation;
        file_put_contents($path, $stamped);

        return true;
    }

    /**
     * @return list<string>
     */
    public static function anchors(string $markdown): array
    {
        preg_match_all(self::ANCHOR, $markdown, $matches);

        return $matches[1];
    }
}
