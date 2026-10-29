# Security

## Table of contents

- [Scope](#scope)
- [Attack surface](#attack-surface)
- [Threat model and mitigations](#threat-model-and-mitigations)
- [Secrets and cryptography](#secrets-and-cryptography)
- [Logging](#logging)
- [Dependencies](#dependencies)
- [Permissions and exposure](#permissions-and-exposure)
- [Host responsibilities (residual risks)](#host-responsibilities-residual-risks)
- [AI security audit](#ai-security-audit)
- [Reporting a vulnerability](#reporting-a-vulnerability)
- [Release security checklist (12.4.1)](#release-security-checklist-1241)

## Scope

This document is the security analysis of **ManualKitBundle** (`nowo-tech/manual-kit-bundle`): what it accepts, what can
go wrong and how the bundle prevents it, plus what the host application must do. The short reporting policy lives in
[`.github/SECURITY.md`](../.github/SECURITY.md).

**Covered:** the bundle's PHP code (controller, catalog, renderer, access, Twig extension, commands, SearchKit
provider, DI defaults), its Twig views, the Stimulus peer and the CSS. **Not covered:** the host's users, authentication
and firewall, and the content of the manual (written by the host's team, in the repository).

## Attack surface

| Input | Where | Who |
| --- | --- | --- |
| `GET {prefix}`, `{prefix}/{chapter}`, `{prefix}/{chapter}.md` | `ManualController` | Readers granted `role` (everyone when `role` is empty) |
| `GET {prefix}/images/{file}` | `ManualController::image()` | Same |
| Request locale | `ManualCatalog` (translation folder) | Same |
| Search query text | `ManualSearchProvider` (through SearchKit) | Signed-in SearchKit users |
| Manual files (`manifest.yaml`, `*.md`, `images/`) | Catalog, renderer | Repository contributors (trusted, but rendered through the sanitiser) |
| Configuration (`nowo_manual_kit`) | DI extension | Deployer |
| `nowo:manual:stamp <locale> <chapter>` | Console | Operators |

## Threat model and mitigations

| Threat | Mitigation |
| --- | --- |
| **Path traversal** | No path from the request reaches the filesystem. A chapter is looked up by comparing the slug with the manifest's slugs (`[A-Za-z0-9-]+`, enforced when the manifest is read and by the route requirement); its path comes from the catalog. Images: the route only accepts `[A-Za-z0-9][A-Za-z0-9._-]*` with an image extension, and the file is `{directory}/images/` + `basename()`. The locale only selects a folder when it is one of the configured `locales`. |
| **Existence oracle** | Admin chapters, unknown slugs and the home slug under `/{chapter}` all answer **404**, never 403; links to admin chapters are unwrapped to plain text for members; the sidebar, pager and search list readable chapters only. |
| **Admin content leaking to members** | `<!-- audience: admin -->` blocks are removed from the Markdown before it is parsed. The filter fails closed: an unclosed or malformed marker hides the rest of the chapter from members. `audience: github` blocks never reach the app. |
| **XSS from the Markdown** | CommonMark output goes through the Symfony HtmlSanitizer: safe elements plus `kbd`; link schemes http, https, mailto; media schemes http, https; relative links and media; only the attributes the renderer needs (`id` on headings and anchors, `class` / `target` / `rel` on links, image sizing and loading). `allow_unsafe_links` is off. The lightbox sets captions with `textContent`; an ESLint rule forbids `innerHTML` / `outerHTML` assignments and `insertAdjacentHTML`. |
| **Script in SVG images** | Images are served with `X-Content-Type-Options: nosniff` and `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox`, so an SVG opened directly never runs script in the application's origin; in the page, an SVG is an `<img>` (no script). |
| **Reverse tabnabbing** | External links get `target="_blank" rel="noopener noreferrer"`. |
| **Information leaks** | Images are `private`, max-age one day; chapter pages are rendered per audience and the cache key includes the audience, so a member never gets the admin rendering. |
| **Injection** | No SQL, no shell, no `eval`, no `unserialize`. The manifest is parsed with `Yaml::parseFile()` (no PHP objects). |
| **Stalled third-party HTTP / resource exhaustion** | The only outbound HTTP is the machine-translation provider of the `nowo:manual:translate` console command (off by default, `provider: none`). Every request carries an idle `timeout` (30 s) and a hard `max_duration` (120 s), both configurable and validated (≥ 1 s); a timeout fails the chapter with a domain error. Provider URLs must be absolute `http(s)`; no web request ever calls a provider ([CONFIGURATION](CONFIGURATION.md#timeouts), REQ-RUNTIME-001). |
| **Invalid or unsafe configuration** | The config tree is strict: unknown keys, empty `admin_role` (would show admin content to everybody), malformed locales, non-`http(s)` `docs_url` / provider URLs and missing provider credentials fail the container compile with the path and the allowed values. |
| **Worker-mode state leaks** | No per-request data in shared services (see [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md)); Igor audit in `release-check`. |

## Secrets and cryptography

The bundle ships no secret, stores none and signs nothing. The translation marker is a SHA-256 prefix of public source
text (change detection, not security).

## Logging

What is logged (PSR-3 `logger` service, the application's Monolog channel `app` by default):

| Event | Level | Context |
| --- | --- | --- |
| Machine-translation request started / succeeded | `info` | `bundle`, `provider`, `target_locale`, `texts` (a count) |
| Machine-translation request failed (timeout, transport, HTTP error, malformed answer) | `error` | the same, plus `error` (the exception class) |

What is never logged: the provider API key (sent in a header for DeepL, in the body for LibreTranslate), the chapter
texts or translations, request bodies, user names or roles. Chapter pages, images and the access checks log nothing
themselves (Symfony's own security and HTTP logs apply as configured by the host). There is no `dump()`,
`var_dump()` or `error_log()` in `src/`.

## Dependencies

`composer audit` runs in CI (`Composer audit (Critical/High)` step) and before each release; Dependabot keeps
dependencies current. Security fixes are released as patch versions of the supported line (see
[`.github/SECURITY.md`](../.github/SECURITY.md)).

## Permissions and exposure

| Exposure | Restriction |
| --- | --- |
| Chapters and images | `role` (default `ROLE_USER`); empty `role` = public manual |
| `audience: admin` chapters and blocks | `admin_role` (default `ROLE_ADMIN`); 404 / removed for everyone else |
| SearchKit "Manual" group | The same rules, in the request locale |

## Host responsibilities (residual risks)

- **Firewall:** import the routes behind a firewall that knows the user (a `lazy` firewall is enough); with an empty
  `role`, the manual (and every image in `images/`) is public.
- **Content:** the manual is public to every reader with `role`; keep secrets out of it. Admin-only material belongs in
  `audience: admin` chapters or blocks — check that each block is closed (`nowo:manual:check` does not lint audiences;
  a test that renders every chapter for members is recommended, see [USAGE](USAGE.md#services-for-your-own-code)).
- **Images:** everything under `images/` is served to readers; do not store anything else there.
- **Browser:** a Content-Security-Policy is recommended; the default templates need no inline script.

## AI security audit

| Field | Value |
| --- | --- |
| Date | 2026-10-23 |
| Method | Independent AI static review of the 1.0.0 code (REQ-SEC-004): path traversal, XSS, audience leaks, SVG, cache keys, commands |
| Result | 1 medium, 5 low. Fixed before 1.0.0: images are served to members only when a chapter they can read shows them; the `.md` alias and an admin-only home answer 404 to members (no existence oracle); audience markers are case-insensitive and any malformed `audience` comment cuts the rest for members; `nowo:manual:stamp` only writes translation files of configured locales and manifest chapters. Accepted: the sanitiser keeps `id` on `a` and `class` on `a` / `img` (stable anchors and image sizing); only a malicious Markdown author could use them, and the manual's Markdown is reviewed like code. |
| Recorded in | This file |

## Reporting a vulnerability

Report security issues **privately**:

1. Do **not** open a public GitHub issue for security-sensitive bugs.
2. Use [GitHub Security Advisories](https://github.com/nowo-tech/ManualKitBundle/security/advisories) or email **hectorfranco@nowo.tech**.
3. Include steps to reproduce, affected versions, and impact.
4. We will acknowledge and coordinate disclosure after a fix is available.

See also [`.github/SECURITY.md`](../.github/SECURITY.md) for supported versions and reporting policy.

## Release security checklist (12.4.1)

Before each release, the maintainer confirms each item (note in the release PR or tag message is sufficient).

| Item | Check |
| --- | --- |
| `docs/SECURITY.md` and `.github/SECURITY.md` up to date | ☐ |
| `.env` / secrets in `.gitignore`; no credentials in the repository | ☐ |
| Published config contains no secrets | ☐ |
| Rendered Markdown sanitised; captions via `textContent`; images sandboxed | ☐ |
| Authorization: `role` on every route; admin chapters 404 for members; audience filter fails closed | ☐ |
| No session-authenticated mutation (read-only GET; CSRF not applicable) | ☐ |
| `composer audit` run; known issues reviewed | ☐ |
| No request path reaches the filesystem (manifest slugs, image basenames) | ☐ |
| AI security audit (REQ-SEC-004) passed — latest grade in `BUNDLES_SECURITY_ANALYSIS.md` | ☐ |
