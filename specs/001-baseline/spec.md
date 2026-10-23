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

- **FR-BUNDLE-001** Bundle class registers the extension and the Twig path pass.
- **FR-CFG-001** `Configuration`: `directory`, `source_locale`, `locales`, `role`, `admin_role`, `docs_url`, `in_app`,
  `template`, `layout`, `cache`, `machine_translation.*`.
- **FR-CFG-002** `NowoManualKitExtension`: container parameters, cache pool, machine-translation provider wiring.
- **FR-CAT-001** `ManualCatalog`: manifest parsing, slugs, audiences, groups, tags, titles, locales, translation
  states, neighbours, external files limited to the docs root (1.2), `include` globs / folders and manifest problems
  instead of exceptions (1.3).
- **FR-CAT-002** `TranslationAudit`: per-locale states, missing stable anchors, stamping (never external chapters).
- **FR-CMD-001** `nowo:manual:check` (`--strict`), `nowo:manual:stamp`, `nowo:manual:translate` (`--chapter`,
  `--force`, `--dry-run`).
- **FR-HTTP-001** `ManualController` + `routes.php`: index, chapter, `.md` alias redirect, member image allowlist.
- **FR-SEC-001** `ManualAccess`: `role` / `admin_role` checks.
- **FR-RENDER-001** `ManualRenderer`: CommonMark + GFM + attributes, sanitising, audience filter, link rewriting (chapter
  routes, published copy for other docs, links of external chapters from their folder), images (no intrinsic size for
  SVG), figures / galleries, stable anchors, table of contents with `toc_depth`, cache.
- **FR-MODEL-001** `ManualChapter`, `RenderedManualChapter`, `TranslationState`.
- **FR-SEARCH-001** `ManualSearchProvider` (optional SearchKit), `ManualSearchText` matcher.
- **FR-TWIG-001** `ManualLinkExtension`: `manual_href()`, `manual_is_external()`, `nowo_manual_layout()`,
  `nowo_manual_asset()`; `TwigPathsPass`; views `layout`, `chapter`.
- **FR-MT-001** `MarkdownTranslator`, `MachineTranslatorInterface`, `DeepLTranslator`, `LibreTranslateTranslator`,
  `UiGlossary`.
- **FR-ASSET-001** `manual.css` and the `manual_lightbox_controller.ts` Stimulus peer.
- **FR-I18N-001** UI strings in 7 locales (`NowoManualKitBundle.*.yaml`).

## Success criteria

- **SC-001** 100% PHP line coverage gate (`make release-check`), TypeScript coverage gate ≥ 90% (100% today).
- **SC-002** Every file of `src/` is listed in [`code-inventory.md`](code-inventory.md).
