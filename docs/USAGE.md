# Usage

## Table of contents

- [The manual folder](#the-manual-folder)
- [manifest.yaml](#manifestyaml)
- [Chapters](#chapters)
- [Links](#links)
- [Stable anchors](#stable-anchors)
- [Audiences](#audiences)
- [Figures](#figures)
- [Translations](#translations)
- [Checking translations](#checking-translations)
- [Machine translation](#machine-translation)
- [Linking from the application](#linking-from-the-application)
- [Templates](#templates)
- [Styling](#styling)
- [Lightbox](#lightbox)
- [Search](#search)
- [Services for your own code](#services-for-your-own-code)

## The manual folder

```text
docs/manual/                 # nowo_manual_kit.directory
├── manifest.yaml            # the chapters, in reading order
├── README.md                # the home page (slug README, route nowo_manual_index)
├── 01-getting-started.md
├── 02-dashboard.md
├── 05-administration.md
├── images/                  # shared by every language
│   └── dashboard.webp
└── es/                      # one folder per translation locale
    ├── README.md
    └── 02-dashboard.md
```

The root files are the manual's source language (`source_locale`). The same folder is a valid GitHub manual: every link
and image below resolves on GitHub too.

## manifest.yaml

```yaml
chapters:
  - { slug: README, icon: 'tabler:home' }
  - { slug: 01-getting-started, group: using, tags: [start, onboarding, empezar] }
  - { slug: 02-dashboard, group: using, icon: 'tabler:layout-dashboard' }
  - { slug: 05-administration, group: administration, audience: admin, tags: [users, roles] }
```

| Key | Default | Meaning |
| --- | --- | --- |
| `slug` | — (required) | File name without `.md`; letters, digits and dashes only. `README` is the home page. |
| `group` | `using` | Sidebar group; its label is the translation `group.<key>` (domain `NowoManualKitBundle`). The bundle ships `using`, `project` and `administration`; add your own keys in `translations/NowoManualKitBundle.<locale>.yaml`. |
| `audience` | `member` | `admin`: only `admin_role` may read the chapter. |
| `icon` | `tabler:book` | Icon name for custom templates (the default template renders none). |
| `tags` | `[]` | Search keywords and synonyms, in any language. |

A slug that is not `[A-Za-z0-9-]+`, or a listed chapter without its file, throws when the manifest is read — a broken
manual fails loudly in tests instead of serving a partial one.

## Chapters

Plain CommonMark + GitHub Flavored Markdown (tables, task lists, strikethrough, autolinks), raw HTML allowed but always
sanitised. The first `# Title` line is the chapter title (in the sidebar, the page `<h1>`, the search) and is removed
from the body. `##` – `####` headings get GitHub's ids and a `#` permalink; `##` and `###` form the "On this page" list.

CommonMark attributes style an image or a link: `![Wide](images/a.webp){.manual-img--small width=320}`.

## Links

| In the Markdown | In the app |
| --- | --- |
| `02-dashboard.md`, `./02-dashboard.md#filters` | The chapter route, fragment kept |
| `README.md` | `nowo_manual_index` |
| A link to an `audience: admin` chapter | The link for administrators; plain text for members |
| `../PRODUCTION.md#tls`, `05-unlisted.md`, `../ops/` | `docs_url` + the path from the docs root, new tab |
| `https://…`, `mailto:…` | Unchanged, `target="_blank" rel="noopener noreferrer"` for http(s) |
| `#section` | Unchanged |

From a translation folder, write the same links with `../` (`../02-dashboard.md#filters`, `../images/a.webp`): they
resolve on GitHub from `es/`, and the app treats them like the source links.

## Stable anchors

GitHub derives a heading id from its text, so a translated heading gets another id and a link from the application to
`#filters` breaks in Spanish. Give the sections you link to an explicit id — on its own line before the heading
(followed by a blank line), or inside it:

```markdown
<a id="filters"></a>

## Filter the list

## Users <a id="users"></a>
```

The heading then carries `id="filters"` in every language, in the app and on GitHub. Keep the same `<a id>` in each
translation; `nowo:manual:check` reports the ones a translation lacks.

## Audiences

```markdown
<!-- audience: admin -->
Only administrators read this paragraph (`admin_role`).
<!-- /audience -->

<!-- audience: github -->
Shown on GitHub only — never in the app (screenshot instructions, demo credentials).
<!-- /audience -->
```

Members never receive an admin block, an admin chapter (404, not 403: the response does not reveal that it exists) or a
link to one. The filter **fails closed**: a marker left without its pair — an unclosed block, a typo such as
`<!-- audience admin -->` — hides the rest of the chapter from members instead of showing them what it was guarding.

## Figures

An image alone in its paragraph becomes a figure:

```markdown
![The dashboard](images/dashboard.webp "Your projects at a glance")
```

- `src` → the `nowo_manual_image` route (`images/x` or `../images/x`; webp, png, jpg, gif, svg);
- the title becomes the `<figcaption>` (and leaves the `<img>`);
- width and height come from the file, `loading="lazy"`, `decoding="async"`;
- a taller-than-wide image (a phone screenshot) gets `manual-img--portrait` / `manual-figure--portrait`, sized like the
  desktop screenshots instead of filling the column;
- several images in one paragraph become a `.manual-gallery`, side by side;
- the image is wrapped in `a.manual-zoom` (opens the file; the lightbox takes over when it is registered).

An image inside a sentence stays inline. Remote images (`https://…`) stay remote.

## Translations

1. Copy the source chapter to `{locale}/{slug}.md` and translate it — keep the stable anchors and use `../` links.
2. Record the source it was written from:

```bash
php bin/console nowo:manual:stamp es 02-dashboard
```

This adds (or replaces) `<!-- translation-of: 1a2b3c4d5e6f -->` on top of the translation: the first 12 hex digits of
the SHA-256 of the source file. From then on:

| State | When | What the reader sees |
| --- | --- | --- |
| `translated` | the marker matches the source | the translation |
| `outdated` | the source changed since the stamp (or no / another marker) | the translation and a notice that parts may be out of date |
| `missing` | no `{locale}/{slug}.md` | the source and a notice that the chapter is not translated yet |
| `source` | a source-locale or unlisted locale request | the source, no notice |

Chapter titles, the sidebar and the search follow the translation. Images are shared (`images/`).

## Checking translations

```bash
php bin/console nowo:manual:check            # table: locale, chapter, state, missing anchors
php bin/console nowo:manual:check --strict   # exit 1 on outdated translations or missing anchors (CI)
```

Missing translations are listed but never fail the check: a new chapter can ship before its translations.

## Machine translation

`nowo:manual:translate` writes the missing and outdated chapters of a locale with a machine translation service
and stamps them. Configure a provider ([CONFIGURATION](CONFIGURATION.md#machine_translation)):

```yaml
nowo_manual_kit:
    machine_translation:
        provider: deepl                     # or libretranslate (self-hosted, no account)
        api_key: '%env(DEEPL_API_KEY)%'     # a DeepL API Free key ends in :fx
        formality: less                     # informal "tú / du" where the language has it
```

```bash
php bin/console nowo:manual:translate es --dry-run          # what would be sent, in characters
php bin/console nowo:manual:translate es                    # missing + outdated chapters
php bin/console nowo:manual:translate es --chapter=02-dashboard --force
```

- **Only prose is sent**, one text per paragraph, heading, list item or table cell. Fenced code, `<a id>` anchors,
  HTML comments (audiences, tags), inline code, link and image targets, attributes, inline HTML and URLs stay as they
  are; relative links get the `../` of the translation folder.
- **UI labels follow the application.** A bold label (`**Settings**`, `**Administration → Users**`) that the
  application's catalogues translate (`glossary_domains`, default `messages`) takes the label of the translated
  interface instead of a free translation.
- **People win.** A written file starts with `<!-- machine-translated -->`. Remove that line once someone has
  reviewed the chapter: the command then leaves it alone (it reports it as edited by a person) and an outdated
  reviewed chapter is updated by hand, unless you pass `--force`.
- Costs stay small: an up-to-date chapter is never sent again; only a changed source re-sends its chapter.

## Linking from the application

`manual_href()` takes a path relative to the docs root, the way the repository links:

```twig
<a href="{{ manual_href('manual/02-dashboard.md#filters') }}"
   {% if manual_is_external('manual/02-dashboard.md') %}target="_blank" rel="noopener"{% endif %}>Help</a>
```

A chapter listed in the manifest opens in-app (when `in_app` is on); anything else opens `docs_url` + the path.

## Templates

- **Frame the default page in your layout:** `layout: 'base.html.twig'` (blocks `title`, `stylesheets`, `body`).
- **Override the bundle template:** `templates/bundles/NowoManualKitBundle/chapter.html.twig`, extending
  `@!NowoManualKitBundle/chapter.html.twig` to change one block, or replacing it.
- **Use your own template:** `template: 'manual/chapter.html.twig'` with the variables `rendered`, `chapters`,
  `previous`, `next` ([CONFIGURATION — template](CONFIGURATION.md#template)).

Keep `rendered.html|raw` inside an element with the class `manual-prose` and the lightbox markup if you want the
default styles and zoom. Details: [TEMPLATES.md](TEMPLATES.md).

## Styling

`manual.css` paints everything from custom properties declared on `:root`; set them in your stylesheet:

| Property | Default | Used for |
| --- | --- | --- |
| `--nowo-manual-ink` | `#1f2933` | Text; mixed for code, table and rule tints |
| `--nowo-manual-mist` | `#f4f6f8` | Hover and current-chapter backgrounds, notices, table stripes |
| `--nowo-manual-sand` | `#d9dee3` | Borders and rules |
| `--nowo-manual-accent` | `#2f6f5e` | Notice and blockquote edge, focus ring |
| `--nowo-manual-accent-deep` | `#22524a` | Current chapter, links, pager |
| `--nowo-manual-surface` | `#ffffff` | Declared for overrides (no default rule uses it) |
| `--nowo-manual-radius` | `0.75rem` | "On this page" box, figures, lightbox |
| `--nowo-manual-radius-control` | `0.5rem` | Chapter links, notices, inline code |
| `--nowo-manual-header-height` | `0px` | Offset of the sticky chapter list and of anchored headings under a fixed header |

## Lightbox

`manual_lightbox_controller.ts` (Stimulus peer): a click on a figure opens it full size in a native `<dialog>` with its
caption (the figcaption, else the alt text), previous / next across the chapter's figures (buttons and ← →), Escape, the
backdrop or the close button to leave. Register it as `nowo-manual-lightbox`:

```ts
// assets/controllers/nowo_manual_lightbox_controller.ts
export { default } from '../../vendor/nowo-tech/manual-kit-bundle/src/Resources/assets/stimulus-peers/manual_lightbox_controller';
```

It needs `@hotwired/stimulus` in your application. Captions are set with `textContent`, never HTML.

## Search

With `nowo-tech/search-kit-bundle` installed, `Nowo\ManualKitBundle\Search\ManualSearchProvider` adds a "Manual" group
to the header search: chapters whose title or manifest `tags` match, then `##` – `####` sections whose heading or
section tags match, among the chapters the viewer may read, in the request locale. Every word of the query must match
(case and accents ignored). Section tags go right under the heading and stay invisible on GitHub:

```markdown
## Reset your password
<!-- tags: forgot, contraseña, login -->
```

The group label is the translation `search.group`; the subtitle of a chapter is `title · group.<key>`.

## Services for your own code

| Service | Use |
| --- | --- |
| `ManualCatalog` | `all(?locale)`, `visible(admin, ?locale)`, `find(slug, ?locale)`, `neighbours()`, `sourceHash()` |
| `ManualRenderer` | `render(ManualChapter, admin)` → `RenderedManualChapter` (`html`, `toc` with ids and tags); `audienceFiltered()` |
| `ManualAccess` | `canRead()`, `isAdmin()` with the configured roles |
| `TranslationAudit` | `run()` (what `nowo:manual:check` prints), `stamp(slug, locale)`, `anchors(markdown)` |

A useful application test renders every chapter for both audiences and asserts that every in-app link, anchor and image
resolves, and that each `manual/x.md#anchor` your templates reference exists.
