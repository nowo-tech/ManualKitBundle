# Installation

## Table of contents

- [Requirements](#requirements)
- [Install with Composer](#install-with-composer)
- [Register the bundle](#register-the-bundle)
- [Routes](#routes)
- [Configuration file](#configuration-file)
- [Publish assets](#publish-assets)
- [Write the first chapter](#write-the-first-chapter)
- [Lightbox (optional)](#lightbox-optional)
- [Search (optional)](#search-optional)
- [FrankenPHP](#frankenphp)

## Requirements

- PHP `>=8.4 <8.6`. The floor is **8.4**, above the Nowo minimum of 8.2 (REQ-SF-001): the bundle uses `new Foo()->bar()`
  without parentheses, typed class constants and the `Dom\HTMLDocument` HTML5 parser, which are PHP 8.4 features.
  Rector (`withPhpVersion(PHP_84)`) targets the same floor.
- Symfony 7.4, 8.0 or 8.1+: FrameworkBundle, TwigBundle, security-core (an authorisation checker: SecurityBundle in
  practice), HtmlSanitizer, Yaml, Routing.
- `league/commonmark` ^2.6 (installed as a dependency).
- Optional: `nowo-tech/search-kit-bundle` ^1.1 (a "Manual" group in the header search), `@hotwired/stimulus` in the
  application's JavaScript (the figure lightbox).

## Install with Composer

```bash
composer require nowo-tech/manual-kit-bundle
```

## Register the bundle

Flex registers it; otherwise:

```php
// config/bundles.php
return [
    // ...
    Nowo\ManualKitBundle\NowoManualKitBundle::class => ['all' => true],
];
```

## Routes

The bundle defines four routes; import them under the prefix you want:

```yaml
# config/routes/nowo_manual_kit.yaml
nowo_manual_kit:
    resource: '@NowoManualKitBundle/Resources/config/routes.php'
    prefix: /manual
    trailing_slash_on_root: false   # the home is /manual, not /manual/
```

For a localised application, put the locale in the prefix — the request locale picks the translation:

```yaml
nowo_manual_kit:
    resource: '@NowoManualKitBundle/Resources/config/routes.php'
    prefix: '/{_locale}/manual'
    requirements:
        _locale: 'en|es|de'
    trailing_slash_on_root: false
```

The home route's path is empty, so without `trailing_slash_on_root: false` Symfony serves it at `/manual/` (with a
trailing slash) instead of `/manual`.

| Route | Path (after the prefix) | What |
| --- | --- | --- |
| `nowo_manual_index` | `` | The home chapter (`README.md`) |
| `nowo_manual_chapter` | `/{chapter}` | A chapter (`02-dashboard`) |
| `nowo_manual_chapter_md` | `/{chapter}.md` | 301 to the chapter (links written for GitHub keep working) |
| `nowo_manual_image` | `/images/{file}` | An image from `{directory}/images` |

## Configuration file

Every key is optional; the defaults read `%kernel.project_dir%/docs/manual` and require `ROLE_USER`:

```yaml
# config/packages/nowo_manual_kit.yaml
nowo_manual_kit:
    layout: 'base.html.twig'
    docs_url: 'https://github.com/acme/app/blob/main/docs/'
```

All keys: [CONFIGURATION.md](CONFIGURATION.md).

## Publish assets

Run once after every install or upgrade, so the files exist under `public/bundles/nowomanualkit/`:

```bash
php bin/console assets:install public
```

The bundle registers the asset package `nowo_manual_kit` (base path `/bundles/nowomanualkit`) itself; do not add it to
`framework.assets.packages`. Link the published files through it in your own templates:

```twig
<link rel="stylesheet" href="{{ asset('css/manual.css', 'nowo_manual_kit') }}">
{# only for the self-contained lightbox (standalone_lightbox: true does this for the default template) #}
<script src="{{ asset('js/manual-lightbox.js', 'nowo_manual_kit') }}" defer></script>
```

`framework.assets` must stay enabled (it is by default with `symfony/asset`, a dependency of the bundle).

## Write the first chapter

```text
docs/manual/
├── manifest.yaml
├── README.md               # the home page (slug README)
├── 01-getting-started.md
├── images/
│   └── dashboard.webp
└── es/                     # optional: one folder per translation locale
    └── 01-getting-started.md
```

```yaml
# docs/manual/manifest.yaml
chapters:
  - { slug: README }
  - { slug: 01-getting-started, group: using }
```

Open `/manual`. The format of the chapters, translations, audiences and figures: [USAGE.md](USAGE.md).

## Lightbox (optional)

The default template wires figures to a Stimulus controller named `nowo-manual-lightbox`. Register the bundle's peer in
your Stimulus application (it is TypeScript your bundler compiles; no npm package):

```ts
// assets/controllers/nowo_manual_lightbox_controller.ts
export { default } from '../../vendor/nowo-tech/manual-kit-bundle/src/Resources/assets/stimulus-peers/manual_lightbox_controller';
```

Applications without a Stimulus build can load the self-contained script instead (Stimulus plus the lightbox, built
by Vite into `src/Resources/public/js/manual-lightbox.js`):

```yaml
nowo_manual_kit:
    standalone_lightbox: true   # the default chapter template adds asset('js/manual-lightbox.js', 'nowo_manual_kit')
```

Use one or the other, not both. Without either, a click on a figure simply opens the image.

## Search (optional)

With `nowo-tech/search-kit-bundle` installed, `ManualSearchProvider` is registered and autoconfigured: the header
search gets a "Manual" group. Nothing to configure — see [USAGE — Search](USAGE.md#search).

## FrankenPHP

The services hold no per-request state; rendered chapters live in the configured cache pool. See
[FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).
