# Demo applications with FrankenPHP (development and production)

This document describes how the **Manual Kit Bundle** demo runs under **FrankenPHP** in Docker.

## Table of contents

- [Overview](#overview)
- [What the demo includes](#what-the-demo-includes)
- [Development configuration](#development-configuration)
  - [Caddyfile (development)](#caddyfile-development)
  - [PHP and Twig (development)](#php-and-twig-development)
  - [Start (development)](#start-development)
- [Production configuration](#production-configuration)
- [Switching classic vs worker (`FRANKENPHP_MODE`)](#switching-classic-vs-worker-frankenphp_mode)
- [Timeouts](#timeouts)
- [Troubleshooting](#troubleshooting)
  - [Twig or PHP changes do not appear](#twig-or-php-changes-do-not-appear)
  - [Demo does not start](#demo-does-not-start)
  - [Browser console: 404 on `/build/…` or missing Vite entry](#browser-console-404-on-build-or-missing-vite-entry)
  - [Unstyled manual page](#unstyled-manual-page)
  - [Figures do not zoom](#figures-do-not-zoom)

## Overview

The `demo/` folder is **not shipped** in the Composer package (`archive.exclude` includes `/demo`). The demo exists only in the source repository.

The stack uses:

- **FrankenPHP** (Caddy + PHP) in a single `php` container
- **No database container** (in-memory users, the manual is Markdown under `docs/manual`; REQ-DEMO-006 / REQ-DEMO-011 not applicable)
- Bundle mount `../..` → `/var/manual-kit-bundle`
- Two Caddyfiles: `Caddyfile` (worker) and `Caddyfile.dev` (classic)
- `FRANKENPHP_MODE` (`classic` \| `worker`, default **`worker`**)

| Demo | UI | Default port |
| --- | --- | --- |
| `demo/symfony8/` | Bootstrap 5 | **8177** |

```bash
make -C demo up-symfony8
# Demo started at: http://localhost:8177
```

| Aspect | Development (default) | Production |
| --- | --- | --- |
| FrankenPHP worker | Off when `FRANKENPHP_MODE=classic` | On (`worker`) |
| Twig cache | Off (`config/packages/dev/twig.yaml`) | On |
| OPcache revalidation | Every request (`docker/php-dev.ini`) | Default |
| HTTP cache headers | `no-store` in `Caddyfile.dev` | Omitted |
| `APP_ENV` / `APP_DEBUG` | `dev` / `1` | `prod` / `0` |
| Database | None (in-memory users, Markdown manual) | — |

## What the demo includes

- **Symfony Web Profiler** — `dev` and `test`
- **Nowo Twig Inspector** and **Nowo Hot Reload** — `dev`/`test` only (Caddyfile Mercure hub + `hot_reload`; do not enable Hot Reload in production)
- **Twig Extra Bundle** (`twig/extra-bundle` + `twig/string-extra`)
- **Manual Kit Bundle** — Symfony 8.1 (PHP 8.5) app with the manual in the navbar: in-memory users `demo` / `demo`
  (member) and `admin` / `admin`, a sample manual in `docs/manual` (English) and `docs/manual/es` (Spanish, one chapter
  left untranslated), routes under `/{_locale}/manual`, and the chapter page framed in `templates/base.html.twig`
  (`nowo_manual_kit.layout`). See [`demo/README.md`](../demo/README.md).
- **Tests**: `make -C demo/symfony8 test` (PHPUnit), `make -C demo/symfony8 test-e2e` (Playwright: sign-in, chapters,
  lightbox, Spanish notices), `make -C demo/symfony8 demo-screenshots` (README image), `make demo-smoke` from the bundle
  root (boot + HTTP 200, REQ-TEST-011).
- **PHP / Symfony policy**: the demo tracks the latest PHP (8.5) and Symfony 8.1; the bundle itself supports PHP 8.4+ and
  Symfony 7.4 / 8.x (see the README requirements).
- **The manual as an integrator ships it** — the bundle's `chapter.html.twig` links `css/manual.css` from
  `/bundles/nowomanualkit/` (`assets:install`)
- **Pentatrion Vite + pnpm** — the demo's entry `assets/app.ts` starts Stimulus and registers the bundle's lightbox peer
  (`@bundle/stimulus-peers/manual_lightbox_controller`) as `nowo-manual-lightbox`
- **No database** — REQ-DEMO-011 does not apply (no `*.db` files either)

Example `config/bundles.php` (abridged: the demo also registers Pentatrion Vite, WebProfiler and Debug):

```php
return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Twig\Extra\TwigExtraBundle\TwigExtraBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Nowo\ManualKitBundle\NowoManualKitBundle::class => ['all' => true],
    Nowo\HotReloadBundle\NowoHotReloadBundle::class => ['dev' => true, 'test' => true],
    Nowo\TwigInspectorBundle\NowoTwigInspectorBundle::class => ['dev' => true, 'test' => true],
];
```

## Development configuration

### Caddyfile (development)

`demo/symfony8/docker/frankenphp/Caddyfile.dev` — no worker, `no-store` cache headers, the Mercure hub used by Hot Reload.

### PHP and Twig (development)

`docker/php-dev.ini` revalidates OPcache. `config/packages/dev/twig.yaml` sets `twig.cache: false`.

### Start (development)

From the bundle root:

```bash
make -C demo up
```

`make up` in `demo/symfony8` prints `Waiting for container to be ready...`, `Installing dependencies...`, then `Demo started at: http://localhost:<PORT>` from `PORT` in `.env` / `.env.example` (REQ-DEMO-005).

## Production configuration

Use `FRANKENPHP_MODE=worker` (default) and `APP_ENV=prod`. Do not enable Hot Reload.

## Switching classic vs worker (`FRANKENPHP_MODE`)

Set `FRANKENPHP_MODE=classic` or `worker` in `.env`, then recreate:

```bash
docker compose up -d --force-recreate
```

The entrypoint copies `Caddyfile.dev` when mode is `classic`.

## Timeouts

The bundle opens no outbound connection; a chapter costs one file read and, on a cache miss, one Markdown render. The demo layers its deadlines innermost first (REQ-RUNTIME-001):

| Layer | Where in the demo | Value |
| --- | --- | --- |
| Outbound HTTP (none in the bundle) | `framework.http_client.default_options.timeout` (`config/packages/framework.yaml`) | 5 s |
| PHP `max_execution_time` (and `max_input_time`) | `docker/php-dev.ini` | 30 s |
| Caddy / FrankenPHP write timeout | `servers { timeouts { write 60s } }` in `docker/frankenphp/Caddyfile` and `Caddyfile.dev` | 60 s |
| FrankenPHP `max_wait_time` | `frankenphp { max_wait_time 30s }` in both Caddyfiles | 30 s (wait for a free PHP thread, then 504) |

**HTTP client 5 s < PHP 30 s < Caddy write 60 s.** Raise them together if you change one.

## Troubleshooting

### Twig or PHP changes do not appear

Use `FRANKENPHP_MODE=classic`, confirm `twig.cache: false` in `dev`, and hard-refresh the browser.

### Demo does not start

Ensure Docker is running, port **8177** is free, and `make -C demo/symfony8 logs` shows FrankenPHP listening. Composer DNS: Compose sets `8.8.8.8` (REQ-DEMO-009).

### Browser console: 404 on `/build/…` or missing Vite entry

Pentatrion needs `public/build/entrypoints.json`. Run `make -C demo/symfony8 assets` (pnpm + `vite build`; also part of `make up` and `update-bundle`). Hard-refresh the page. Caddy serves `/build/*` as static files (no Vite dev-server proxy).

### Unstyled manual page

The chapter template links `/bundles/nowomanualkit/css/manual.css`. A 404 means the public assets are not installed: run
`make -C demo/symfony8 assets-install` (`assets:install public --symlink --relative`, also part of `make install`).

### Figures do not zoom

The lightbox is the Stimulus peer compiled into the demo's Vite entry. Rebuild it with `make -C demo/symfony8 assets` and
check the browser console; without it a click on a figure simply opens the image.
