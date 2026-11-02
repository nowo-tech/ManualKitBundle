# Feature Specification: Sub-pages (chapter tree, up to three levels)

**Feature Branch**: `feat/1.6.0`  
**Created**: 2026-11-02  
**Status**: Implemented (1.6.0, unreleased)  
**Input**: symfony-beacon asked for sub-pages in the manual. Agreed scope: an optional `parent` per chapter, at most
three levels as a hard limit, nested URLs, inherited access, tree navigation, search, links, translations, strict BC.

**Related**: [`../001-baseline/spec.md`](../001-baseline/spec.md) ·
[`../001-baseline/code-inventory.md`](../001-baseline/code-inventory.md) · [`docs/USAGE.md#sub-pages`](../../docs/USAGE.md#sub-pages)

## User Scenarios & Testing

### User Story 1 — Organise a long manual (P1)

A writer nests chapters (`guide` → `install` → `docker`) with `parent`, optionally in sub-folders. **Test**:
`ChapterTreeTest::testTheCatalogWalksTheTreeDepthFirstAndInheritsAccess`, `ChapterTreeManifestTest`.

### User Story 2 — Read and move through the tree (P1)

A reader opens `/manual/guide/install/docker`, sees the open branch in the chapter list, a breadcrumb
*Manual › Guide › Install › Docker*, walks previous / next depth-first and gets "In this section" on parent pages.
**Test**: `ChapterTreeTest::testAMemberReadsTheTreeWithItsNavigationBreadcrumbAndSubPages`, demo e2e `tree.spec.ts`.

### User Story 3 — Access never widens down the tree (P1)

A child of an admin chapter is admin-only whatever it declares; a member gets 404 for it, sees links to it as plain
text and never finds it in search. **Test**: `ChapterTreeTest::testAccessIsInheritedAndEveryOtherPathIsA404`,
`testSearchIndexesSubPagesWithTheirParentsAndAccess`, demo e2e.

### User Story 4 — Translate nested chapters (P2)

Translations mirror the sub-folders (`es/guide/install.md`); check, stamp and machine translation walk them.
**Test**: `ChapterTreeTest::testTranslationsLiveInTheSameSubFolders`, `testStampAndTranslateWriteIntoTheSubFolder`.

### User Story 5 — Nothing changes for a flat manual (P1)

**Test**: `FlatManualBcTest` compares pages, search results, catalogue and problems with golden files written by the
v1.5.1 code (`tests/Fixtures/bc-1.5.1`).

## Requirements

- **FR-TREE-001** Manifest `parent: <slug>` on any row (`slug`, `file`, `include`). Depth ≤ 3
  (`ManualChapter::MAX_DEPTH`). An invalid parent value, an unknown parent, a cycle, a fourth level, the home page
  (`README`) as a parent or a child, or a nested chapter without a file inside the manual (looked up at
  `{ancestors}/{slug}.md`, then `{slug}.md`; symbolic links resolving outside the manual refused) leaves the chapter
  and its descendants out and records a `problems()` entry. Order: depth-first, siblings in manifest order. A child
  takes the group of its top-level chapter (own `group` ignored and reported) and is admin-only when it or any
  ancestor is. Slugs stay unique manual-wide.
- **FR-TREE-002** Routes `nowo_manual_child` `/{parent}/{chapter}`, `nowo_manual_grandchild`
  `/{grandparent}/{parent}/{chapter}`, `nowo_manual_child_md`, `nowo_manual_grandchild_md` (301 to the chapter);
  segments `[A-Za-z0-9][A-Za-z0-9-]*`. A nested chapter answers only under its own ancestors (404 otherwise); the flat
  `nowo_manual_chapter` / `nowo_manual_chapter_md` redirect (301) a readable nested slug to its place. Existing
  route names, requirements and URLs are unchanged. `ManualRoutes` builds every chapter URL.
- **FR-TREE-003** Default template: top-level chapters per group with folded `<details>` branches (open along the
  current chapter), `ancestors` breadcrumb through `nowo_manual_breadcrumbs_template()` (UiKit `breadcrumbs` partial
  when UiKit is installed, `_breadcrumbs.html.twig` with the same markup otherwise), `children` list, pager through
  `manual_chapter_path()`. Template variables `ancestors`, `children`.
- **FR-TREE-004** `ManualSearchProvider` indexes nested chapters with their own tags and sections under the same
  access, titled `Parent › Child` (`ManualChapter::trailTitle()`).
- **FR-TREE-005** Relative links resolve across the tree from the chapter's source folder (`../x.md`,
  `child/x.md`, `../../x.md`), from a translation's own folder, and as a bare `slug.md` inside a nested chapter;
  images up to `../../../images/`. `manual_href('manual/<folder>/<slug>.md')` maps to the nested route.
- **FR-TREE-006** `TranslationAudit` (outdated / missing anchors), `nowo:manual:stamp` and `nowo:manual:translate`
  read and write `{locale}/{folder}/{slug}.md`.
- **FR-TREE-007** BC: a manifest without `parent` gives byte-identical pages, search results, catalogue and problems
  (golden files from v1.5.1).

## Security notes (new surface, for the independent REQ-SEC-004 audit)

- No request value reaches the filesystem: URL segments are only compared with catalogue slugs; files come from the
  manifest. Folder names are built from manifest slugs (`[A-Za-z0-9-]+`, validated before use; `parent` values that are
  not slugs are rejected) and the resolved file must `realpath()` inside `directory`.
- `file` / `include` rows with a `parent` keep the 1.2/1.3 docs-root allow-list (`externalFile()`), unchanged.
- Access: inheritance can only narrow; 404 for unreadable, wrong-path and unknown pages alike (no oracle); 301
  redirects are issued only after the read check.

## Success criteria

- **SC-T01** 100% PHP line coverage kept; `FlatManualBcTest` green.
- **SC-T02** Demo: three-level example, Playwright e2e for navigation, access and search with axe (WCAG 2.1 AA, no
  violations), screenshot `docs/images/demo/sub-pages.webp`.
