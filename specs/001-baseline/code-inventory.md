# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/manual-kit-bundle`  
**Last audited**: 2026-10-23

Every production source artifact under `src/` and the requirement that covers it.

| Source file | Requirement IDs |
| --- | --- |
| `Catalog/ManualCatalog.php` | FR-CAT-001 |
| `Catalog/TranslationAudit.php` | FR-CAT-002 |
| `Command/CheckTranslationsCommand.php` | FR-CMD-001 |
| `Command/StampTranslationCommand.php` | FR-CMD-001 |
| `Command/TranslateManualCommand.php` | FR-CMD-001 |
| `Controller/ManualController.php` | FR-HTTP-001 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | FR-TWIG-001 |
| `DependencyInjection/Configuration.php` | FR-CFG-001 |
| `DependencyInjection/NowoManualKitExtension.php` | FR-CFG-002 |
| `Model/ManualChapter.php` | FR-MODEL-001 |
| `Model/RenderedManualChapter.php` | FR-MODEL-001 |
| `Model/TranslationState.php` | FR-MODEL-001 |
| `NowoManualKitBundle.php` | FR-BUNDLE-001 |
| `Render/ManualRenderer.php` | FR-RENDER-001 |
| `Resources/assets/stimulus-peers/manual_lightbox_controller.test.ts` | FR-ASSET-001 |
| `Resources/assets/stimulus-peers/manual_lightbox_controller.ts` | FR-ASSET-001 |
| `Resources/config/routes.php` | FR-HTTP-001 |
| `Resources/config/services.php` | FR-CFG-002 |
| `Resources/public/css/manual.css` | FR-ASSET-001 |
| `Resources/translations/NowoManualKitBundle.de.yaml` | FR-I18N-001 |
| `Resources/translations/NowoManualKitBundle.en.yaml` | FR-I18N-001 |
| `Resources/translations/NowoManualKitBundle.es.yaml` | FR-I18N-001 |
| `Resources/translations/NowoManualKitBundle.fr.yaml` | FR-I18N-001 |
| `Resources/translations/NowoManualKitBundle.it.yaml` | FR-I18N-001 |
| `Resources/translations/NowoManualKitBundle.nl.yaml` | FR-I18N-001 |
| `Resources/translations/NowoManualKitBundle.pt.yaml` | FR-I18N-001 |
| `Resources/views/chapter.html.twig` | FR-TWIG-001 |
| `Resources/views/layout.html.twig` | FR-TWIG-001 |
| `Search/ManualSearchProvider.php` | FR-SEARCH-001 |
| `Search/ManualSearchText.php` | FR-SEARCH-001 |
| `Security/ManualAccess.php` | FR-SEC-001 |
| `Translation/DeepLTranslator.php` | FR-MT-001 |
| `Translation/LibreTranslateTranslator.php` | FR-MT-001 |
| `Translation/MachineTranslatorInterface.php` | FR-MT-001 |
| `Translation/MarkdownTranslator.php` | FR-MT-001 |
| `Translation/UiGlossary.php` | FR-MT-001 |
| `Twig/ManualLinkExtension.php` | FR-TWIG-001 |
