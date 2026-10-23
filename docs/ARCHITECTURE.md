# Architecture

## Table of contents

- [Layers](#layers)
- [Request flow](#request-flow)
- [Rendering pipeline](#rendering-pipeline)
- [Design rules](#design-rules)

## Layers

| Layer | Namespace / path | Role |
| --- | --- | --- |
| Model | `Model` | `ManualChapter` (slug, group, audience, icon, title, path, tags, locale, translation state), `RenderedManualChapter` (html, toc), `TranslationState` |
| Catalog | `Catalog` | `ManualCatalog`: reads `manifest.yaml`, resolves a chapter for a locale (source, translated, outdated, missing), titles, neighbours, `sourceHash()`. `TranslationAudit`: states and missing stable anchors per locale, `stamp()` |
| Rendering | `Render` | `ManualRenderer`: audience filter, CommonMark + GFM, link and image rewriting, sanitiser, DOM pass (stable anchors, figures), toc with section tags, cache |
| Access | `Security` | `ManualAccess`: `role` (readers) and `admin_role` through the authorisation checker |
| HTTP | `Controller` | `ManualController`: index, chapter, `.md` redirect, sandboxed images |
| Twig | `Twig`, `Resources/views` | `ManualLinkExtension` (`manual_href`, `manual_is_external`, `nowo_manual_layout`, `nowo_manual_asset`); `chapter.html.twig`, `layout.html.twig` |
| Console | `Command` | `nowo:manual:check [--strict]`, `nowo:manual:stamp <locale> <chapter>` |
| Search | `Search` | `ManualSearchText` (folding, every word matches title or tags); `ManualSearchProvider` (SearchKit SPI, registered only when SearchKit is installed) |
| DI | `DependencyInjection` | Flat configuration tree → `nowo_manual_kit.*` parameters, Twig paths pass (`@NowoManualKitBundle`, app overrides first) |
| Browser | `Resources/assets/stimulus-peers`, `Resources/public/css` | `manual_lightbox_controller.ts` (opt-in Stimulus peer), `manual.css` |

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
