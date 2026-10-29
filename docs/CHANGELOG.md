# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
  - [Added](#added)
  - [Changed](#changed)
- [[1.4.0] - 2026-10-28](#140---2026-10-28)
  - [Security](#security)
  - [Changed](#changed-1)
- [[1.3.0] - 2026-10-23](#130---2026-10-23)
  - [Added](#added-1)
  - [Changed](#changed-2)
- [[1.2.0] - 2026-10-23](#120---2026-10-23)
  - [Added](#added-2)
  - [Fixed](#fixed)
- [[1.1.0] - 2026-10-23](#110---2026-10-23)
  - [Added](#added-3)
- [[1.0.1] - 2026-10-23](#101---2026-10-23)
  - [Fixed](#fixed-1)
- [[1.0.0] - 2026-10-23](#100---2026-10-23)
  - [Added](#added-4)

## [Unreleased]

### Added

- `machine_translation.timeout` (30 s idle) and `machine_translation.max_duration` (120 s total) on every DeepL /
  LibreTranslate request; a stalled provider now fails the chapter with `… request failed: …` (REQ-RUNTIME-001).
- Provider requests log start / success / failure (`info` / `error`) on the application logger, without the key or
  the texts; logging policy in [SECURITY](SECURITY.md#logging) (REQ-OBS-001).
- Asset package `nowo_manual_kit` (`/bundles/nowomanualkit`) registered from the extension's `prepend()`; the default
  template links `asset('css/manual.css', 'nowo_manual_kit')`; Twig `nowo_manual_asset_package()` (REQ-ASSETS-004).
- Self-contained lightbox `js/manual-lightbox.js` (Vite IIFE of Stimulus plus the controller, sources in
  `src/Resources/assets/src`, `make assets` / `pnpm run build`; CI checks the committed build is current) and
  `standalone_lightbox: true` to load it from the default template (REQ-ASSETS-001).
- Cursor pack: `.cursor/mcp.json` (Engram), `.cursor/rules/*.mdc` and the Spec Kit skills `.cursor/skills/speckit-*`
  are now versioned (REQ-IDE-001 / REQ-IDE-003 / REQ-SPECKIT-001).

### Changed

- **Strict configuration (REQ-SF-006):** empty `directory` / `template` / `layout` / `cache`, malformed locales,
  non-`http(s)` `docs_url` / provider `url`, `deepl` without `api_key` and `libretranslate` without `url` fail the
  container compile with the path and the allowed values. See [UPGRADING](UPGRADING.md#unreleased).
- `nowo_manual_asset()` resolves through the asset package instead of a hard-coded `/bundles/nowomanualkit/` prefix.
- `symfony/asset` and `psr/log` are now explicit dependencies.
- Spec Kit baseline: the code inventory is grouped by domain with a coverage summary (REQ-SPECKIT-003).

## [1.4.0] - 2026-10-28

Security: unknown chapter audience fails closed; admin_role cannot be empty. See [UPGRADING](UPGRADING.md#to-140).

### Security

- **Unknown chapter audience fails closed.** A manifest `audience` other than `member` / `admin` (a typo such as
  `admins`) made the chapter visible to members; it is now admin-only and reported in `problems()` (like audience
  blocks, which already failed closed).
- **`admin_role` cannot be empty.** `''` would have opened admin chapters, blocks and images to everybody; the
  configuration now rejects it.

### Changed

- **Developer tooling (REQ-CS-001):** `make validate-phpdoc` is read-only and now part of `make release-check`: it fails when a class or public method in `src/` has no PHPDoc block (the former `@return` check asked for tags that `cs-fix` (`no_superfluous_phpdoc_tags`) removes; `make fix-phpdoc` is gone). Added the 34 missing docblocks.

## [1.3.0] - 2026-10-23

### Added

- Manifest `include`: a glob (`../adr/*.md`) or a folder (`../ops/`) of the docs root, one chapter per Markdown file
  (slug from the file name, title from its `# Heading`, the row's group / audience / icon / tags / toc_depth for all).
- `nowo:manual:check --sources`: fails when a manifest source is missing (container start, deploy); `--strict` fails
  on manifest problems too.

### Changed

- **A manifest entry that cannot be served no longer fails the page.** A missing file, a path outside the docs root,
  a pattern that matches nothing, a bad or repeated slug, a missing or broken manifest: the chapter is left out and
  `ManualCatalog::problems()` lists why (`nowo:manual:check` prints them). In Symfony Beacon v1.97.0 a manual file the
  production image did not ship made every signed-in page answer 500.

## [1.2.0] - 2026-10-23

### Added

- **External chapters**: a manifest row with `file` (e.g. `../CHANGELOG.md`) serves another Markdown file of the docs
  root as a source-only chapter — links resolved from its own folder, never translated, skipped by the check / stamp /
  translate commands. New manifest key `toc_depth` (2–4) limits "On this page". See [USAGE](USAGE.md#external-chapters).
- `specs/001-baseline` (REQ-SPECKIT-001) with a code inventory.

### Fixed

- PHP 8.5: `getimagesize()` reads SVG files, so vector images got a fixed width / height; SVGs keep no intrinsic size
  (the CI cells on PHP 8.5 failed).

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
