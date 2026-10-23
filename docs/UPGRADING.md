# Upgrading

This guide lists the changes integrators must apply when upgrading between versions.

## Table of contents

- [Unreleased](#unreleased)
- [1.0.1 (2026-10-23)](#101-2026-10-23)
- [1.0.0 (2026-10-23)](#100-2026-10-23)
  - [From an application-local manual](#from-an-application-local-manual)

## Unreleased

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
