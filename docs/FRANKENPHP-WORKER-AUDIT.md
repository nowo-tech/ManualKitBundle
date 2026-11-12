# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/manual-kit-bundle` (`symfony-bundle`) |
| Audited revision | `v1.9.0` (kept-alive test added 2026-11-12; earlier review kept below) |
| Audit date | 2026-11-12 (earlier audit dates kept in the history) |
| Method | Manual review of every service under `src/` + Igor deep audit (`make igor`) + PHPStan FrankenPHP rulesets |
| **Verdict** | ✅ **Viable under scenario B** — every service is stateless; rendered chapters live in the configured cache pool |

## Table of contents

- [Execution model assumed](#execution-model-assumed)
- [Summary](#summary)
- [Services reviewed](#services-reviewed)
- [Kept-alive test (REQ-RUNTIME-002)](#kept-alive-test-req-runtime-002)
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

## Services reviewed

| Service | State | Reset mechanism |
|---------|-------|-----------------|
| `ManualCatalog`, `ManualRenderer`, `ManualAccess`, `TranslationAudit`, `ManualSearchProvider` | `final readonly`; injected collaborators and configuration only; the manifest is re-read per call | none needed |
| `ManualController` | `final readonly`; the request, locale and user are read per call | none needed |
| `ManualLinkExtension` | readonly injected collaborators and configuration | none needed |
| `CheckTranslationsCommand`, `StampTranslationCommand`, `TranslateManualCommand` | CLI, `final readonly` | none needed |
| Rendered chapters | configured cache pool (`cache.app` by default), key includes chapter, locale, translation state, audience, mtime / size | pool TTL / key change on file change |

## Kept-alive test (REQ-RUNTIME-002)

Test: `Nowo\ManualKitBundle\Tests\Integration\Worker\WorkerKeptAliveKernelTest` (`tests/Integration/Worker/WorkerKeptAliveKernelTest.php`). It uses one booted kernel (`TestKernel`: FrameworkBundle, SecurityBundle, TwigBundle and the bundle, in-memory users `alice` and `admin`) and drives `http_kernel` `handle()` + `terminate()` for the manual chapters in two locales. `services_resetter` / `kernel.reset` is **never** called. It runs 1,200 iterations alternating two users, asserts every response contains only its own data (nothing of the other user or of the previous iteration), and that memory growth after a 100-iteration warm-up stays below 4 MiB.

Result: no state is carried between requests, so the bundle is **safe with kernel reset false**.

Rule 7: the bundle never clears or resets the host's default EntityManager (it has no persistence).

## Rules for future code

- Never memoize per-request data (locale, user, rendered HTML) in a service property; use the cache pool or the
  `Request`.
- Any outbound client added later needs explicit timeouts (REQ-RUNTIME-001).

Re-run this audit (and update the table) whenever services are added under `src/`.
