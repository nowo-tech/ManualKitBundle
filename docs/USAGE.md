# Usage

## Table of contents

- [The manual folder](#the-manual-folder)
- [manifest.yaml](#manifestyaml)
- [Chapters](#chapters)
- [Links](#links)
- [Stable anchors](#stable-anchors)
- [Sub-pages](#sub-pages)
- [External chapters](#external-chapters)
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
- [Using it from a DDD / hexagonal application](#using-it-from-a-ddd--hexagonal-application)

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
| `file` | — | 1.2: serve another Markdown file of the docs root instead of `{slug}.md`, e.g. `../CHANGELOG.md` — see [External chapters](#external-chapters). |
| `include` | — | 1.3: a glob (`../adr/*.md`) or a folder (`../ops/`) of the docs root: one chapter per Markdown file, slug from the file name, the row's other keys for all of them (no `slug`). |
| `toc_depth` | `4` | 1.2: deepest heading level listed in "On this page" (2–4), e.g. `2` for a long CHANGELOG. |
| `parent` | — | 1.6: the slug of the chapter this one sits under — see [Sub-pages](#sub-pages). |

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

## Sub-pages

1.6: `parent` nests a chapter under another one, up to **three levels** (chapter → child → grandchild):

```yaml
chapters:
  - { slug: README }
  - { slug: guide, group: using }
  - { slug: install, parent: guide, tags: [setup] }        # docs/manual/guide/install.md
  - { slug: docker, parent: install, tags: [container] }   # docs/manual/guide/install/docker.md
  - { slug: billing, parent: guide, audience: admin }
```

```text
docs/manual/
├── guide.md
├── guide/
│   ├── install.md
│   ├── install/docker.md
│   └── billing.md
└── es/guide/install.md          # translations mirror the sub-folders
```

- **Files.** A nested chapter's file is `{parent path}/{slug}.md` (`guide/install/docker.md`); when that file does not
  exist, `{slug}.md` at the manual root is used. Translations sit in the same sub-folder under the locale
  (`es/guide/install.md`). Slugs stay unique across the whole manual.
- **URLs.** `/manual/guide`, `/manual/guide/install`, `/manual/guide/install/docker` (routes `nowo_manual_chapter`,
  `nowo_manual_child`, `nowo_manual_grandchild`), plus the `.md` forms that redirect (`/manual/guide/install.md`).
  A nested chapter answers only under its own parents; `/manual/docker` redirects (301) to its place in the tree.
  Anchors are unchanged (`#section`). In templates, link a chapter with `manual_chapter_path(chapter)`.
- **Access.** A child takes its parent's `group` and audience and may only narrow the audience: `audience: member`
  under an admin chapter stays admin-only. A page the viewer cannot open answers 404 and links to it read as plain
  text; a hidden parent hides its whole branch.
- **Navigation.** The chapter list shows children under their parent in a folded `<details>` branch, open along the
  current chapter's branch; previous / next walk the tree depth-first; a parent page lists its sub-pages ("In this
  section"); a nested page shows a breadcrumb *Manual › Parent › Child* — UiKit's `breadcrumbs` partial when UiKit is
  installed (page contract), the same markup from the bundle otherwise.
- **Search.** Sub-pages are indexed with their own `tags` and sections, behind the same access rules, and titled
  "Parent › Child".
- **Links.** Relative links work as on GitHub: `guide/install.md` from the top level, `../guide.md` and
  `install/docker.md` from `guide/install.md`, `../../faq.md` from a grandchild; a bare `child.md` naming another
  chapter also resolves. `nowo:manual:check`, `nowo:manual:stamp` and `nowo:manual:translate` walk the sub-folders.
- **Validation.** An unknown parent, a cycle, a fourth level, a parent under the home page, a parent that is not a slug
  or a nested chapter without a file inside the manual (a symbolic link out of it included) leaves that chapter and its
  branch out; a translation linked out of the manual is ignored (the source is served) and records a problem; `nowo:manual:check --strict` / `--sources` fail on it. A child's own `group` is
  ignored (and reported).

A manifest without `parent` renders exactly as in 1.5.

## External chapters

A manifest row with `file` serves a Markdown file that lives elsewhere in the docs root (the folder that holds the
manual), such as the project CHANGELOG:

```yaml
chapters:
    - { slug: changelog, file: ../CHANGELOG.md, group: administration, audience: admin, toc_depth: 2 }
```

- The path is relative to the manual folder and must resolve to a `.md` file inside the docs root; anything else stops
  the catalogue with an error.
- Its relative links start from its own folder: in `docs/CHANGELOG.md`, `UPGRADING.md#to-2` opens the published
  `docs/UPGRADING.md` (`docs_url`) and `manual/02-dashboard.md` opens the in-app chapter.
- It is source-only: every locale reads the file as it is, with no "not translated" notice, and `nowo:manual:check`,
  `nowo:manual:stamp` and `nowo:manual:translate` skip it.

`include` (1.3) does the same for every Markdown file of a glob or a folder, in file-name order:

```yaml
    - { include: '../adr/*.md', group: administration, audience: admin }   # docs/adr/0001-use-mysql.md → slug 0001-use-mysql
    - { include: '../ops/' }                                              # every docs/ops/*.md
```

**Nothing the manifest lists can break the site (1.3).** A missing file, a `file` / `include` outside the docs root, a
pattern that matches nothing, a bad or repeated slug: that chapter is left out and the catalogue records a *problem*
(`ManualCatalog::problems()`). `nowo:manual:check` prints them; `--strict` and `--sources` fail on them. Run
`php bin/console nowo:manual:check --sources` where the files must exist — in CI and when the production container
starts — so a file your image does not ship (a `.dockerignore` rule) shows up before users miss the chapter.

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
php bin/console nowo:manual:check --strict   # exit 1 on manifest problems, outdated translations or missing anchors (CI)
php bin/console nowo:manual:check --sources  # exit 1 when a manifest source is missing (container start, deploy)
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

1.9: to use your own translation service (an internal gateway, another provider), implement
`Nowo\ManualKitBundle\Translation\MachineTranslatorInterface` and set `provider: service` with
`service: <your service id>` (the container compile fails when that service does not implement the interface).

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

It needs `@hotwired/stimulus` in your application. Without a Stimulus build, set `standalone_lightbox: true`: the
default template then loads `asset('js/manual-lightbox.js', 'nowo_manual_kit')`, a Vite IIFE of Stimulus plus this
controller ([CONFIGURATION](CONFIGURATION.md#standalone_lightbox)). Captions are set with `textContent`, never HTML.

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

## Using it from a DDD / hexagonal application

ManualKit is a **presentation** module: an in-app help UI (controller, Twig, routes, an optional SearchKit group)
over a read-only catalog of Markdown files in `docs/manual`. It has no Doctrine entities, no persistence and no host
entity types in its API, so your domain never has to know about it.

**Keep the domain free of the bundle**

- Import its routes and templates in your UI layer only (`@NowoManualKitBundle/Resources/config/routes.php`,
  `manual_href()` / `manual_chapter_path()` in Twig).
- Access is decided by Symfony security, not by a user class: `role` and `admin_role` are any `isGranted()`
  attribute. Map them to your own voter (e.g. `role: MANUAL_READ`) to put your access policy behind your ACL.
- Per-chapter rules from your domain (plan, tenant, feature flag) go behind
  `Security\ManualChapterAccessCheckerInterface` (1.9): alias it to your adapter. It is asked after the built-in
  `audience: admin` rule, for every chapter in the navigation, previous / next, sub-pages, search, images and the page
  itself (a refused chapter is a 404 and hides its sub-pages; once a chapter is refused, an image is only served when
  it appears in a chapter the viewer may open, for administrators too). The default allows every chapter the roles
  allow. The checker is a shared service: read the current user / plan / tenant on every call (no state kept between
  requests in a worker) and keep it cheap, it runs once per chapter per page and per search.
- To replace the whole policy, alias `Security\ManualAccessInterface` (`canRead()`, `isAdmin()`,
  `canReadChapter()`); the default is `ManualAccess`. The bundle still enforces `audience: admin` itself
  (`isAdmin()` decides), so your `canReadChapter()` can only narrow access.
- If application code needs manual data (a "help" link, a list of chapters), wrap `ManualCatalog` /
  `ManualRenderer` behind a port of your own; their results are value objects (`ManualChapter`,
  `RenderedManualChapter`, `TranslationState`), never entities.

```php
// A per-chapter rule (infrastructure layer) over your application port
final readonly class PlanChapterAccess implements \Nowo\ManualKitBundle\Security\ManualChapterAccessCheckerInterface
{
    public function __construct(private CurrentPlan $plan) {} // your port

    public function canReadChapter(\Nowo\ManualKitBundle\Model\ManualChapter $chapter): bool
    {
        return $chapter->group !== 'billing' || $this->plan->includesBilling();
    }
}
```

```yaml
services:
    Nowo\ManualKitBundle\Security\ManualChapterAccessCheckerInterface: '@App\Help\PlanChapterAccess'
```

```php
// Your port (application layer)
interface HelpLinks
{
    public function chapterUrl(string $slug, ?string $locale = null): ?string;
}

// Your adapter (infrastructure layer)
final readonly class ManualKitHelpLinks implements HelpLinks
{
    public function __construct(
        private \Nowo\ManualKitBundle\Catalog\ManualCatalog $catalog,
        private \Nowo\ManualKitBundle\Security\ManualAccessInterface $access,
        private \Symfony\Component\Routing\Generator\UrlGeneratorInterface $urls,
    ) {
    }

    public function chapterUrl(string $slug, ?string $locale = null): ?string
    {
        $chapter = $this->catalog->find($slug, $locale);
        if ($chapter === null || !$this->access->canReadChapter($chapter)) {
            return null;
        }

        return \Nowo\ManualKitBundle\Routing\ManualRoutes::generate($this->urls, $chapter);
    }
}
```

**Ports and extension points**

| Point | Kind | Use |
| --- | --- | --- |
| `Translation\MachineTranslatorInterface::translate(list<string>, source, target): list<string>` | SPI | Machine translation for `nowo:manual:translate`: DeepL and LibreTranslate built in, or yours with `machine_translation.provider: service` + `service: <id>` (1.9) |
| `Security\ManualChapterAccessCheckerInterface::canReadChapter(ManualChapter)` | SPI (alias) | Per-chapter rule of the host (1.9); default allow-all |
| `Security\ManualAccessInterface` | port (alias) | The whole access policy (1.9); default `ManualAccess` (roles + chapter rule) |
| `role`, `admin_role` | security attributes | Hook your voter / ACL |
| `Search\ManualSearchProvider` | SearchKit `SearchProviderInterface` | "Manual" group in the global search, only when SearchKit is installed |
| `template`, `layout`, `templates/bundles/NowoManualKitBundle/` | config / Twig | Your own page and layout |
| `cache` | config | Cache pool for rendered chapters |

**Known limitations**

- Links to a chapter refused by your per-chapter rule, inside the text of another chapter, stay links (rendered
  chapters are cached per audience, not per user) and lead to a 404.
- The bundle dispatches no events (chapter viewed, translation written).
- Chapters are read from the filesystem only; `ManualChapter::$path` is an absolute file path.
- `ManualCatalog` and `ManualRenderer` are final classes without interfaces; wrap them behind your own port.
