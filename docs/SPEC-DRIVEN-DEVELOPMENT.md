# Spec-driven development

In this repository, **spec-driven development** has three layers that stay in sync:

1. **GitHub Spec Kit baseline** — [`specs/001-baseline/`](../specs/001-baseline/) ([`spec.md`](../specs/001-baseline/spec.md), [`code-inventory.md`](../specs/001-baseline/code-inventory.md)), initialized with [GitHub Spec Kit](https://github.com/github/spec-kit) (`.specify/`, **Cursor Agent** skills in `.cursor/skills/speckit-*`). The inventory maps **100%** of production code in `src/`. **How to install, initialize, and use Spec Kit:** [`SPEC-KIT.md`](SPEC-KIT.md).
2. **Product behavior** — what **ManualKitBundle** guarantees to applications that integrate it (see [`USAGE.md`](USAGE.md), [`CONFIGURATION.md`](CONFIGURATION.md), [`INSTALLATION.md`](INSTALLATION.md)). **PHPUnit** and **PHPStan** (and **Vitest** when applicable) enforce contracts in CI where applicable.
3. **Traceability anchors** — stable **`REQ-*`** identifiers in Makefiles and demos (when present) so changes to scripts, ports, and demo workflows stay discoverable from issues and PRs.

There is no separate executable spec language (for example Gherkin); Spec Kit specs, tests, and static analysis are the mechanical proof alongside this document.

---

## Table of contents

- [User stories](#user-stories)
- [Bundle functional scope](#bundle-functional-scope)
- [Validating the functional spec](#validating-the-functional-spec)
- [Requirement identifiers (`REQ-*`)](#requirement-identifiers-req-)
- [Suggested workflow for contributors](#suggested-workflow-for-contributors)
- [Relationship to Engram / external checklists](#relationship-to-engram--external-checklists)
- [GitHub Spec Kit (summary)](#github-spec-kit-summary)
- [See also](#see-also)

## User stories

The IDs match the user scenarios of the baseline spec ([`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md#user-scenarios)), where each has Given/When/Then acceptance criteria.

| ID | Story | Integrator docs |
| --- | --- | --- |
| US-01 | **As an** integrator, **I want** to install the bundle, import the routes and point it at `docs/manual` **so that** readers get the manual inside the application. | [INSTALLATION](INSTALLATION.md#requirements) ([routes](INSTALLATION.md#routes), [assets](INSTALLATION.md#publish-assets)) · [CONFIGURATION](CONFIGURATION.md) |
| US-02 | **As a** writer, **I want** one Markdown source that GitHub and the app both render, with in-app links between chapters **so that** the manual is maintained once. | [USAGE — Chapters](USAGE.md#chapters) · [USAGE — Links](USAGE.md#links) |
| US-03 | **As a** reader, **I want** the manual in my language, with a notice when a chapter is untranslated or outdated **so that** I know what I am reading. | [USAGE — Translations](USAGE.md#translations) |
| US-04 | **As an** integrator, **I want** stable anchors **so that** the application's links to sections survive translated headings. | [USAGE — Stable anchors](USAGE.md#stable-anchors) · [USAGE — Linking from the application](USAGE.md#linking-from-the-application) |
| US-05 | **As an** operator, **I want** admin-only chapters and blocks that members never receive (404, fail closed) **so that** internal procedures stay internal. | [USAGE — Audiences](USAGE.md#audiences) · [SECURITY](SECURITY.md) |
| US-06 | **As a** reader, **I want** figures with captions and a zoom **so that** screenshots are readable. | [USAGE — Figures](USAGE.md#figures) · [USAGE — Lightbox](USAGE.md#lightbox) |
| US-07 | **As a** maintainer, **I want** a CI check of missing and outdated translations and missing anchors **so that** translations do not silently rot. | [USAGE — Checking translations](USAGE.md#checking-translations) |
| US-08 | **As an** integrator using SearchKit, **I want** chapters and sections in the header search **so that** help is one shortcut away. | [USAGE — Search](USAGE.md#search) |

**Out of scope:** editing the manual in the browser, a full-text index, PDF export, and behavior outside declared
PHP/Symfony compatibility ranges.

---

## Bundle functional scope

**Goal:** an in-app product manual for Symfony from the repository's `docs/manual` Markdown: one source for GitHub and
the app, translations per locale with stable anchors, audiences that fail closed, figures with a lightbox, search tags.

**Current state:** `1.4.x` (see [`CHANGELOG.md`](CHANGELOG.md)). See [`ARCHITECTURE.md`](ARCHITECTURE.md) for the design.

**In scope**

- Manifest-driven catalog, per-locale resolution and translation states; `nowo:manual:check` / `nowo:manual:stamp`.
- GitHub-compatible rendering (heading ids, links, images), sanitised and cached; audience blocks and chapters.
- Controller (index, chapter, `.md` redirect, sandboxed images), default templates and CSS, Twig link helpers.
- Stimulus lightbox peer and its self-contained Vite build (`standalone_lightbox`), published through the
  `nowo_manual_kit` asset package; SearchKit provider when SearchKit is installed; 7 locales.
- Source-only chapters from other files of the docs root (`file`, `include`), e.g. the CHANGELOG.
- `nowo:manual:translate`: machine translation of missing / outdated chapters (DeepL, LibreTranslate) with request
  timeouts and logging; strict configuration validated at compile time.
- Documented integration (root `README.md` and `docs/`), configuration in [`CONFIGURATION.md`](CONFIGURATION.md), runtime behavior in [`USAGE.md`](USAGE.md).
- Consumer-facing changes in [`CHANGELOG.md`](CHANGELOG.md) and [`UPGRADING.md`](UPGRADING.md).

**Explicit non-goals**

- Writing or translating the content (the host's team does; the bundle reports what is stale).
- Serving files that are not listed chapters or images.
- **`demo/`** UI: illustrative unless explicitly documented as stable API.

---

## Validating the functional spec

- Run **`make qa`** or **`make release-check`** as documented in [`CONTRIBUTING.md`](CONTRIBUTING.md).
- Run **PHPUnit** and **Vitest** locally and in CI for code changes.
- New or changed behavior should add or adjust tests under `tests/` and next to the TypeScript sources (`src/Resources/assets/**/*.test.ts`).

---

## Requirement identifiers (`REQ-*`)

| ID | Where | What it marks |
| --- | --- | --- |
| `REQ-DEMO-005` | `demo/symfony8/Makefile` | Canonical `make up`: `Waiting…`, `Installing…`, `Demo started at:` from `PORT`. |
| `REQ-DEMO-007` | `demo/Makefile`, demo Makefiles | `update-bundle` before demo tests in `release-check`. |
| `REQ-MAKE-001` | Root `Makefile` | Docker-driven development workflow for the bundle. |
| `REQ-MAKE-008` | Root `Makefile` | `update-deps` via shared `.scripts/`. |
| `REQ-MAKE-002` | Root `Makefile` | `release-check` runs its steps sequentially in spec order. |
| `REQ-CS-008` | Root `Makefile` | `make igor` worker-state audit (FrankenPHP). |
| `REQ-TWIG-005` | Root `Makefile` | `check-no-raw-html-form`: no raw `<form>` / `<input>` / `<textarea>` / `<select>` in Twig. |
| `REQ-GIT-001` | Root `Makefile` | `setup-hooks`, `check-no-cursor-coauthor`, `strip-cursor-coauthor-from-history`. |
| `REQ-TEST-011` | Root / demo `Makefile` | `demo-smoke`: the demo boots and answers HTTP 200. |
| `REQ-DEMO-013` | `demo/symfony8/Makefile` | `test-e2e` (Playwright) and `demo-screenshots` (WebP under `docs/images/demo/`). |

When you change scripted behavior, update the existing `REQ-*` comment or add a new ID and document it here.

---

## Suggested workflow for contributors

1. Clarify behavior in an issue or draft PR (product + Makefiles/demos).
2. Implement with tests and static analysis.
3. Anchor scripts and demos when dev UX changes.
4. Ship integrator docs when configuration or public behavior changes.
5. **Keep Spec Kit artifacts in sync** when production code under `src/` changes:
   - Update [`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md) and [`code-inventory.md`](../specs/001-baseline/code-inventory.md).
   - Follow the maintainer checklist in [`SPEC-KIT.md`](SPEC-KIT.md).
   - For **new features**, use Cursor Agent skills (`/speckit-specify`, `/speckit-plan`, `/speckit-tasks`) as documented in SPEC-KIT.

---

## Relationship to Engram / external checklists

[`ENGRAM.md`](ENGRAM.md) covers Nowo-wide documentation checklist items. This document ties together **what the package does**, **how we verify it**, and **local `REQ-*` habits**. Both coexist: Engram for org-level compliance, this file for product + traceability.

---

## GitHub Spec Kit (summary)

This repository uses [GitHub Spec Kit](https://github.com/github/spec-kit) with **Cursor Agent** (`cursor-agent` integration).

| Artifact | Path |
| --- | --- |
| **Operator manual** (install, init, usage) | [`SPEC-KIT.md`](SPEC-KIT.md) |
| Baseline spec | [`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md) |
| Code inventory (100%) | [`specs/001-baseline/code-inventory.md`](../specs/001-baseline/code-inventory.md) |
| Constitution | [`.specify/memory/constitution.md`](../.specify/memory/constitution.md) |
| Cursor Agent skills | [`.cursor/skills/`](../.cursor/skills/) (`speckit-*`) |

**Quick start (maintainers):**

```bash
# Install Specify CLI (once per machine) — see SPEC-KIT.md
specify init --here --force --integration cursor-agent --script sh
specify integration list   # Cursor → installed (default)
```

In Cursor Agent, start a new feature with `/speckit-specify <description>`. For day-to-day tooling details, skills reference, folder layout, and troubleshooting, read **[`SPEC-KIT.md`](SPEC-KIT.md)**.

---

## See also

- [`SPEC-KIT.md`](SPEC-KIT.md) — GitHub Spec Kit manual (install, structure, usage)
- [`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md)
- [`USAGE.md`](USAGE.md)
- [`CONFIGURATION.md`](CONFIGURATION.md)
- [`INSTALLATION.md`](INSTALLATION.md)
- [`CONTRIBUTING.md`](CONTRIBUTING.md)
- [`RELEASE.md`](RELEASE.md)
- [`DEMO-FRANKENPHP.md`](DEMO-FRANKENPHP.md)
