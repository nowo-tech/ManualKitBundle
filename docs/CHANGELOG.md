# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.9.0] - 2026-11-10](#190---2026-11-10)
  - [Added](#added)
  - [Changed](#changed)
- [[1.8.0] - 2026-11-08](#180---2026-11-08)
  - [Added](#added-1)
- [[1.7.0] - 2026-11-07](#170---2026-11-07)
  - [Changed](#changed-1)
- [[1.6.0] - 2026-11-02](#160---2026-11-02)
  - [Added](#added-2)
- [[1.5.1] - 2026-10-30](#151---2026-10-30)
  - [Fixed](#fixed)
- [[1.5.0] - 2026-10-29](#150---2026-10-29)
  - [Added](#added-3)
  - [Changed](#changed-2)
- [[1.4.0] - 2026-10-28](#140---2026-10-28)
  - [Security](#security)
  - [Changed](#changed-3)
- [[1.3.0] - 2026-10-23](#130---2026-10-23)
  - [Added](#added-4)
  - [Changed](#changed-4)
- [[1.2.0] - 2026-10-23](#120---2026-10-23)
  - [Added](#added-5)
  - [Fixed](#fixed-1)
- [[1.1.0] - 2026-10-23](#110---2026-10-23)
  - [Added](#added-6)
- [[1.0.1] - 2026-10-23](#101---2026-10-23)
  - [Fixed](#fixed-2)
- [[1.0.0] - 2026-10-23](#100---2026-10-23)
  - [Added](#added-7)

## [Unreleased]

### Fixed

- `ManualRenderer` no longer calls `filemtime()` on a missing `manifest.yaml`: a host `ManualCatalogInterface` serving chapters from a directory without a manifest renders normally (the cache key skips the manifest part).

### Added

- Ports (REQ-ARCH-001 rule 1): `ManualCatalogInterface` and `ManualRendererInterface`, implemented by `ManualCatalog` and `ManualRenderer` and aliased in the container. Every bundle consumer (controller, search provider, Twig extension, commands, translation audit) type-hints the interfaces, so a host can serve chapters from another source or replace the renderer by aliasing the interface. Additive: the class ids still resolve and the constants and static helpers stay on the classes.
- `docs/ARCHITECTURE.md`: ports table and a "Standard Symfony and DDD hosts" section.

### Changed

- Demo uses the UiKit base style and FormKit forms (REQ-DEMO-014/015).

### Tests

- Tests: kept-alive worker test `WorkerKeptAliveKernelTest` (one kernel or container, never reset, 1,200 alternating iterations, memory bound) and `docs/FRANKENPHP-WORKER-AUDIT.md` refreshed for v1.9.0; no runtime change.

## [1.9.0] - 2026-11-10

Target 1.9.0: ports for DDD / hexagonal hosts (audit 2026-11-09, P2). See [UPGRADING](UPGRADING.md#to-190).

### Added

- **`machine_translation.provider: service`** + **`machine_translation.service`**: `nowo:manual:translate` uses your own
  `MachineTranslatorInterface` service (a translation gateway, another provider). `MachineTranslatorInterface` is now
  aliased to the configured provider (`nowo_manual_kit.machine_translator`).
- **`Security\ManualAccessInterface`** (`canRead()`, `isAdmin()`, `canReadChapter(ManualChapter)`), implemented by
  `ManualAccess` and aliased to it; the controller and the search provider depend on the interface.
- **`Security\ManualChapterAccessCheckerInterface`**: a per-chapter rule of the host (plan, tenant, feature flag),
  checked after the admin-only rule for navigation, previous / next, sub-pages, search and the page itself (404). A
  refused chapter hides its sub-pages. Default `AllowAllManualChapterAccessChecker` (no extra rule, as before).
  Once the host rule refuses a chapter, `/images/{file}` only serves an image shown in a chapter the viewer may open,
  for administrators too (audit KN-M-L1). The page routes always enforce `audience: admin` themselves, whatever
  `ManualAccessInterface` implementation is aliased (KN-M-L2).
- `machine_translation.service` is checked at container compile time: it must implement `MachineTranslatorInterface`
  (KN-M-I2).

### Changed

- Docs: new USAGE section "Using it from a DDD / hexagonal application" (where the bundle sits in a hexagonal host, the ports, events and DTOs to build an anti-corruption layer on, and the known limitations).

## [1.8.0] - 2026-11-08

Navigation groups and chapter page blocks, so a host page extends the bundle template instead of copying it (Beacon override B7 / MK-1). Audit: Appendix IL, Pass.

### Added

- `groups` option: order and labels of the chapter groups in the navigation (`[{key, label, translation_domain}]` or
  plain keys). Listed groups come first, the others follow in manifest order; groups with no readable chapter are left
  out. The chapter template receives the result as `groups`. Default `[]` keeps the manifest order and bundle labels.
- Chapter page blocks `manual_nav` (with `manual_nav_start`), `manual_body` (with `manual_body_start`) and
  `manual_aside`, so a host page extends the bundle template instead of copying it.
- `_macros.html.twig`: the `branch` (foldable sub-page tree) and `children` macros, importable from a host template.
  The default chapter page still renders byte for byte what 1.7 rendered (golden tests unchanged).

## [1.7.0] - 2026-11-07

### Changed

- **Lightbox motion:** the dialog and its backdrop open and close on UiKit's `--nowo-ui-motion-medium*`, `--nowo-ui-motion-scale` and easing tokens (own fallbacks: 200 ms in, 150 ms out, scale 0.98). The close button, a backdrop click and Escape wait for the fade before `dialog.close()`, with a timer if `transitionend` never fires. Reduced motion keeps an 80 ms fade with no movement; `html[data-motion="full"]` keeps the full effect. No `!important`.
- **Turbo:** running fades finish on `turbo:before-cache`, so that a cached page never holds a half-closed surface. Lightbox: audit finding HW-L5.
- **Development only, already on `main` since 1.6.0:** `ci.yml` alias `symfony/event-dispatcher-contracts:dev-main as 3.8.99`, wider `phpunit`, `igor-php` and `twig-cs-fixer` constraints, lock files. Nothing reaches a host.

## [1.6.0] - 2026-11-02

Sub-pages: chapters nest up to three levels (parent: in the manifest, files in sub-folders per locale), nested routes nowo_manual_child / nowo_manual_grandchild, inherited access that only narrows, tree navigation, breadcrumb, Parent › Child search, sub-folder translation tooling; flat manuals unchanged. See [UPGRADING](https://github.com/nowo-tech/ManualKitBundle/blob/v1.6.0/docs/UPGRADING.md#to-160).

Sub-pages: a chapter may sit under another one, up to three levels. See [UPGRADING](UPGRADING.md#unreleased).

### Added

- Manifest `parent: <slug>` (at most three levels: chapter → child → grandchild). Files may live in sub-folders
  (`guide/install.md`, translations in `es/guide/install.md`); an unknown parent, a cycle, a fourth level, the home page
  in the tree or a file outside the manual leave the branch out with a `problems()` entry.
- Routes `nowo_manual_child` (`/{parent}/{chapter}`), `nowo_manual_grandchild` (`/{grandparent}/{parent}/{chapter}`)
  and their `.md` redirects; a slug-only URL of a nested chapter redirects to its place in the tree. Existing routes
  and URLs are unchanged.
- A child inherits its parent's group and audience (narrowing only); hidden parents hide their branch (404, links as
  plain text).
- Chapter list with folded branches (the current one open), depth-first previous / next, "In this section" on parent
  pages and a breadcrumb on nested pages (UiKit's `breadcrumbs` partial when installed); template variables `ancestors`
  and `children`, Twig `manual_chapter_path()` and `nowo_manual_breadcrumbs_template()`.
- Search titles nested chapters "Parent › Child" (own tags, same access); `manual_href()` accepts
  `manual/<folder>/<slug>.md`; relative links and `../../images/…` resolve from sub-folders; translation check, stamp
  and machine translation walk the sub-folders.
- `ManualChapter::ancestors`, `ancestorTitles`, `folder`, `parent()`, `depth()`, `urlPath()`, `trailTitle()`;
  `ManualCatalog::children()` / `ancestors()`; `ManualRoutes`.
- Containment: top-level sources, translations and locale sub-folders that resolve (symbolic links) outside the
  manual are ignored with a problem, and stamp / translate never write through them; slugs no longer accept a
  trailing newline (`\z`); `guide/x.md` naming a hidden child kept at the manual root reads as plain text.
- Translations `breadcrumbs`, `children`, `nav.subpages` (7 locales). Demo: three-level example, search page,
  Playwright e2e with axe.

## [1.5.1] - 2026-10-30

Bundle routes are opt-in again: controllers are no longer autoconfigured, so a host's routing.controllers import does not expose them (REQ-SF-004 rule 7). See [UPGRADING](UPGRADING.md#to-151).

### Fixed

- **Manual routes registered without the route import:** on Symfony >= 7.3 the `#[Route]` attributes (plus `#[AsController]`) on the autoconfigured `ManualController` gave it the `routing.controller` tag, so the host's default `controllers: { resource: routing.controllers }` registered `nowo_manual_index`, `nowo_manual_chapter`, `nowo_manual_chapter_md` and `nowo_manual_image` without the host importing `@NowoManualKitBundle/Resources/config/routes.php`, and without its prefix (the manual landed on `/` and `/{chapter}`, shadowing host pages; `role` was still enforced). The controller is no longer autoconfigured and no longer carries `#[AsController]` (explicit `controller.service_arguments` tag); the routes exist only through the documented import (REQ-SF-004 rule 7). Regression test with a kernel that only loads `routing.controllers`.

## [1.5.0] - 2026-10-29

ManualKit: provider timeouts + PSR-3 logging, strict config validation, scoped asset package, standalone lightbox build See [UPGRADING](UPGRADING.md#to-150).

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
