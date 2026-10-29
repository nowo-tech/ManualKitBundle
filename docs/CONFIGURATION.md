# Configuration

Every key of `nowo_manual_kit` is optional. Reference (defaults shown):

```yaml
# config/packages/nowo_manual_kit.yaml
nowo_manual_kit:
    directory: '%kernel.project_dir%/docs/manual'
    source_locale: en
    locales: []                 # [] = framework.enabled_locales
    role: ROLE_USER
    admin_role: ROLE_ADMIN
    docs_url: ''
    in_app: true
    template: '@NowoManualKitBundle/chapter.html.twig'
    layout: '@NowoManualKitBundle/layout.html.twig'
    standalone_lightbox: false
    cache: cache.app
    machine_translation:
        provider: none          # none | deepl | libretranslate
        api_key: ''
        url: ''
        timeout: 30             # seconds, idle timeout of each provider request
        max_duration: 120       # seconds, hard deadline of each provider request
        formality: default      # default | less | more
        languages: {}
        glossary_domains: [messages]
```

The tree is strict (REQ-SF-006): unknown keys, empty `directory` / `template` / `layout` / `cache` / `admin_role`,
malformed locales, a `docs_url` or `machine_translation.url` that is not an absolute `http(s)` URL, a `deepl` provider
without `api_key`, a `libretranslate` provider without `url` and timeouts below one second stop the container compile
with the offending path and the allowed values, for example:

```text
Invalid configuration for path "nowo_manual_kit.machine_translation": "nowo_manual_kit.machine_translation.url" is
required when "provider" is "libretranslate" (e.g. http://libretranslate:5000).
```

## Table of contents

- [directory](#directory)
- [source_locale](#source_locale)
- [locales](#locales)
- [role](#role)
- [admin_role](#admin_role)
- [docs_url](#docs_url)
- [in_app](#in_app)
- [template](#template)
- [layout](#layout)
- [standalone_lightbox](#standalone_lightbox)
- [cache](#cache)
- [machine_translation](#machine_translation)
- [Timeouts](#timeouts)
- [Asset package](#asset-package)
- [Container parameters](#container-parameters)

## directory

The manual: `manifest.yaml`, the source-language chapters (`{slug}.md`), `images/` and one folder per translation
locale (`es/`, `de/` …). Only chapters listed in the manifest are ever served.

## source_locale

Language of the root files (default `en`). A request in this locale always reads the root files.

## locales

Locales that may have a translation folder. Empty (the default) means `%kernel.enabled_locales%`. A request locale that
is not listed — or is the source locale — reads the root files without a notice.

## role

Role (or any security attribute) needed to read the manual (a string). An empty string makes the manual public. Visitors without
it get a 401 / 403 from the firewall (with `form_login`, the sign-in page).

## admin_role

Readers of `audience: admin` chapters and `<!-- audience: admin -->` blocks (default `ROLE_ADMIN`). Everyone else gets
a 404 on those chapters, and their links read as plain text.

## docs_url

Base URL of the published documentation, e.g. `https://github.com/acme/app/blob/main/docs/` (empty, or an absolute
`http(s)` URL). Links from a chapter to a
repository document that is not a served chapter (`../PRODUCTION.md#tls`) point at `docs_url` + the path relative to
the docs root, and open in a new tab. Also the base of `manual_href()` when a link is not an in-app chapter.

## in_app

`manual_href()` opens the in-app chapter (`true`) or always the published copy at `docs_url` (`false`) — for
applications that ship the manual on GitHub only.

## template

Twig template of a chapter page. Variables: `rendered` (`RenderedManualChapter`: `chapter`, `html`, `toc`), `chapters`
(the chapters the viewer may read, in the request locale), `previous`, `next`. Point it at one of your templates for a
fully custom page ([TEMPLATES.md](TEMPLATES.md)).

## layout

Layout the default chapter template extends. It must define the blocks `title`, `stylesheets` (the template calls
`parent()`) and `body`. The bundle's own layout is a bare HTML page; set your application's base layout to frame the
manual in your navigation.

## standalone_lightbox

`false` (default): the application registers the Stimulus peer in its own build ([USAGE](USAGE.md#lightbox)). `true`:
the default chapter template loads the self-contained script `js/manual-lightbox.js` (Stimulus plus the lightbox,
built by Vite into `src/Resources/public/js`) with `asset('js/manual-lightbox.js', 'nowo_manual_kit')`. Do not enable it
when the peer is already registered: the figures would get two controllers.

## cache

Cache pool service id for rendered chapters (default `cache.app`). Entries are keyed by chapter, locale, translation
state, audience, file mtime and size, manifest mtime and router base URL, and expire after a day.

## machine_translation

`nowo:manual:translate` ([USAGE](USAGE.md#machine-translation)).

| Key | Default | Meaning |
| --- | --- | --- |
| `provider` | `none` | `none` (the command is off), `deepl` or `libretranslate`. Both need `symfony/http-client`. |
| `api_key` | `''` | DeepL key (a key ending in `:fx` goes to api-free.deepl.com); optional LibreTranslate key. Use `%env()%`. |
| `url` | `''` | LibreTranslate base URL (required for it, e.g. `http://libretranslate:5000`); DeepL endpoint override. Absolute `http(s)` only. |
| `timeout` | `30` | Idle timeout of each provider request, in seconds (Symfony HttpClient `timeout`; minimum 1). |
| `max_duration` | `120` | Hard deadline of each provider request, connection included, in seconds (`max_duration`; at least `timeout`). |
| `formality` | `default` | DeepL register: `default`, `less` or `more` (sent as `prefer_*`). |
| `languages` | `{}` | Locale => provider language, e.g. `{pt: PT-BR}`. DeepL defaults: `en` → `EN-GB`, `pt` → `PT-PT`. |
| `glossary_domains` | `[messages]` | Domains whose short UI labels replace bold labels in the chapters; `[]` disables it. |

`deepl` needs `api_key`; `libretranslate` needs `url`. Each request logs its start, success or failure on the
application logger without the key or the texts ([SECURITY](SECURITY.md#logging)).

## Timeouts

The only blocking I/O of the bundle is the machine-translation request of `nowo:manual:translate`, a console command:
no web request ever waits on a provider. Deadlines, innermost first (REQ-RUNTIME-001):

| Layer | Setting | Default |
| --- | --- | --- |
| Provider request, idle | `machine_translation.timeout` (HttpClient `timeout`) | 30 s |
| Provider request, total | `machine_translation.max_duration` (HttpClient `max_duration`) | 120 s |
| PHP | CLI has no `max_execution_time`: `max_duration` is the deadline that ends a stalled request | — |

A timeout ends the command for that chapter with `DeepL request failed: …` / `LibreTranslate request failed: …`; the
chapter is left untouched and the run can be repeated. The per-request options override
`framework.http_client.default_options`. Web requests only read files (a chapter, an image) and render Markdown; under
FrankenPHP they are bounded by PHP `max_execution_time` < Caddy `write` < `max_wait_time` as in
[DEMO-FRANKENPHP](DEMO-FRANKENPHP.md#timeouts).

## Asset package

The bundle registers the Symfony asset package `nowo_manual_kit` (`base_path: /bundles/nowomanualkit`, the folder
`assets:install` creates) from its extension's `prepend()`. Templates link the published files with
`asset('css/manual.css', 'nowo_manual_kit')` / `asset('js/manual-lightbox.js', 'nowo_manual_kit')`;
`nowo_manual_asset('css/manual.css')` and `nowo_manual_asset_package()` are Twig shortcuts for the same package. Do not
copy the package into `framework.assets.packages` unless you override it (CDN, `base_urls`).

## Container parameters

Every key is exposed as `nowo_manual_kit.<key>` (e.g. `%nowo_manual_kit.directory%`).
