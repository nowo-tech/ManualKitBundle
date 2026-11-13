<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Catalog;

use Nowo\ManualKitBundle\Model\ManualChapter;

/**
 * Port: the chapters of the manual and the files behind them.
 *
 * The default implementation is {@see ManualCatalog}, which reads `manifest.yaml`; alias this interface to your own
 * service to serve the chapters from another source. The class constants (`MANIFEST`, `SLUG`, `TRANSLATION_MARKER`)
 * and `ManualCatalog::sourceHash()` stay on the default class.
 */
interface ManualCatalogInterface
{
    /**
     * Returns the manual directory.
     */
    public function directory(): string;

    /**
     * Returns the locale the source chapters are written in.
     */
    public function sourceLocale(): string;

    /**
     * @return list<string>
     */
    public function locales(): array;

    /**
     * @return list<ManualChapter>
     */
    public function all(?string $locale = null): array;

    /**
     * What the manifest asks for but cannot be served (the chapter is left out instead of failing the page).
     *
     * @return list<string>
     */
    public function problems(): array;

    /**
     * The translation file of a chapter when the commands may write it, null when it resolves outside the manual.
     */
    public function writableTranslationPath(string $slug, string $locale, string $folder = ''): ?string;

    /**
     * @return list<ManualChapter>
     */
    public function visible(bool $admin, ?string $locale = null): array;

    /**
     * Finds a chapter by slug in the given locale, or null.
     */
    public function find(string $slug, ?string $locale = null): ?ManualChapter;

    /**
     * The chapters before and after `$chapter` among those the viewer may read.
     *
     * @return array{0: ManualChapter|null, 1: ManualChapter|null}
     */
    public function neighbours(ManualChapter $chapter, bool $admin, ?string $locale = null): array;

    /**
     * The chapters right under `$chapter` that the viewer may read, in manual order.
     *
     * @return list<ManualChapter>
     */
    public function children(ManualChapter $chapter, bool $admin, ?string $locale = null): array;

    /**
     * The chapters above `$chapter`, top-level first.
     *
     * @return list<ManualChapter>
     */
    public function ancestors(ManualChapter $chapter, ?string $locale = null): array;

    /**
     * Returns the path of a source-language chapter file (`$folder` = its sub-folder of the manual).
     */
    public function sourcePath(string $slug, string $folder = ''): string;

    /**
     * Returns the path of a chapter translation file (in the same sub-folder as its source).
     */
    public function translationPath(string $slug, string $locale, string $folder = ''): string;

    /**
     * Whether `$locale` reads translations (a configured locale other than the source one).
     */
    public function translates(?string $locale): bool;
}
