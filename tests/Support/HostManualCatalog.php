<?php

declare(strict_types=1);

namespace Nowo\ManualKitBundle\Tests\Support;

use Nowo\ManualKitBundle\Catalog\ManualCatalog;
use Nowo\ManualKitBundle\Catalog\ManualCatalogInterface;
use Nowo\ManualKitBundle\Model\ManualChapter;

use function array_values;

/**
 * 1.10: a host catalog replacing the ManualCatalogInterface port: wraps the bundle catalog and drops the "faq" chapter.
 */
final readonly class HostManualCatalog implements ManualCatalogInterface
{
    public function __construct(private ManualCatalog $inner)
    {
    }

    public function directory(): string
    {
        return $this->inner->directory();
    }

    public function sourceLocale(): string
    {
        return $this->inner->sourceLocale();
    }

    public function locales(): array
    {
        return $this->inner->locales();
    }

    public function all(?string $locale = null): array
    {
        return $this->withoutFaq($this->inner->all($locale));
    }

    public function problems(): array
    {
        return $this->inner->problems();
    }

    public function writableTranslationPath(string $slug, string $locale, string $folder = ''): ?string
    {
        return $this->inner->writableTranslationPath($slug, $locale, $folder);
    }

    public function visible(bool $admin, ?string $locale = null): array
    {
        return $this->withoutFaq($this->inner->visible($admin, $locale));
    }

    public function find(string $slug, ?string $locale = null): ?ManualChapter
    {
        return $slug === 'faq' ? null : $this->inner->find($slug, $locale);
    }

    public function neighbours(ManualChapter $chapter, bool $admin, ?string $locale = null): array
    {
        return $this->inner->neighbours($chapter, $admin, $locale);
    }

    public function children(ManualChapter $chapter, bool $admin, ?string $locale = null): array
    {
        return $this->inner->children($chapter, $admin, $locale);
    }

    public function ancestors(ManualChapter $chapter, ?string $locale = null): array
    {
        return $this->inner->ancestors($chapter, $locale);
    }

    public function sourcePath(string $slug, string $folder = ''): string
    {
        return $this->inner->sourcePath($slug, $folder);
    }

    public function translationPath(string $slug, string $locale, string $folder = ''): string
    {
        return $this->inner->translationPath($slug, $locale, $folder);
    }

    public function translates(?string $locale): bool
    {
        return $this->inner->translates($locale);
    }

    /**
     * @param list<ManualChapter> $chapters
     *
     * @return list<ManualChapter>
     */
    private function withoutFaq(array $chapters): array
    {
        return array_values(array_filter($chapters, static fn (ManualChapter $c): bool => $c->slug !== 'faq'));
    }
}
