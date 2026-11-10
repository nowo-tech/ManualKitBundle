# Upgrading

This guide lists the changes integrators must apply when upgrading between versions.

## Table of contents

- [Unreleased](#unreleased)
- [To 1.9.0](#to-190)
- [To 1.8.0](#to-180)
- [To 1.7.0](#to-170)
- [To 1.6.0](#to-160)
- [To 1.5.1](#to-151)
- [To 1.5.0](#to-150)
- [To 1.4.0](#to-140)
- [1.3.0 (2026-10-23)](#130-2026-10-23)
- [1.2.0 (2026-10-23)](#120-2026-10-23)
- [1.1.0 (2026-10-23)](#110-2026-10-23)
- [1.0.1 (2026-10-23)](#101-2026-10-23)
- [1.0.0 (2026-10-23)](#100-2026-10-23)
  - [From an application-local manual](#from-an-application-local-manual)

## Unreleased

## To 1.9.0

From **1.8.0**:

```bash
composer update nowo-tech/manual-kit-bundle
```

No breaking change; with the default configuration the pages, navigation and search are the same.

- `ManualController` and `ManualSearchProvider` now take `ManualAccessInterface` instead of the `ManualAccess` class
  (`ManualAccess` implements it, so code that builds them by hand keeps working). `ManualAccess` takes an optional 4th
  argument, the `ManualChapterAccessCheckerInterface`.
- Optional, per-chapter rule:

  ```yaml
  services:
      Nowo\ManualKitBundle\Security\ManualChapterAccessCheckerInterface: '@App\Help\PlanChapterAccess'
  ```

  Links to a refused chapter inside another chapter's text stay links (the rendered chapters are cached per
  audience, not per user); they lead to a 404.
- Optional, own machine translator:

  ```yaml
  nowo_manual_kit:
      machine_translation:
          provider: service
          service: App\Infrastructure\Translation\GatewayMachineTranslator
  ```

## To 1.8.0

From **1.7.0**:

```bash
composer update nowo-tech/manual-kit-bundle
```

No action required: with `groups: []` (default) and no block filled, the chapter page renders byte for byte as in 1.7.

An application with its own chapter page (`nowo_manual_kit.template`) can now extend the bundle's instead of copying it:

1. Move the group order and labels to config:

   ```yaml
   nowo_manual_kit:
       groups:
           - { key: using, label: manual.group.using }
           - { key: project, label: manual.group.project }
   ```

2. Make the template extend `@NowoManualKitBundle/chapter.html.twig`, import `@NowoManualKitBundle/_macros.html.twig`
   when it needs the sub-page tree, and keep only the blocks it changes (`manual_nav`, `manual_nav_start`,
   `manual_body`, `manual_body_start`, `manual_aside`). See [TEMPLATES — Blocks](TEMPLATES.md#blocks).

## To 1.7.0

From **1.6.0**:

```bash
composer update nowo-tech/manual-kit-bundle
```

The close now waits for its fade: the `hidden` state, `dialog.close()` or removal comes up to 150 ms later than before, and a test should wait for the closed state or set the durations to `0ms`. With no transition (`0ms`, or a page without the bundle's CSS) it all happens at once, as before. Durations come from UiKit's `--nowo-ui-motion-*` tokens with fallbacks here, so no UiKit release is needed.

## To 1.6.0

From **1.5.1**:

```bash
composer update nowo-tech/manual-kit-bundle
```

No action needed: a manifest without `parent` renders byte for byte as in 1.5.1 (BC test), and every route name and URL
is unchanged. To use sub-pages (see [USAGE](USAGE.md#sub-pages)):

- Add `parent: <slug>` to the child rows of `manifest.yaml`; move their files to `docs/manual/<parent>/<slug>.md` if
  you like (translations to `docs/manual/<locale>/<parent>/<slug>.md`).
- **Own chapter template?** Link chapters with `manual_chapter_path(item)` instead of
  `path('nowo_manual_chapter', {chapter: item.slug})` — the old form still works for nested chapters through a 301 —
  and use the new `ancestors` / `children` variables for a breadcrumb and a sub-page list (the default template shows
  how; `nowo_manual_breadcrumbs_template()` gives UiKit's breadcrumb partial when installed). `chapters` now also
  holds nested chapters, each right after its parent; filter `c.ancestors is empty` for the top level.
- BreadcrumbKit trails keyed on `nowo_manual_chapter` do not cover `nowo_manual_child` / `nowo_manual_grandchild`.

## To 1.5.1

From **1.5.0**:

```bash
composer update nowo-tech/manual-kit-bundle
```

From **1.5.0**: no action needed if you import `@NowoManualKitBundle/Resources/config/routes.php` (see [INSTALLATION](INSTALLATION.md)). If the manual was reachable in your app only through the default `routing.controllers` import (Symfony >= 7.3), import `routes.php` explicitly with a prefix.

## To 1.5.0

From **1.4.0**:

```bash
composer update nowo-tech/manual-kit-bundle
```

```bash
composer update nowo-tech/manual-kit-bundle
php bin/console assets:install public   # new file: js/manual-lightbox.js
```

- **Published assets go through the `nowo_manual_kit` asset package.** The bundle now registers
  `framework.assets.packages.nowo_manual_kit` (`base_path: /bundles/nowomanualkit`). If an overridden template links
  `/bundles/nowomanualkit/css/manual.css` by hand, replace it with `{{ asset('css/manual.css', 'nowo_manual_kit') }}`
  (or keep `nowo_manual_asset('css/manual.css')`, which now resolves through the package). Remove any copy of the
  package from your `framework.assets.packages`. `symfony/asset` is now a dependency and `framework.assets` must stay
  enabled.
- **Stricter configuration.** Values that used to be accepted silently now fail the container compile with the path:
  empty `directory`, `template`, `layout` or `cache`; a `source_locale` / `locales` entry that is not a locale; a
  `docs_url` or `machine_translation.url` that is not an absolute `http(s)` URL; `provider: deepl` without `api_key`;
  `provider: libretranslate` without `url`. Fix the YAML the message points at.
- **Provider timeouts.** Machine-translation requests now stop after `machine_translation.timeout` (30 s idle) /
  `max_duration` (120 s total). Raise them for very large chapters on a slow provider.
- `ManualLinkExtension`'s last constructor argument (`string $assetsBase`) is replaced by `?Packages $packages` and
  `bool $standaloneLightbox`; only code that builds the extension by hand is affected.

## To 1.4.0

From **1.3.0**:

```bash
composer update nowo-tech/manual-kit-bundle
```

## 1.3.0 (2026-10-23)

- A manifest problem no longer throws a `RuntimeException`: the chapter is left out and `ManualCatalog::problems()` /
  `nowo:manual:check` report it. Code that caught the exception should read `problems()` instead.
- Add `php bin/console nowo:manual:check --sources` to your CI and to the container start, so a missing source shows
  up before your users miss a chapter.

## 1.2.0 (2026-10-23)

No change needed. To serve another Markdown file of your docs (e.g. the CHANGELOG), add a manifest row with `file`
(USAGE, *External chapters*).

## 1.1.0 (2026-10-23)

No change needed. To use `nowo:manual:translate`, install `symfony/http-client` and set
`nowo_manual_kit.machine_translation.provider` (see CONFIGURATION).

## 1.0.1 (2026-10-23)

No code change. If you import the routes with a prefix, add `trailing_slash_on_root: false` to the import so the
home is served at `/manual` rather than `/manual/` (see INSTALLATION).

## 1.0.0 (2026-10-23)

First release — nothing to upgrade. Install it with [INSTALLATION.md](INSTALLATION.md).

### From an application-local manual

Applications that served `docs/manual` with their own controller (the Symfony Beacon layout the bundle comes from) move
to the bundle like this:

1. Remove the local catalog, renderer, controller, Twig link extension and search provider; register the bundle and
   import its routes with the old prefix (`/manual`, or `/{_locale}/manual`).
2. Rename the routes everywhere (templates, `path()`, menus, SearchKit quick actions, tests):

   | Before (typical) | 1.0.0 |
   | --- | --- |
   | `manual_index` | `nowo_manual_index` |
   | `manual_chapter` | `nowo_manual_chapter` |
   | `manual_chapter_md` | `nowo_manual_chapter_md` |
   | `manual_image` | `nowo_manual_image` |

3. Keep your page: set `template` to your existing chapter template — it receives the same `rendered`, `chapters`,
   `previous`, `next` variables. Add the translation notice (`rendered.chapter.translation.value` is `missing` or
   `outdated`) if you translate the manual.
4. `manual_href()` / `manual_is_external()` keep their names; configure `docs_url` and `in_app` instead of the local
   parameters.
5. The lightbox: re-export the bundle's peer under the identifier your template uses (`export { default } from
   '…/manual-kit-bundle/src/Resources/assets/stimulus-peers/manual_lightbox_controller';`).
6. Before translating, add stable anchors (`<a id="…"></a>`) to the sections the application links to, so the links
   survive translated headings.
