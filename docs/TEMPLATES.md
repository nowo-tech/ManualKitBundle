# Templates

ManualKitBundle ships **one page**, the chapter page (`chapter.html.twig`, also used for the home), and a bare
`layout.html.twig` it extends by default. The page is meant to be framed in the host's layout (`layout`) or replaced by
the host's own template (`template`). The bundle has no UiKit dependency: its styles come from `manual.css` and the
`--nowo-manual-*` custom properties.

## Table of contents

- [Pages](#pages)
- [Chapter page parts](#chapter-page-parts)
- [Variables](#variables)
- [Overrides](#overrides)
- [Tokens](#tokens)
- [Allowed exceptions](#allowed-exceptions)

## Pages

| Template | Route(s) | Extends |
| --- | --- | --- |
| `@NowoManualKitBundle/chapter.html.twig` (configurable: `template`) | `nowo_manual_index`, `nowo_manual_chapter` | `nowo_manual_layout()` = the `layout` option |
| `@NowoManualKitBundle/layout.html.twig` | — | — (HTML document; blocks `title`, `stylesheets`, `body`) |

`tests/Functional/ManualControllerTest.php` renders both, as a member, an administrator, in English and Spanish, and with
a custom `template`.

## Chapter page parts

| Part | Element | Hook |
| --- | --- | --- |
| Root | `.nowo-manual` | `data-testid="manual"` |
| Chapter list | `nav.nowo-manual__nav`, groups `.nowo-manual__group`, `.nowo-manual__list` (`aria-current="page"`) | `manual-nav`, `manual-nav-<slug>` |
| Chapter | `article.nowo-manual__chapter` (Stimulus `nowo-manual-lightbox`) | `manual-chapter`, `data-chapter` |
| Translation notice | `.nowo-manual__notice--missing` / `--outdated` (`role="note"`) | `manual-translation-notice` |
| On this page | `nav.nowo-manual__toc` (`h2`, `h3`) | `manual-toc` |
| Body | `.manual-prose` (the rendered HTML: `.manual-figure`, `.manual-gallery`, `.manual-zoom`, `.manual-anchor`) | — |
| Lightbox | `dialog.manual-lightbox` and its targets | `manual-lightbox`, `manual-lightbox-close` |
| Pager | `nav.nowo-manual__pager` (`rel="prev"` / `rel="next"`) | `manual-previous`, `manual-next` |

All strings come from the `NowoManualKitBundle` domain (`title`, `home`, `chapters`, `on_this_page`, `pager`, `zoom`,
`lightbox.*`, `group.*`, `notice.*`).

## Variables

| Variable | Type | Content |
| --- | --- | --- |
| `rendered` | `RenderedManualChapter` | `chapter` (`ManualChapter`), `html` (sanitised), `toc` (`level`, `id`, `text`, `tags`) |
| `chapters` | `list<ManualChapter>` | The chapters the viewer may read, resolved for the request locale |
| `previous`, `next` | `ManualChapter\|null` | Neighbours among those chapters |

`ManualChapter`: `slug`, `group`, `adminOnly`, `icon`, `title`, `path`, `tags`, `locale`, `translation`
(`TranslationState`: `.value` is `source`, `translated`, `outdated` or `missing`), `isHome()`.

## Overrides

- One block or the whole file: `templates/bundles/NowoManualKitBundle/chapter.html.twig`, with
  `{% extends '@!NowoManualKitBundle/chapter.html.twig' %}` to keep the rest.
- A page of the application's own: `nowo_manual_kit.template: 'manual/chapter.html.twig'` (icons, search button,
  breadcrumbs — whatever the host has). Keep `.manual-prose` around `rendered.html|raw` and the lightbox markup for the
  default figure styles and zoom.

## Tokens

`manual.css` paints everything from `--nowo-manual-*` custom properties on `:root` ([USAGE — Styling](USAGE.md#styling)).
Map them to the host's design tokens instead of overriding rules.

## Allowed exceptions

- `rendered.html|raw`: the HTML has been through the Symfony HtmlSanitizer in `ManualRenderer`.
