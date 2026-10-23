# ManualKitBundle Constitution

## Core Principles

### I. Documented integrator contract
Product behavior lives in `specs/001-baseline/spec.md`, `docs/SPEC-DRIVEN-DEVELOPMENT.md`, and integrator docs (`USAGE.md`, `CONFIGURATION.md`, `ARCHITECTURE.md`, `SECURITY.md`). Demos are illustrative unless promoted in the spec.

### II. Spec-first, test-proven
PHPUnit, PHPStan, and Vitest are the mechanical proof. Behavioral changes require tests.

### III. 100% code inventory traceability
Every production unit under `src/` (PHP, Twig, translation YAML, the Stimulus peer, the stylesheet) must appear in `specs/001-baseline/code-inventory.md`. New files require spec updates in the same PR.

### IV. The manifest and the roles decide what is served
Only chapters listed in `manifest.yaml` are served; no path from the request reaches the filesystem (slugs are `[A-Za-z0-9-]+`, images are basenames under `images/`). Readers need the configured `role`; `audience: admin` chapters and `<!-- audience: admin -->` blocks need `admin_role`, answer 404 to everyone else (no existence oracle) and fail closed on an unbalanced marker.

### V. Safety defaults
Rendered Markdown goes through the Symfony HTML sanitiser (safe elements, http(s)/mailto links only). Images are served with `nosniff` and a sandboxing CSP, so an SVG never runs script in the application's origin. The lightbox writes captions with `textContent`, never HTML. The SearchKit provider returns only chapters and sections the viewer may read.

### VI. Cursor + Spec Kit
GitHub Spec Kit is initialized with **Cursor Agent** (`cursor-agent`). Skills live in `.cursor/skills/speckit-*`.

### VII. Symfony compatibility
Follow declared PHP/Symfony ranges in `composer.json` and README badges. FrankenPHP worker mode: no per-request state in services. See `docs/FRANKENPHP-WORKER-AUDIT.md`.

## Governance
Amendments update this file, baseline spec when principles affect behavior, and `CHANGELOG.md` when consumer-visible.

Versioning (SemVer): **MAJOR** when a principle is removed or redefined incompatibly; **MINOR** when a principle or section is added or materially expanded; **PATCH** for clarifications and factual corrections that do not change intent.

**Version**: 1.0.0 | **Ratified**: 2026-10-16 | **Last Amended**: 2026-10-16
