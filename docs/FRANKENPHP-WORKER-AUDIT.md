# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/manual-kit-bundle` (`symfony-bundle`) |
| Audited revision | `1.0` (`main`, 2026-10-23) |
| Audit date | 2026-10-23 |
| Method | Manual review of every service under `src/` + Igor deep audit (`make igor`) + PHPStan FrankenPHP rulesets |
| **Verdict** | ✅ **Viable under scenario B** — every service is stateless; rendered chapters live in the configured cache pool |

## Table of contents

- [Execution model assumed](#execution-model-assumed)
- [Summary](#summary)
- [Rules for future code](#rules-for-future-code)

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This
audit assumes the **strict** variant: the kernel is **not** rebooted between requests.

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` are reset between requests.
- **B — no reset at all:** any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | `ManualCatalog`, `ManualRenderer`, `ManualAccess`, `ManualController`, `TranslationAudit`, `ManualSearchProvider` and both commands are `final readonly`; `ManualLinkExtension` holds only readonly injected collaborators and configuration. The CommonMark environment and the sanitiser are built per render, never kept. |
| Caches | ✅ | Rendered chapters in the configured pool (`cache`, default `cache.app`), keyed by chapter, locale, translation state, audience, file mtime / size, manifest mtime and base URL — a file change is picked up without a worker restart. The manifest is re-read on each call (no in-memory copy). |
| Static properties / `static` locals | ✅ | None |
| Request / user / locale captured in services | ✅ | The controller and the search provider read the request locale and the authorisation checker per call; no service stores them. |
| Superglobals, `putenv`, `ini_set`, `setlocale`, `exit` | ✅ | None in `src/` |
| Blocking I/O and timeouts | ✅ | No outbound HTTP or subprocess; local file reads only. |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker.neon` included in `phpstan.neon.dist` |
| Igor worker-state audit | ✅ | `make igor` in `release-check` (REQ-CS-008) |

## Rules for future code

- Never memoize per-request data (locale, user, rendered HTML) in a service property; use the cache pool or the
  `Request`.
- Any outbound client added later needs explicit timeouts (REQ-RUNTIME-001).

Re-run this audit (and update the table) whenever services are added under `src/`.
