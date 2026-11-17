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
    groups: []                  # 1.8: [{key, label, translation_domain}] or [key], navigation order and labels
    redirects: {}               # 1.11: chapters and anchors that moved, old → new (plus the manifest's redirects: file)
    standalone_lightbox: false
    cache: cache.app
    machine_translation:
        provider: none          # none | deepl | libretranslate | service (1.9)
        service: ''             # 1.9: with provider service, your MachineTranslatorInterface service id
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
without `api_key`, a `libretranslate` provider without `url`, a `service` provider without `service` and timeouts below one second stop the container compile
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
- [groups](#groups)
- [redirects](#redirects)
- [standalone_lightbox](#standalone_lightbox)
- [cache](#cache)
- [machine_translation](#machine_translation)
- [Timeouts](#timeouts)
- [Asset package](#asset-package)
- [Container parameters](#container-parameters)

## directory

The manual: `manifest.yaml`, the source-language chapters (`{slug}.md`), `images/` and one folder per translation
locale (`es/`, `de/` …). Only chapters listed in the manifest are ever served.

1.6: the manual may have sub-folders for nested chapters (`parent` in the manifest, see
[USAGE](USAGE.md#sub-pages)); no configuration is involved and no file outside `directory` is ever served for them.

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
(the chapters the viewer may read, in the request locale), `previous`, `next`, `ancestors`, `children` and `groups`
(1.8). Point it at one of your templates for a fully custom page, or at a template that extends
`@NowoManualKitBundle/chapter.html.twig` and fills its blocks (1.8: `manual_nav`, `manual_body`, ...; see
[TEMPLATES.md](TEMPLATES.md#blocks)).

## layout

Layout the default chapter template extends. It must define the blocks `title`, `stylesheets` (the template calls
`parent()`) and `body`. The bundle's own layout is a bare HTML page; set your application's base layout to frame the
manual in your navigation.

## groups

1.8. Order and headings of the chapter groups (manifest `group`) in the navigation. Each entry is a key or
`{key, label, translation_domain}`:

```yaml
nowo_manual_kit:
    groups:
        - { key: using, label: manual.group.using }          # translated in `messages` (the default domain)
        - { key: project, label: manual.group.project }
        - { key: administration, label: admin.manual, translation_domain: admin }
        - organization                                        # label null: group.organization of NowoManualKitBundle
```

Listed groups come first, in this order; groups of the manifest that are not listed follow in manifest order, so no
chapter disappears. Groups without a chapter the viewer may read are left out. A key may appear once. `[]` (default)
keeps the manifest order and the bundle's `group.<key>` labels. The chapter template receives the result as `groups`.

## redirects

1.11. Chapters and anchors that moved, `old → new`, so the links people bookmarked or pasted keep working:

```yaml
nowo_manual_kit:
    redirects:
        04-projects-and-issues: projects                    # a chapter renamed
        05-admin-and-ops: admin-hub                         # a chapter split: the rest of the page goes here…
        '05-admin-and-ops#users': 'organization#users'      # …and these sections went to other chapters
        '05-admin-and-ops#backups': 'backups'               # a section that became a chapter of its own
        09-ui-kit-chrome: 'appearance#ui-kit'               # a chapter merged into a section of another
        'organization#members': 'organization#users'        # a heading renamed inside an existing chapter
```

The same map can live next to the chapters, which suits a generated one: name a YAML file of the manual directory
from `manifest.yaml`, or write the mapping inline there.

```yaml
# docs/manual/manifest.yaml
redirects: redirects.yaml
chapters:
    - …
```

```yaml
# docs/manual/redirects.yaml (the map at the top level, or under a `redirects:` key)
redirects:
    04-projects-and-issues: projects
    '05-admin-and-ops#users': 'organization#users'
```

Both sources are merged; one old path mapped to two different targets is an error. Quote the keys and targets that
hold a `#`. YAML keys keep their dashes (they are not normalised to underscores).

**Validation, at container build.** The map is read and compiled once, when the container is built (the files are
tracked, so a debug container rebuilds when they change). The build stops, naming every offending entry, when:

- a key or target is not `slug` or `slug#anchor` — targets are internal chapter slugs only: an absolute URL, a
  scheme, `//`, a path or an empty anchor is refused, so the map can never become an open redirect;
- the map has a cycle (`a → b → a`, `a → a#x`, or one through a moved anchor);
- the manifest names a redirects file that is missing, is not a `.yaml` / `.yml` file inside the manual directory, or
  cannot be parsed.

Chains (`a → b`, `b → c`) are accepted and **collapsed**: `/manual/a` answers one 301 straight to `c`. The walk
stops at the first chapter that still exists. If `b` is still a chapter, `/manual/a` goes to `b`, because an existing
chapter always wins over a rule for its slug. The lint reports the rule for `b` as an error and names every rule whose
chain passes through it.
`nowo:manual:redirects` reports them so you can write the final target ([USAGE](USAGE.md#chapter-redirects)).
The map is immutable for the life of the container (one deploy): nothing is read per request. A `directory` built
from an env var cannot be read at build time; only the inline map is used then.

**How a path resolves.** An exact rule wins (`old#install` → `setup#docker`). Otherwise `old#anchor` follows the
chapter rule of `old`: to `new#anchor` when the target has no anchor (the fragment travels, as a browser keeps it
through a 301) or to the target's own anchor when it has one. A chapter rule applies only when the old slug is no
longer a chapter of the manifest (an existing chapter always wins; the lint command flags such a rule).

**Server side.** `GET /manual/<old-slug>` (and `/manual/<old-slug>.md`, the child routes and a path through a parent
that moved) answers **301** to the new chapter in the request locale (`/es/manual/<old>` → `/es/manual/<new>`). The
target goes through the same checks as a request for it, **before** the redirect: a reader who may not open it gets
exactly the 404 the target itself would give (an anonymous visitor is asked to sign in first, as for any chapter).
The map therefore adds no existence oracle and never reveals where a hidden chapter went. A target's `#anchor` is
added to the `Location` only when the reader's rendering of the target has it, so the id of an admin block never
reaches a member. Every 301 of the bundle is sent with `Cache-Control: private, no-cache`, because its answer depends
on who asks.

**Anchors, client side.** A 301 cannot see the `#fragment`, so the chapter page carries the anchors that moved in
`data-nowo-manual-anchors` and the default template loads `js/manual-anchors.js` (an external, deferred file: no
inline script, CSP `script-src 'self'` is enough) only on pages that have some:

- when the old chapter has anchors mapped elsewhere, its 301 goes to `/manual/<new>?moved_from=<old>` (no fragment,
  so the browser keeps the reader's) and the script sends the reader to the mapped anchor (`''`: the target's anchor
  for a reader who came without one), then removes `moved_from` from the address bar;
- `own` anchors of an existing chapter (`'organization#members': 'organization#users'`) apply when the fragment is not
  on the page.

Entries whose target chapter the reader may not open, or whose target anchor is not in the reader's rendering (an
admin block, a translation without the stable anchor), are left out of the map. A page reached with `moved_from`
prints `<link rel="canonical">` to the chapter URL without the marker. Without JavaScript the reader still lands on the new chapter (at its top, or at the anchor of a
plain chapter-to-anchor rule, which the 301 carries). A custom chapter template keeps the feature by printing the
`anchor_redirects` variable the same way ([TEMPLATES](TEMPLATES.md)).

Search, navigation and every link the bundle prints only ever use the current slugs. Links written inside the
chapters are not rewritten. `nowo:manual:redirects` reports those that point at an old slug or a moved anchor, a
missing chapter or a missing anchor, with their file and line ([USAGE](USAGE.md#chapter-redirects)).

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
| `provider` | `none` | `none` (the command is off), `deepl` or `libretranslate` (both need `symfony/http-client`), or (1.9) `service`: your own `MachineTranslatorInterface`. |
| `service` | `''` | 1.9: with `provider: service`, the id of a service implementing `Nowo\ManualKitBundle\Translation\MachineTranslatorInterface` (required then). `api_key`, `url`, `timeout`, `max_duration`, `formality` and `languages` are not used; the glossary still applies. |
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

Every key is exposed as `nowo_manual_kit.<key>` (e.g. `%nowo_manual_kit.directory%`). 1.11:
`%nowo_manual_kit.redirects%` holds the merged map (config + manifest file) as written, before chains are collapsed.
