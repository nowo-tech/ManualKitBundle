# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.1.0] - 2026-10-23](#110---2026-10-23)
  - [Added](#added)
- [[1.0.1] - 2026-10-23](#101---2026-10-23)
  - [Fixed](#fixed)
- [[1.0.0] - 2026-10-23](#100---2026-10-23)
  - [Added](#added-1)

## [Unreleased]

## [1.1.0] - 2026-10-23

### Added

- `nowo:manual:translate <locale>` (`--chapter`, `--force`, `--dry-run`): machine-translates the missing and outdated
  chapters with DeepL (API Free or Pro) or a self-hosted LibreTranslate, then stamps them. Only prose is sent; code,
  anchors, comments, link targets and HTML are kept, relative links get `../`, bold UI labels take the application's
  own translations, and a reviewed file (without `<!-- machine-translated -->`) is never overwritten without
  `--force`. Configuration `nowo_manual_kit.machine_translation` (off by default). See
  [USAGE](USAGE.md#machine-translation).

## [1.0.1] - 2026-10-23

### Fixed

- Docs: import the routes with `trailing_slash_on_root: false`, so the manual's home is `/manual` and not `/manual/`
  (README, INSTALLATION, `Resources/config/routes.php`). Found while moving Symfony Beacon onto the bundle.

## [1.0.0] - 2026-10-23

First release: the in-app manual of Symfony Beacon, extracted into a bundle and made translatable. See
[UPGRADING](UPGRADING.md#100-2026-10-23).

### Added

- `ManualCatalog`: chapters of `{directory}/manifest.yaml` in order (slug, group, audience, icon, tags), titles from the
  first `# ` line, neighbours among the readable chapters; a broken manifest throws.
- Translations per locale in `{directory}/{locale}/{slug}.md`, with `<!-- translation-of: <hash> -->` and the states
  `source`, `translated`, `outdated`, `missing`; untranslated chapters fall back to the source with a notice.
- `ManualRenderer`: CommonMark + GFM with GitHub heading ids and permalinks, stable anchors (`<a id>` before or inside a
  heading), chapter links → routes (also `../x.md` from translation folders), other docs → `docs_url`, audience blocks
  `admin` / `github` that fail closed, HtmlSanitizer, figures (caption, intrinsic size, portrait class, galleries),
  CommonMark attributes, table of contents with `<!-- tags: … -->`, cache per chapter / locale / audience / version.
- `ManualController`: `nowo_manual_index`, `nowo_manual_chapter`, `nowo_manual_chapter_md` (301), `nowo_manual_image`
  (sandboxing CSP, `nosniff`); `role` / `admin_role` with 404 for admin chapters.
- Twig: `manual_href()`, `manual_is_external()`; default `chapter.html.twig` and `layout.html.twig` (configurable
  `template` and `layout`), `manual.css` with `--nowo-manual-*` custom properties.
- Stimulus peer `manual_lightbox_controller.ts` (figure lightbox with previous / next, keyboard, captions).
- Commands `nowo:manual:check [--strict]` and `nowo:manual:stamp <locale> <chapter>`.
- `ManualSearchProvider` for `nowo-tech/search-kit-bundle` (registered only when it is installed).
- Translations in 7 locales (en, es, de, fr, it, nl, pt), domain `NowoManualKitBundle`.
- Symfony 8.1 FrankenPHP demo with an English / Spanish sample manual.
