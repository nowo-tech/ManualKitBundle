# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/manual-kit-bundle`  
**Last audited**: 2026-10-29

Every production unit under `src/` and the requirement that covers it. Vitest files (`*.test.ts`) are test code and
out of scope; the built `public/js/manual-lightbox.js` is listed as the output of its named sources.

## Bundle & DI

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `NowoManualKitBundle.php` | Bundle entry | FR-BUNDLE-001 |
| `DependencyInjection/Configuration.php` | Strict config tree | FR-CFG-001 |
| `DependencyInjection/NowoManualKitExtension.php` | DI extension, asset package prepend | FR-CFG-002, FR-CFG-003 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Template override path | FR-TWIG-002 |
| `Resources/config/services.php` | Service wiring | FR-CFG-002 |

## Catalog

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Catalog/ManualCatalog.php` | Manifest, locales, translation states | FR-CAT-001 |
| `Catalog/TranslationAudit.php` | Translation audit and stamping | FR-CAT-002 |

## CLI

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Command/CheckTranslationsCommand.php` | `nowo:manual:check` | FR-CMD-001 |
| `Command/StampTranslationCommand.php` | `nowo:manual:stamp` | FR-CMD-002 |
| `Command/TranslateManualCommand.php` | `nowo:manual:translate` | FR-CMD-003 |

## HTTP & security

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Controller/ManualController.php` | Index, chapter, `.md` redirect, images | FR-HTTP-001 |
| `Resources/config/routes.php` | Attribute route import | FR-HTTP-001 |
| `Security/ManualAccess.php` | Reader / admin roles | FR-SEC-001 |

## Rendering & model

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Render/ManualRenderer.php` | Markdown rendering, sanitising, cache | FR-RENDER-001 |
| `Model/ManualChapter.php` | Chapter value object | FR-MODEL-001 |
| `Model/RenderedManualChapter.php` | Rendered chapter value object | FR-MODEL-001 |
| `Model/TranslationState.php` | Translation state enum | FR-MODEL-001 |

## Search

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Search/ManualSearchProvider.php` | SearchKit provider | FR-SEARCH-001 |
| `Search/ManualSearchText.php` | Query matcher | FR-SEARCH-002 |

## Twig

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Twig/ManualLinkExtension.php` | Twig functions | FR-TWIG-001 |
| `Resources/views/chapter.html.twig` | Chapter page | FR-TWIG-003 |
| `Resources/views/layout.html.twig` | Bare layout | FR-TWIG-003 |

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
| `Resources/assets/src/register-lightbox.ts` | Standalone registration | FR-ASSET-003 |
| `Resources/assets/src/manual-lightbox.ts` | Standalone Vite entry | FR-ASSET-003 |

## Build outputs

| Output file | Built from | Requirement IDs |
| --- | --- | --- |
| `Resources/public/js/manual-lightbox.js` | `Resources/assets/src/manual-lightbox.ts` (`vite.config.ts`, `pnpm run build`) | FR-BUILD-001 |

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
| Bundle & DI | 5 |
| Catalog | 2 |
| CLI | 3 |
| HTTP & security | 3 |
| Rendering & model | 4 |
| Search | 2 |
| Twig | 3 |
| Machine translation | 5 |
| Frontend assets | 4 |
| Build outputs | 1 |
| Translations | 7 |
| **Total** | **39** |

Audit: `find src -type f -not -name '*.test.ts' | wc -l` → **39** (excluded: 2 `*.test.ts`).
