# Manual Kit Bundle — Demo

FrankenPHP demo for **Symfony 8.1** (PHP 8.5) with the in-app manual: a small Markdown manual under
`demo/symfony8/docs/manual` (English at the root, Spanish under `es/`), served at `/{_locale}/manual` inside the demo
layout (`nowo_manual_kit.layout: base.html.twig`). In-memory users, no database. The figure lightbox is the bundle's
Stimulus peer, registered in `assets/app.ts` as `nowo-manual-lightbox`.

## Accounts

| User | Password | Role | Sees |
| --- | --- | --- | --- |
| `demo` | `demo` | `ROLE_USER` | Home and *Getting started*; admin chapters answer 404, admin-only blocks are left out |
| `admin` | `admin` | `ROLE_ADMIN` | Everything, including the *Administration* chapter |

## Quick start

```bash
make -C demo/symfony8 up
```

Default URL: **http://localhost:8177** (`PORT` in `demo/symfony8/.env.example`). Sign in, open **Manual**, click the
figure in *Getting started*, then switch the language to Español: the home and *Getting started* are translated,
*Administration* is shown in English with a "not translated yet" notice.

Translation report:

```bash
docker compose -f demo/symfony8/docker-compose.yml exec php bin/console nowo:manual:check
```

## Commands

| Command | Description |
| ------- | ----------- |
| `make -C demo/symfony8 up` | Build, install dependencies, publish assets (`manual.css`), build the Vite entry |
| `make -C demo/symfony8 down` | Stop containers |
| `make -C demo/symfony8 test` | Sync the bundle, run the demo PHPUnit tests |
| `make -C demo/symfony8 test-e2e` | Playwright happy path (sign-in, chapters, lightbox, Spanish notices) — needs `up` |
| `make -C demo/symfony8 demo-screenshots` | Regenerate `docs/images/demo/overview.webp` (README image) |
| `make demo-smoke` | From the bundle root: boot the demo and assert HTTP 200 (REQ-TEST-011) |
| `make update-deps` | Refresh bundle **and** demo Composer dependencies (from the bundle root) |

From `demo/`: `make up`, `make down`, `make test-symfony8`, `make release-check` (tests + e2e + healthcheck).

**Forms:** the login page is a Symfony Form (`src/Form/LoginType.php`, fields `_username` / `_password`, CSRF field
`_csrf_token`, token id `authenticate`) rendered by `templates/demo/_form.html.twig` with `form_start` / the
`{% for child in form %}{% if not child.rendered %}{{ form_row(child) }}{% endif %}{% endfor %}` loop / `form_end`. No
template writes raw `<form>` / `<input>` tags (REQ-TWIG-005, `make check-no-raw-html-form`).

**Demo-only settings — do not copy:** the plaintext password hasher, the `demo` / `admin` accounts, the GET logout link
and the placeholder `APP_SECRET` exist for the demo only; use the `auto` hasher, a CSRF-protected logout and a real
secret in an application.

See [`docs/DEMO-FRANKENPHP.md`](../docs/DEMO-FRANKENPHP.md) for the FrankenPHP setup (worker / classic, Caddyfile,
troubleshooting).
