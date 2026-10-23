## AI contribution guidelines — Manual Kit Bundle

Follow these rules when contributing PHP, TypeScript, Twig, and documentation to this repository.

---

## Project scope

- **Type:** Standalone Symfony bundle (`nowo-tech/manual-kit-bundle`), in-app product manual from `docs/manual` Markdown (translations per locale, stable anchors, audiences that fail closed, figures with a lightbox, optional SearchKit provider).
- **PHP:** `>=8.4 <8.6` with `declare(strict_types=1);` in every PHP file.
- **Symfony:** **7.4**, **8.0**, and **8.1+** (`^7.4 || ^8.0` on `symfony/*` constraints). Symfony 8 requires PHP 8.4+.
- **Code:** `src/` (`Nowo\ManualKitBundle\`).
- **Frontend:** one opt-in Stimulus peer in `src/Resources/assets/stimulus-peers/` (TypeScript the application bundles; Vitest + **pnpm** for its tests) and plain CSS in `src/Resources/public/css/manual.css`. No build output is published. The Symfony 8 demo compiles the peer with **Pentatrion Vite** (`vite-plugin-symfony`).
- **Language:** PHPDoc, inline comments, and user-facing docs in **English** only.

---

## PHP standards

- PSR-12 + Symfony coding standards; run `make cs-check` before finishing.
- Use **strict comparisons** (`===`) and constructor injection.
- Prefer `final` classes; keep BC on public config keys, route names, and Twig template paths.
- Wire services in `src/Resources/config/services.php`.
- Preserve the DI extension alias: `nowo_manual_kit` (flat tree: one manual per application).
- Controllers use `#[Route]` attributes; route names are prefixed `nowo_manual_` (`nowo_manual_index`, `nowo_manual_chapter`, `nowo_manual_chapter_md`, `nowo_manual_image`).

---

## Bundle-specific conventions

- Twig namespace: `@NowoManualKitBundle`; translation domain: `NowoManualKitBundle` (REQ-TWIG-002 / REQ-I18N-003); `nowo_manual_kit` is only the config root key.
- Public assets under `/bundles/nowomanualkit/` (`assets:install`); the default template links them with `nowo_manual_asset()`.
- CSS classes `.nowo-manual__*`, CSS custom properties `--nowo-manual-*`, DOM hooks `data-testid` / `data-nowo-manual-lightbox-*`, Stimulus identifier `nowo-manual-lightbox`.
- Only chapters listed in `manifest.yaml` are served: no path from the request reaches the filesystem. Admin chapters answer 404 (not 403) to members; `<!-- audience -->` markers fail closed.
- Rendered Markdown always goes through the HTML sanitiser; the lightbox writes captions with `textContent`, never `innerHTML`.
- No per-request state in services (FrankenPHP worker mode).

---

## Tests and quality

- PHPUnit target: **100% line coverage** on `src/` (gate ≥99%); justify exclusions in `docs/COVERAGE.md`.
- TypeScript tests via Vitest: `make test-ts` (thresholds 100% lines / statements / functions, 95% branches; gate ≥90%).
- Full gate: `make release-check` (style, static analysis, PHP + TS coverage, demos).

---

## Documentation

- Keep `README.md` badges and the **Documentation** section aligned with `docs/`.
- Update `docs/CHANGELOG.md` and `docs/UPGRADING.md` for user-visible changes.
- Document installation steps (bundle, routes, config, assets) in `docs/INSTALLATION.md`.

---

## Do not

- Commit secrets, `.env` files, or demo `var/` caches.
- Add Spanish PHPDoc or comments in `src/`.
- Publish a separate npm package for the Stimulus peer.
- Touch unrelated files when fixing a focused issue.

## Commits

- Never add AI co-author trailers: no `Co-authored-by: Cursor` / `cursoragent@cursor.com`, no `Co-Authored-By: Claude …` / `noreply@anthropic.com` (REQ-GIT-001).
