# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/manual-kit-bundle`  
**Last audited**: 2026-11-26 (1.15.0, unreleased: manual search synonyms; 1.13.0: 1.11 chapter redirects, 1.13 audiences and roots; 1.6 sub-pages: [`../002-sub-pages/spec.md`](../002-sub-pages/spec.md))

Every production unit under `src/` and the requirement that covers it. Vitest files (`*.test.ts`) are test code and
out of scope; the built `public/js/manual-lightbox.js` is listed as the output of its named sources.

## Bundle & DI

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `NowoManualKitBundle.php` | Bundle entry | FR-BUNDLE-001 |
| `DependencyInjection/Configuration.php` | Strict config tree | FR-CFG-001 |
| `DependencyInjection/NowoManualKitExtension.php` | DI extension, asset package prepend | FR-CFG-002, FR-CFG-003 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Template override path | FR-TWIG-002 |
| `DependencyInjection/Compiler/MachineTranslatorPass.php` | Provider service type check at compile | FR-MT-005 |
| `DependencyInjection/Compiler/ManualSynonymsPass.php` | 1.15: SearchKit synonyms for the manual search when SearchKit's synonyms are on and the host has no port alias | FR-SEARCH-004 |
| `DependencyInjection/Compiler/ReaderAwareRendererPass.php` | 1.13: with `roots` configured the aliased renderer must implement `ReaderAwareManualRendererInterface`, else the container build fails | FR-AUD-004 |
| `Resources/config/services.php` | Service wiring | FR-CFG-002 |

## Catalog

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Catalog/ManualCatalog.php` | Manifest, locales, translation states, chapter tree | FR-CAT-001, FR-TREE-001 |
| `Catalog/ManualCatalogInterface.php` | Catalog port (1.10) | FR-PORT-001 |
| `Catalog/TranslationAudit.php` | Translation audit and stamping | FR-CAT-002, FR-TREE-006 |

## CLI

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Command/CheckTranslationsCommand.php` | `nowo:manual:check` | FR-CMD-001 |
| `Command/StampTranslationCommand.php` | `nowo:manual:stamp` | FR-CMD-002 |
| `Command/TranslateManualCommand.php` | `nowo:manual:translate` | FR-CMD-003, FR-TREE-006 |
| `Command/RedirectsCommand.php` | `nowo:manual:redirects`: lints the redirect map (missing targets, cycles, chains, dead rules, old slugs still in `route_links`) (1.11) | FR-REDIR-004 |

## HTTP & security

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Controller/ManualController.php` | Index, chapter, `.md` redirect, images, nested routes | FR-HTTP-001, FR-TREE-002, FR-TREE-003 |
| `Resources/config/routes.php` | Attribute route import | FR-HTTP-001 |
| `Routing/ManualRoutes.php` | Route of a chapter by its place in the tree | FR-TREE-002 |
| `Routing/ManualRouteLoader.php` | 1.13: routes of one manual root (`type: nowo_manual`, resource = root name), named `nowo_manual.<root>.<route>`, carrying `_nowo_manual_root` | FR-AUD-003 |
| `Security/ManualAccess.php` | Reader / admin roles | FR-SEC-001 |
| `Security/ManualAccessInterface.php` | Access policy port (1.9) | FR-SEC-002 |
| `Security/ManualChapterAccessCheckerInterface.php` | Host per-chapter rule (1.9) | FR-SEC-002 |
| `Security/AllowAllManualChapterAccessChecker.php` | Default per-chapter rule (1.9) | FR-SEC-002 |
| `Security/ReadableChapters.php` | Chapter list filter, ancestors refused (internal) | FR-SEC-002 |

## Rendering & model

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Render/ManualRenderer.php` | Markdown rendering, sanitising, cache, tree links | FR-RENDER-001, FR-TREE-005 |
| `Render/ManualRendererInterface.php` | Renderer port (1.10) | FR-PORT-001 |
| `Render/ReaderAwareManualRendererInterface.php` | Renderer port for one reader: root and held audiences (1.13) | FR-AUD-004 |
| `Render/ReaderRendering.php` | Renders for a reader with whatever renderer is aliased; a 1.12 renderer is never used for another root (internal, 1.13) | FR-AUD-004 |
| `Render/LinkContext.php` | Link rewriting context (internal) | FR-TREE-005 |
| `Render/CommentBlocks.php` | `<!-- name: value -->` block parser shared by audience and device blocks (1.12) | FR-RENDER-001, FR-DEVICE-001 |
| `Render/DeviceBlocks.php` | Device block markers and wrappers (1.12) | FR-DEVICE-001 |
| `Render/DeviceViews.php` | `device_views` configuration, device allow-list, companion names (1.12) | FR-DEVICE-001, FR-DEVICE-003 |
| `Model/ManualChapter.php` | Chapter value object, place in the tree | FR-MODEL-001, FR-TREE-001 |
| `Model/RenderedManualChapter.php` | Rendered chapter value object | FR-MODEL-001 |
| `Model/TranslationState.php` | Translation state enum | FR-MODEL-001 |

## Audiences and roots (1.13)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Audience/ManualAudiences.php` | Named audiences, default audience and manual roots from configuration (immutable) | FR-AUD-001 |
| `Audience/ManualRoot.php` | One manual root: chapters its audiences may read, own route import, optional firewalls | FR-AUD-003 |
| `Audience/ManualRootResolver.php` | Which root a request is for (route default or firewall); stateless | FR-AUD-003 |
| `Audience/ManualReader.php` | Per-request reader: root plus held audiences; rendering cache key | FR-AUD-002 |
| `Audience/ManualReaderResolverInterface.php` | Reader port (null when the root is closed to the user) | FR-AUD-002 |
| `Audience/ManualReaderResolver.php` | Default reader resolver: root role, `member`, `admin`, one `isGranted()` per audience | FR-AUD-002 |
| `Audience/AudienceManifestValidator.php` | Unknown audience in the manifest or a chapter block fails the container build (internal) | FR-AUD-001 |
| `CacheWarmer/AudienceCacheWarmer.php` | `cache:warmup` repeats the audience check with the runtime `directory` | FR-AUD-001 |

## Chapter redirects (1.11)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Redirect/RedirectTarget.php` | One end of a redirect (`slug` or `slug#anchor`), internal only | FR-REDIR-001 |
| `Redirect/RedirectMapCompiler.php` | Validates the map (no URL, no cycle), collapses chains, precomputes anchor lookups | FR-REDIR-001 |
| `Redirect/CompiledRedirectMap.php` | Result of the compiler: rules, lookups, problems | FR-REDIR-001 |
| `Redirect/RedirectMapLoader.php` | Gathers the map from `redirects` config and the manifest's `redirects:` entry, at container build | FR-REDIR-002 |
| `Redirect/ChapterRedirectsInterface.php` | Redirect map port (immutable per process) | FR-REDIR-002 |
| `Redirect/ChapterRedirectMap.php` | Default immutable map compiled once | FR-REDIR-002 |

## Search

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Search/ManualSearch.php` | 1.15: the manual search, with or without SearchKit | FR-SEARCH-001, FR-SEARCH-003, FR-TREE-004 |
| `Search/ManualSearchHit.php` | 1.15: one hit | FR-SEARCH-003 |
| `Search/ManualSearchProvider.php` | SearchKit provider (1.15: SearchKit term alternatives, delegates to `ManualSearch`) | FR-SEARCH-001, FR-SEARCH-003, FR-TREE-004 |
| `Search/ManualSearchResult.php` | 1.15: hits and total | FR-SEARCH-003 |
| `Search/ManualSearchText.php` | Query matcher (1.15: alternatives) | FR-SEARCH-002 |
| `Search/ManualSynonymsInterface.php` | 1.15: synonym port of the manual search | FR-SEARCH-004 |
| `Search/SearchKitManualSynonyms.php` | 1.15: the port over SearchKit's `SynonymExpander` | FR-SEARCH-004 |

## Twig

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Twig/ManualLinkExtension.php` | Twig functions | FR-TWIG-001, FR-TREE-003, FR-TREE-005 |
| `Resources/views/chapter.html.twig` | Chapter page, tree navigation | FR-TWIG-003, FR-TREE-003 |
| `Resources/views/_breadcrumbs.html.twig` | Breadcrumb without UiKit | FR-TREE-003 |
| `Resources/views/layout.html.twig` | Bare layout | FR-TWIG-003 |
| `Resources/views/_macros.html.twig` | Chapter page macros (importable) | FR-TWIG-004 |

## Machine translation

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Translation/MarkdownTranslator.php` | Markdown segmentation and marker | FR-MT-001 |
| `Translation/MachineTranslatorInterface.php` | Provider contract | FR-MT-002 |
| `Translation/DeepLTranslator.php` | DeepL provider (timeouts, logging) | FR-MT-003 |
| `Translation/LibreTranslateTranslator.php` | LibreTranslate provider (timeouts, logging) | FR-MT-003 |
| `Translation/UiGlossary.php` | UI label glossary | FR-MT-004 |

## Frontend assets

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/public/css/manual.css` | Stylesheet | FR-ASSET-001 |
| `Resources/assets/stimulus-peers/manual_lightbox_controller.ts` | Stimulus lightbox peer | FR-ASSET-002 |
| `Resources/assets/stimulus-peers/motion.ts` | Closing animation helper | FR-ASSET-004 |
| `Resources/assets/src/register-lightbox.ts` | Standalone registration | FR-ASSET-003 |
| `Resources/assets/src/logger.ts` | Bundle logger: `scriptLoaded()` banner with build label, quiet switch (`data-nowo-console`, `NOWO_CONSOLE`), debug-gated levels (REQ-ASSETS-005, 1.14) | FR-ASSET-005 |
| `Resources/assets/src/manual-lightbox.ts` | Standalone Vite entry (logs `manual-kit/lightbox` banner) | FR-ASSET-003 |
| `Resources/assets/src/device-views.ts` | Desktop / Phone switch (1.12) | FR-DEVICE-002 |
| `Resources/assets/src/manual-devices.ts` | Standalone Vite entry of the switch (1.12; logs `manual-kit/devices` banner) | FR-DEVICE-002, FR-ASSET-005 |
| `Resources/assets/src/anchor-redirects.ts` | Client half of the redirect map: moves the reader to the new anchor (`data-nowo-manual-anchors`, `?moved_from`) (1.11) | FR-REDIR-003 |
| `Resources/assets/src/manual-anchors.ts` | Standalone Vite entry of the anchor redirects (1.11; logs `manual-kit/anchors` banner) | FR-REDIR-003, FR-ASSET-005 |

## Build outputs

| Output file | Built from | Requirement IDs |
| --- | --- | --- |
| `Resources/public/js/manual-lightbox.js` | `Resources/assets/src/manual-lightbox.ts` (`vite.config.ts`, `pnpm run build`) | FR-BUILD-001 |
| `Resources/public/js/manual-devices.js` | `Resources/assets/src/manual-devices.ts` (`vite.devices.config.ts`, `pnpm run build`) | FR-BUILD-001, FR-DEVICE-002 |
| `Resources/public/js/manual-anchors.js` | `Resources/assets/src/manual-anchors.ts` (`vite.anchors.config.ts`, `pnpm run build`) | FR-BUILD-001, FR-REDIR-003 |

## Translations

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/translations/NowoManualKitBundle.de.yaml` | UI strings (de) | FR-I18N-001 |
| `Resources/translations/NowoManualKitBundle.en.yaml` | UI strings (en) | FR-I18N-001 |
| `Resources/translations/NowoManualKitBundle.es.yaml` | UI strings (es) | FR-I18N-001 |
| `Resources/translations/NowoManualKitBundle.fr.yaml` | UI strings (fr) | FR-I18N-001 |
| `Resources/translations/NowoManualKitBundle.it.yaml` | UI strings (it) | FR-I18N-001 |
| `Resources/translations/NowoManualKitBundle.nl.yaml` | UI strings (nl) | FR-I18N-001 |
| `Resources/translations/NowoManualKitBundle.pt.yaml` | UI strings (pt) | FR-I18N-001 |

## Coverage summary

| Category | Units |
| --- | --- |
| Bundle & DI | 8 |
| Catalog | 3 |
| CLI | 4 |
| HTTP & security | 9 |
| Rendering & model | 11 |
| Audiences and roots (1.13) | 8 |
| Chapter redirects (1.11) | 6 |
| Search | 7 |
| Twig | 5 |
| Machine translation | 5 |
| Frontend assets | 10 |
| Build outputs | 3 |
| Translations | 7 |
| **Total** | **86** |

Audit: `find src -type f -not -name '*.test.ts' | wc -l` → **86** (excluded: 7 `*.test.ts`: `anchor-redirects`, `device-views`, `register-lightbox`, `manual_lightbox_controller`, `motion`, `logger`, `script-loaded`).
