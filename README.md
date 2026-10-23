# Manual Kit Bundle

[![CI](https://github.com/nowo-tech/ManualKitBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/ManualKitBundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/manual-kit-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/manual-kit-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/manual-kit-bundle.svg)](https://packagist.org/packages/nowo-tech/manual-kit-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-7.4%20%7C%208.0%20%7C%208.1%2B-000000?logo=symfony)](https://symfony.com) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/ManualKitBundle.svg?style=social&label=Star)](https://github.com/nowo-tech/ManualKitBundle) [![Coverage](https://img.shields.io/badge/Coverage-100%25-brightgreen)](#tests-and-coverage)

> ⭐ **Found this useful?** [Install from Packagist](https://packagist.org/packages/nowo-tech/manual-kit-bundle) · Give it a **star** on [GitHub](https://github.com/nowo-tech/ManualKitBundle) so more developers can find it.

**Manual Kit Bundle** — an in-app product manual for Symfony built from your `docs/manual` Markdown: one source that
GitHub renders as it is and the application serves inside its own layout, translated per locale with stable anchors,
admin-only chapters and blocks that fail closed, figures with a lightbox, search tags and an optional
[SearchKit](https://github.com/nowo-tech/SearchKitBundle) provider.

> 📋 **Compatible with Symfony 7.4+ and 8.0–8.1+** — PHP 8.4+.

![FrankenPHP Friendly Worker Mode](docs/images/frankenphp-friendly.png)

This bundle is **FrankenPHP worker mode friendly**. See [docs/FRANKENPHP-WORKER-AUDIT.md](docs/FRANKENPHP-WORKER-AUDIT.md).

## Features

- **One source for GitHub and the app**: `docs/manual/*.md` in reading order from `manifest.yaml`; links between
  chapters (`02-dashboard.md#filters`) become in-app routes, links to other repository docs open the published copy
  (`docs_url`), GitHub-style `.md` URLs redirect to the chapter
- **Translations per locale**: `docs/manual/es/02-dashboard.md` next to the source; a chapter without a translation is
  served from the source with a *not translated yet* notice; `<!-- translation-of: <hash> -->` marks a translation
  *outdated* as soon as its source changes
- **Stable anchors**: `<a id="filters"></a>` gives a heading the same id in every language, so the application's links
  (`manual_href('manual/02-dashboard.md#filters')`) survive translated titles
- **Audiences that fail closed**: `audience: admin` chapters answer 404 to members (no existence oracle);
  `<!-- audience: admin -->` blocks are dropped for members, `audience: github` blocks never show in the app, and an
  unclosed or misspelt marker hides the rest of the chapter
- **Figures**: images become figures with their title as caption, intrinsic width / height, lazy loading, phone
  screenshots sized down, several images of one paragraph side by side, and an opt-in Stimulus lightbox
- **Search**: `tags` in the manifest and `<!-- tags: … -->` under a heading feed the SearchKit "Manual" group
  (chapters and sections the viewer may read, in their locale)
- **Translation checks for CI**: `nowo:manual:check --strict` fails on outdated translations and missing stable
  anchors; `nowo:manual:stamp es 02-dashboard` records that a translation matches its source again
- **Sanitised and cached**: CommonMark + GFM through the Symfony HTML sanitiser, cached per chapter, audience, locale
  and file version; images served with a sandboxing CSP
- **Overridable templates** and **CSS custom properties** (`--nowo-manual-*`); 7 locales (en, es, de, fr, it, nl, pt)

## Installation

```bash
composer require nowo-tech/manual-kit-bundle
```

```php
// config/bundles.php
return [
    Nowo\ManualKitBundle\NowoManualKitBundle::class => ['all' => true],
];
```

## Quick start

1. Import the routes and publish the stylesheet (`bin/console assets:install`):

```yaml
# config/routes/nowo_manual_kit.yaml
nowo_manual_kit:
    resource: '@NowoManualKitBundle/Resources/config/routes.php'
    prefix: /manual
    trailing_slash_on_root: false   # the home is /manual, not /manual/
```

2. Write the manual (the defaults read `docs/manual`):

```yaml
# docs/manual/manifest.yaml
chapters:
  - { slug: README }
  - { slug: 01-getting-started, group: using, tags: [start, onboarding] }
  - { slug: 02-administration, group: administration, audience: admin }
```

```markdown
<!-- docs/manual/01-getting-started.md -->
# Getting started

<a id="first-steps"></a>

## First steps
<!-- tags: sign in, login -->

![The dashboard](images/dashboard.webp "Your projects at a glance")
```

3. Frame it in your layout and link to it:

```yaml
# config/packages/nowo_manual_kit.yaml
nowo_manual_kit:
    layout: 'base.html.twig'   # blocks: title, stylesheets, body
    docs_url: 'https://github.com/acme/app/blob/main/docs/'
```

```twig
<a href="{{ manual_href('manual/01-getting-started.md#first-steps') }}">Help</a>
```

Full guide: [docs/INSTALLATION.md](docs/INSTALLATION.md) · options: [docs/CONFIGURATION.md](docs/CONFIGURATION.md) ·
writing and translating the manual, templates, lightbox, search, commands: [docs/USAGE.md](docs/USAGE.md).

## Requirements

- PHP `>=8.4 <8.6`
- Symfony 7.4, 8.0, and 8.1+
- FrameworkBundle, TwigBundle, SecurityBundle (security-core), HtmlSanitizer, Yaml, `league/commonmark` ^2.6
- Optional: `nowo-tech/search-kit-bundle` ^1.1 (Manual group in the header search), `@hotwired/stimulus` in the
  application (figure lightbox)

### Version information

| Version | PHP | Symfony | Status |
| --- | --- | --- | --- |
| 1.x | 8.4 – 8.5 | 7.4, 8.0, 8.1+ | Supported (bug and security fixes) |

## Demo

A Symfony 8.1 demo runs on FrankenPHP (worker mode by default); it needs no database:

```bash
make -C demo up
# Demo started at: http://localhost:8177
```

Sign in as **demo** / `demo` (member) or **admin** / `admin`, open **Manual**, zoom the figure and switch to Español.
See [demo/README.md](demo/README.md) and [docs/DEMO-FRANKENPHP.md](docs/DEMO-FRANKENPHP.md).

Playwright end-to-end tests: `make -C demo/symfony8 test-e2e` (see [docs/CONTRIBUTING.md](docs/CONTRIBUTING.md)).

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)
- [GitHub Spec Kit](docs/SPEC-KIT.md)

### Additional documentation

- [Templates](docs/TEMPLATES.md)
- [Architecture](docs/ARCHITECTURE.md)
- [PSR evaluation](docs/PSR.md)
- [Coverage policy](docs/COVERAGE.md)
- [Demo FrankenPHP](docs/DEMO-FRANKENPHP.md)
- [FrankenPHP worker audit](docs/FRANKENPHP-WORKER-AUDIT.md)
- [GitHub CI](docs/GITHUB_CI.md)
- [GitHub About](docs/GITHUB.md)

## Tests and coverage

- Tests: PHPUnit (unit, integration, functional), Vitest (the Stimulus peer), Playwright (demo e2e)
- PHP: 100%
- TS/JS: 100%
- Python: N/A

PHP line coverage: **100%** of includable `src/` (gate **≥ 99%**). TypeScript: Vitest thresholds 100% statements /
branches / functions / lines on `src/Resources/assets/stimulus-peers` (make gate **≥ 90%**).

```bash
make test
make test-coverage
make test-ts
make qa
```

## License

MIT © 2026 Héctor Franco Aceituno / Nowo.tech
