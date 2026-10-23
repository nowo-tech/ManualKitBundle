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
    cache: cache.app
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
- [cache](#cache)
- [machine_translation](#machine_translation)
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

Role (or any security attribute) needed to read the manual. An empty string makes the manual public. Visitors without
it get a 401 / 403 from the firewall (with `form_login`, the sign-in page).

## admin_role

Readers of `audience: admin` chapters and `<!-- audience: admin -->` blocks (default `ROLE_ADMIN`). Everyone else gets
a 404 on those chapters, and their links read as plain text.

## docs_url

Base URL of the published documentation, e.g. `https://github.com/acme/app/blob/main/docs/`. Links from a chapter to a
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

## cache

Cache pool service id for rendered chapters (default `cache.app`). Entries are keyed by chapter, locale, translation
state, audience, file mtime and size, manifest mtime and router base URL, and expire after a day.

## machine_translation

`nowo:manual:translate` ([USAGE](USAGE.md#machine-translation)).

| Key | Default | Meaning |
| --- | --- | --- |
| `provider` | `none` | `none` (the command is off), `deepl` or `libretranslate`. Both need `symfony/http-client`. |
| `api_key` | `''` | DeepL key (a key ending in `:fx` goes to api-free.deepl.com); optional LibreTranslate key. Use `%env()%`. |
| `url` | `''` | LibreTranslate base URL (required for it, e.g. `http://libretranslate:5000`); DeepL endpoint override. |
| `formality` | `default` | DeepL register: `default`, `less` or `more` (sent as `prefer_*`). |
| `languages` | `{}` | Locale => provider language, e.g. `{pt: PT-BR}`. DeepL defaults: `en` → `EN-GB`, `pt` → `PT-PT`. |
| `glossary_domains` | `[messages]` | Domains whose short UI labels replace bold labels in the chapters; `[]` disables it. |

## Container parameters

Every key is exposed as `nowo_manual_kit.<key>` (e.g. `%nowo_manual_kit.directory%`).
