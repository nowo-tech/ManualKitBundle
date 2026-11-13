# Architecture

## Table of contents

- [Layers](#layers)
- [Request flow](#request-flow)
- [Rendering pipeline](#rendering-pipeline)
- [Design rules](#design-rules)
- [Public ports and extension points](#public-ports-and-extension-points)
- [Standard Symfony and DDD hosts](#standard-symfony-and-ddd-hosts)

## Layers

| Layer | Namespace / path | Role |
| --- | --- | --- |
| Model | `Model` | `ManualChapter` (slug, group, audience, icon, title, path, tags, locale, translation state), `RenderedManualChapter` (html, toc), `TranslationState` |
| Catalog | `Catalog` | `ManualCatalog`: reads `manifest.yaml`, resolves a chapter for a locale (source, translated, outdated, missing), titles, neighbours, `sourceHash()`. `TranslationAudit`: states and missing stable anchors per locale, `stamp()` |
| Rendering | `Render` | `ManualRenderer`: audience filter, CommonMark + GFM, link and image rewriting, sanitiser, DOM pass (stable anchors, figures), toc with section tags, cache |
| Access | `Security` | `ManualAccess`: `role` (readers) and `admin_role` through the authorisation checker |
| HTTP | `Controller` | `ManualController`: index, chapter, `.md` redirect, sandboxed images |
| Twig | `Twig`, `Resources/views` | `ManualLinkExtension` (`manual_href`, `manual_is_external`, `nowo_manual_layout`, `nowo_manual_asset`, `nowo_manual_asset_package`, `nowo_manual_standalone_lightbox`); `chapter.html.twig`, `layout.html.twig` |
| Console | `Command` | `nowo:manual:check [--strict]`, `nowo:manual:stamp <locale> <chapter>` |
| Search | `Search` | `ManualSearchText` (folding, every word matches title or tags); `ManualSearchProvider` (SearchKit SPI, registered only when SearchKit is installed) |
| DI | `DependencyInjection` | Flat configuration tree → `nowo_manual_kit.*` parameters, Twig paths pass (`@NowoManualKitBundle`, app overrides first) |
| Browser | `Resources/assets/stimulus-peers`, `Resources/assets/src`, `Resources/public` | `manual_lightbox_controller.ts` (opt-in Stimulus peer); `manual-lightbox.ts` → Vite IIFE `public/js/manual-lightbox.js` (standalone lightbox); `public/css/manual.css`; all served through the `nowo_manual_kit` asset package |

## Request flow

1. `GET /manual/02-dashboard` → `ManualController::chapter()`.
2. `ManualAccess::canRead()` or 403 / 401 (through the firewall's entry point).
3. `ManualCatalog::find('02-dashboard', $request->getLocale())` → the chapter resolved for the locale. Unknown slug,
   the home slug, or an admin chapter for a member → 404.
4. `ManualRenderer::render($chapter, $admin)` (cached) and `ManualCatalog::visible()` / `neighbours()` for the sidebar
   and the pager, then the configured template.

The slug in the URL is only compared with the manifest's slugs; the file path always comes from the catalog. Images
use `basename()` of a name restricted by the route requirement.

## Rendering pipeline

1. Read the resolved file (translation or source).
2. `audienceFiltered()`: keep `admin` blocks for administrators, drop them for members, always drop `github` blocks;
   cut at a stray marker for members (fail closed).
3. Remove the leading `# Title` (after any comment such as the translation marker).
4. CommonMark (core, GFM, heading permalinks, attributes); on `DocumentParsedEvent`, rewrite links (chapters → routes,
   admin chapters → plain text for members, other docs → `docs_url`) and images (→ the image route, intrinsic size,
   portrait class, lazy loading).
5. Symfony HtmlSanitizer (safe elements plus `kbd`; http(s) / mailto links; ids on headings and anchors).
6. DOM pass (`Dom\HTMLDocument`, only when the HTML has images or `<a id>`): stable anchors become heading ids, image-only
   paragraphs become figures or galleries.
7. Table of contents from `h2` – `h4`, with the `<!-- tags: … -->` under each heading.

The cache key holds the renderer version, slug, locale, translation state, audience, file mtime and size, manifest
mtime and the router base URL.

## Design rules

- Only listed chapters are served; no request path reaches the filesystem.
- Members never receive admin content: chapters 404, blocks removed before parsing, links unwrapped.
- Every rendered page goes through the sanitiser; the lightbox writes captions with `textContent`.
- Stateless services only (FrankenPHP worker mode); rendered chapters live in the cache pool, not in properties.
- The manual stays a valid GitHub manual: every construct the bundle reads (stable anchors, audience and tags comments,
  `../` links from translation folders) is plain Markdown / HTML that GitHub renders or hides.

## Public ports and extension points

| Port | Default implementation | Purpose |
| --- | --- | --- |
| `Catalog\ManualCatalogInterface` | `Catalog\ManualCatalog` | Chapters, navigation, locales and the files behind them (reads `manifest.yaml`). |
| `Render\ManualRendererInterface` | `Render\ManualRenderer` | Renders a chapter to sanitised HTML plus its table of contents. |
| `Security\ManualAccessInterface` | `Security\ManualAccess` | Who may read and who is admin. |
| `Security\ManualChapterAccessCheckerInterface` | `Security\AllowAllManualChapterAccessChecker` | Per-chapter rule of the host. |
| `Translation\MachineTranslatorInterface` | none (`machine_translation.provider`) | Machine translation provider. |

All ports take and return scalars and bundle value objects (`ManualChapter`, `RenderedManualChapter`); no Doctrine or
HTTP type appears in them. Each interface is aliased to its default service, and every bundle consumer type-hints the
interface, so aliasing it to your own service replaces the default everywhere:

```yaml
services:
  App\Manual\DatabaseManualCatalog: ~
  Nowo\ManualKitBundle\Catalog\ManualCatalogInterface:
    alias: App\Manual\DatabaseManualCatalog
```

`ManualCatalog::MANIFEST`, `::SLUG`, `::TRANSLATION_MARKER`, `::sourceHash()` and `ManualRenderer::audienceFiltered()`
stay on the default classes. Replacements must stay stateless (worker mode, kernel not reset).

## Standard Symfony and DDD hosts

- **Standard Symfony host:** set `nowo_manual_kit.directory`, import the routes and keep the default services.
- **DDD / hexagonal host:** the bundle has no entities, mapping or events; the manual is files. Plug your own access
  rules through `ManualAccessInterface` / `ManualChapterAccessCheckerInterface` (an adapter over your authorization
  port), and, if chapters live elsewhere, implement `ManualCatalogInterface` (and `ManualRendererInterface` when you
  render yourself) in your infrastructure layer and alias the ports.
