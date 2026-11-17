# Feature Specification: Chapter redirects (old slug / anchor → new)

**Feature Branch**: `feat/chapter-redirects`  
**Created**: 2026-11-17  
**Status**: Implemented (1.11.0, unreleased)  
**Input**: symfony-beacon spec 231 block A, task T002 (manual restructure: about 64 chapters and 478 anchors move).
Contract accepted as written, plus the coordinator's additions: internal targets only (no open redirect), validated at
container build, cycles rejected, chains collapsed, map immutable per deploy (FrankenPHP worker, kernel reset false),
standard and DDD hosts.

**Related**: [`../001-baseline/spec.md`](../001-baseline/spec.md) ·
[`docs/CONFIGURATION.md#redirects`](../../docs/CONFIGURATION.md#redirects) ·
[`docs/USAGE.md#chapter-redirects`](../../docs/USAGE.md#chapter-redirects)

## User Scenarios & Testing

### User Story 1 — Old links keep working (P1)

A reader opens a bookmarked `/es/manual/04-projects-and-issues` and lands on `/es/manual/projects` (301, locale kept);
the `.md` alias, child routes and a path through a parent that moved behave the same way; a chain is one hop.
**Test**: `ChapterRedirectsTest::testAMovedChapterAnswers301ToItsNewPlaceInTheRequestLocale`,
`testMarkdownAndChildRoutesRedirectTheSameWay`, demo `DemoControllerTest::testOldChapterLinksRedirectToTheirNewPlace`.

### User Story 2 — Moved sections (P1)

`/manual/old#install` reaches `/manual/deploy#docker`: the 301 adds `?moved_from=old`, the page prints the anchor map
in `data-nowo-manual-anchors` and `js/manual-anchors.js` moves the reader; a renamed heading of an existing chapter is
mapped when the fragment is not on the page. **Test**:
`ChapterRedirectsTest::testAnOldChapterWithMovedAnchorsLandsWithTheMarkerAndTheAnchorMap`, Vitest
`anchor-redirects.test.ts`, demo e2e `demo.spec.ts`.

### User Story 3 — No existence oracle (P1)

A redirect to a chapter the reader may not open answers exactly like that chapter (401 anonymous, the same 404 byte
for byte for a member); the anchor map never lists targets or anchors the reader cannot see. **Decision**: the access
check runs on the target before the 301. **Test**:
`ChapterRedirectsTest::testARedirectToAChapterTheReaderMayNotOpenAnswersLikeThatChapter`,
`testOwnAnchorsSkipTargetsAndSectionsTheReaderCannotSee`.

### User Story 4 — A safe, checked map (P1)

An absolute URL, `//`, a scheme or a cycle stops the container build; `nowo:manual:redirects` reports missing targets,
chains, dead rules and old slugs in `route_links`. **Test**: `RedirectMapCompilerTest`, `RedirectsWiringTest`,
`RedirectMapLoaderTest`, `RedirectsCommandTest`.

### User Story 5 — Worker mode (P1)

**Test**: `WorkerRedirectsTest` (one kernel, never reset, 1,000 iterations, two users, two locales, memory bound).

## Out of scope

- Rewriting links inside chapters to old slugs (the lint command points at them; the renderer is unchanged).
- Translated console output (the bundle's commands print English, as before).
