# Coverage policy

## Table of contents

- [PHP line coverage gate](#php-line-coverage-gate)
- [TypeScript line coverage gate](#typescript-line-coverage-gate)
- [Justified exclusions](#justified-exclusions)
- [How to refresh](#how-to-refresh)

## PHP line coverage gate

`make coverage-check` enforces **≥ 99%** line coverage on the PHPUnit includable `src/` set (`REQ-TEST-003` /
`REQ-TEST-006`); the target is **100%**.

The README percentage must match the latest coverage text / CI artifact.

## TypeScript line coverage gate

`make test-ts` / `pnpm run test:coverage` run Vitest on `src/Resources/assets` (the Stimulus peer and the standalone entry) with **100%** lines,
functions, statements and branches (`vitest.config.ts` `coverage.thresholds`); the `make test-ts` gate on the minimum
of the four is **≥ 90%**.

## Justified exclusions

`phpunit.xml.dist` excludes `src/Resources` (Twig, translations, CSS, the Stimulus peer and the PHP DI / route files,
which are configuration rather than executable application code). In `Resources/config/services.php` the SearchKit
provider is registered only when `SearchProviderInterface` exists; the test suite always installs SearchKit, so the
"not installed" branch is exercised by applications only. No `@codeCoverageIgnore` annotations are used.

Vitest coverage leaves out `**/*.test.ts`.

Do **not** add new `@codeCoverageIgnore` or exclusions without updating this document.

## How to refresh

```bash
make coverage-check
make test-ts
```
