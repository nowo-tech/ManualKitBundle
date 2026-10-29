# Feature Specification: ManualKitBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Created**: 2026-10-23  
**Status**: Active  
**Input**: GitHub Spec Kit baseline documenting 100% of production code in `src/` (REQ-SPECKIT-001).

**Related docs**: [`docs/USAGE.md`](../../docs/USAGE.md), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/SECURITY.md`](../../docs/SECURITY.md)  
**Code inventory (traceability)**: [`code-inventory.md`](code-inventory.md)

---

## Summary

**Package**: `nowo-tech/manual-kit-bundle`  
**Configuration root**: `nowo_manual_kit`

An in-app product manual served from the Markdown of `docs/manual/` (the same files GitHub renders): a manifest of
chapters with audiences, one folder per translation locale with stale-translation detection, stable anchors, figures
with a lightbox, an optional SearchKit provider, machine translation of missing chapters (1.1) and source-only
chapters taken from other files of the docs root such as the CHANGELOG (1.2).

## User Scenarios & Testing

### User Story 1 — Read the manual in the app (Priority: P1)

**Independent Test**: sign in, open `/manual`: the chapters of `manifest.yaml` in order, the home page, a chapter with
its "On this page" list, previous / next links, figures served from `images/`.

**Acceptance Scenarios**: an unlisted file is never served (404); an `audience: admin` chapter is a 404 for members and
its links render as plain text; `<!-- audience: admin -->` blocks are dropped for members and fail closed.

### User Story 2 — Translate it (Priority: P1)

**Independent Test**: add `docs/manual/es/02-dashboard.md`, stamp it, change the source: the chapter shows an
outdated notice; `nowo:manual:check --strict` fails until it is updated and stamped again.

### User Story 3 — Find it (Priority: P2)

**Independent Test**: with SearchKit installed, the header search lists chapters and sections by title and tags,
case- and accent-insensitive, among the chapters the viewer may read.

### User Story 4 — Machine-translate missing chapters (Priority: P3)

**Independent Test**: configure DeepL or LibreTranslate, run `nowo:manual:translate es`: missing / outdated chapters
are written with the machine marker and stamped; reviewed files are kept unless `--force`.

### User Story 5 — Serve the CHANGELOG (Priority: P3)

**Independent Test**: manifest row `{ slug: changelog, file: ../CHANGELOG.md, audience: admin, toc_depth: 2 }`: the
file is served as a source-only chapter, its relative links resolve from its own folder, only `##` headings are
listed in "On this page", and translation commands skip it.

## Requirements

### Bundle & DI

- **FR-BUNDLE-001** `NowoManualKitBundle` registers the extension (alias `nowo_manual_kit`) and `TwigPathsPass`.
- **FR-CFG-001** `Configuration`: `directory`, `source_locale`, `locales`, `role`, `admin_role`, `docs_url`, `in_app`,
  `template`, `layout`, `standalone_lightbox`, `cache`, `machine_translation.*` (`provider`, `api_key`, `url`,
  `timeout`, `max_duration`, `formality`, `languages`, `glossary_domains`). Strict tree (REQ-SF-006): unknown keys,
  empty paths / `admin_role`, malformed locales, non-`http(s)` URLs, providers without their key / URL and timeouts
  below 1 s fail the compile with the path and the allowed values.
- **FR-CFG-002** `NowoManualKitExtension` + `services.php`: `nowo_manual_kit.*` parameters, cache pool, Twig extension
  wiring (asset packages, standalone lightbox flag), machine-translation provider with its timeouts and the logger.
- **FR-CFG-003** `NowoManualKitExtension::prepend()`: asset package `nowo_manual_kit` → `/bundles/nowomanualkit`
  (REQ-ASSETS-004).

### Catalog

- **FR-CAT-001** `ManualCatalog`: manifest parsing, slugs, audiences, groups, tags, titles, locales, translation
  states, neighbours, external files limited to the docs root (1.2), `include` globs / folders and manifest problems
  instead of exceptions (1.3).
- **FR-CAT-002** `TranslationAudit`: per-locale states, missing stable anchors, stamping (never external chapters).

### CLI

- **FR-CMD-001** `nowo:manual:check` (`--strict`, `--sources`): missing / outdated translations, missing anchors,
  manifest problems.
- **FR-CMD-002** `nowo:manual:stamp`: records the source hash a translation was made from.
- **FR-CMD-003** `nowo:manual:translate` (`--chapter`, `--force`, `--dry-run`): machine-translates missing / outdated
  chapters through the configured provider.

### HTTP & security

- **FR-HTTP-001** `ManualController` + `routes.php`: index, chapter, `.md` alias redirect, member image allowlist.
- **FR-SEC-001** `ManualAccess`: `role` / `admin_role` checks; admin content fails closed.

### Rendering & model

- **FR-RENDER-001** `ManualRenderer`: CommonMark + GFM + attributes, sanitising, audience filter, link rewriting (chapter
  routes, published copy for other docs, links of external chapters from their folder), images (no intrinsic size for
  SVG), figures / galleries, stable anchors, table of contents with `toc_depth`, cache.
- **FR-MODEL-001** `ManualChapter`, `RenderedManualChapter`, `TranslationState` (value objects).

### Search

- **FR-SEARCH-001** `ManualSearchProvider` (registered only with SearchKit): chapters and sections the viewer may read.
- **FR-SEARCH-002** `ManualSearchText`: case- and accent-insensitive all-words matcher.

### Twig

- **FR-TWIG-001** `ManualLinkExtension`: `manual_href()`, `manual_is_external()`, `nowo_manual_layout()`,
  `nowo_manual_asset()` (through the `nowo_manual_kit` asset package), `nowo_manual_asset_package()`,
  `nowo_manual_standalone_lightbox()`.
- **FR-TWIG-002** `TwigPathsPass`: `templates/bundles/NowoManualKitBundle` overrides first.
- **FR-TWIG-003** Views `layout.html.twig` (bare page) and `chapter.html.twig` (navigation, chapter, lightbox markup,
  `asset(…, 'nowo_manual_kit')` links, optional standalone script).

### Machine translation

- **FR-MT-001** `MarkdownTranslator`: sends only prose (paragraphs, headings, list items, table cells) to the
  provider in batches; keeps fenced code, `<a id>` anchors, HTML comments and HTML lines; writes the machine marker.
- **FR-MT-002** `MachineTranslatorInterface`: provider contract (texts in, same count out).
- **FR-MT-003** `DeepLTranslator` and `LibreTranslateTranslator`: provider requests with an idle `timeout` and a
  `max_duration` deadline (REQ-RUNTIME-001); start / success / failure logged without the key or the texts
  (REQ-OBS-001); transport errors and malformed answers end in a domain `RuntimeException`.
- **FR-MT-004** `UiGlossary`: UI labels of the configured translation domains replace bold labels in chapters.

### Assets

- **FR-ASSET-001** `public/css/manual.css`: manual layout, CSS custom properties `--nowo-manual-*`.
- **FR-ASSET-002** `stimulus-peers/manual_lightbox_controller.ts`: Stimulus lightbox the host registers as
  `nowo-manual-lightbox` (captions via `textContent`).
- **FR-ASSET-003** `assets/src/register-lightbox.ts` + `assets/src/manual-lightbox.ts`: standalone entry that starts
  Stimulus and registers the peer.
- **FR-BUILD-001** `public/js/manual-lightbox.js`: Vite IIFE output of FR-ASSET-003 (`pnpm run build`; CI checks it is
  current).

### I18N

- **FR-I18N-001** UI strings in 7 locales (`NowoManualKitBundle.*.yaml`, domain `NowoManualKitBundle`).

## Key entities

- **Manifest** (`manifest.yaml`): ordered chapter rows (`slug`, `file`, `include`, `group`, `audience`, `tags`,
  `toc_depth`, `icon`).
- **ManualChapter**: a served chapter in a locale with its `TranslationState` (`source`, `translated`, `outdated`,
  `missing`); `external` marks a source-only file of the docs root (never translated).
- **RenderedManualChapter**: sanitised HTML + table of contents for one audience.

## Non-goals

- Editing the manual in the browser, a full-text index, PDF export.
- Serving any file that is not a listed chapter or an image of `images/`.
- Calling a translation provider from a web request (only the console command does).

## Assumptions

- The manual lives in the application repository (`docs/manual`) and is trusted content, still rendered through the
  sanitiser.
- The host provides Symfony Security (firewall) and, for the lightbox, either its own Stimulus build or
  `standalone_lightbox: true`.

## Success criteria

- **SC-001** 100% PHP line coverage gate (`make release-check`), TypeScript coverage gate ≥ 90% (100% today).
- **SC-002** Every production unit under `src/` (39: `find src -type f -not -name '*.test.ts' | wc -l`) is mapped in
  [`code-inventory.md`](code-inventory.md); no `FR-SRC-*` placeholder.
- **SC-003** Invalid configuration fails the container compile with a message naming the path (`BundleBootTest`,
  `ConfigurationTest`).

## Validation commands

```bash
make release-check          # hygiene, assets build, QA, Igor, coverage gates, translations, demos, Vitest
make test-coverage-100      # PHP 100% line gate
make test-ts                # Vitest (100% today)
make assets                 # pnpm install + vite build (src/Resources/public/js/manual-lightbox.js)
```

## Links

- [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md) — integrator stories and scope
- [`docs/SPEC-KIT.md`](../../docs/SPEC-KIT.md) — Spec Kit manual
- [`docs/INSTALLATION.md`](../../docs/INSTALLATION.md), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md),
  [`docs/USAGE.md`](../../docs/USAGE.md)
