# PHP-FIG PSR evaluation (REQ-CS-007)

Package: `nowo-tech/manual-kit-bundle` (`symfony-bundle`)

This document records which [PHP-FIG PSRs](https://www.php-fig.org/psr/) apply to this package.
Only contracts that add clear interoperability or maintainability value are **Adopted**.
Others are **N/A** (or already covered by Symfony) so the decision stays auditable.

## Table of contents

- [Baseline (always)](#baseline-always)
- [Interface / contract PSRs](#interface--contract-psrs)
- [Summary](#summary)

## Baseline (always)

| PSR | Decision | How |
| --- | -------- | --- |
| PSR-12 (coding style) | **Adopted** | `@PSR12` in `.php-cs-fixer.dist.php` (Nowo REQ-CS-001). |
| PSR-4 (autoloading) | **Adopted** | `composer.json` `autoload` / `autoload-dev` PSR-4 map for `src/` and `tests/`. |

## Interface / contract PSRs

| PSR | Decision | Where |
| --- | -------- | ----- |
| PSR-3 Logger | **N/A** | The bundle logs nothing. |
| PSR-6 Cache | **N/A** | Rendered chapters use Symfony Cache Contracts (`CacheInterface::get()` with a callback), the Symfony-native surface; any PSR-6 pool works behind it. |
| PSR-16 Simple cache | **N/A** | Same as PSR-6. |
| PSR-7 / PSR-17 HTTP messages | **N/A** | Symfony HttpFoundation is the correct surface for controllers. |
| PSR-18 HTTP client | **N/A** | The bundle makes no outgoing HTTP call. |
| PSR-11 Container | **N/A** | Constructor injection only. |
| PSR-14 Event dispatcher | **N/A** | No events of its own (it listens to CommonMark's `DocumentParsedEvent` inside the converter). |
| PSR-15 HTTP middleware | **N/A** | Symfony controller. |
| PSR-20 Clock | **N/A** | No time-dependent logic (cache entries expire through the pool). |

## Summary

- **Adopted beyond baseline:** none.
- **Rule:** do not add `psr/*` Composer dependencies without matching type-hints and DI wiring.

---

_REQ-CS-007 evaluation date: 2026-10-23._
