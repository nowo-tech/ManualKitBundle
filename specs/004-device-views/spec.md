# Feature Specification: Desktop / phone views of a chapter

**Feature Branch**: `feat/device-views`  
**Created**: 2026-11-17  
**Status**: Implemented (1.12.0, unreleased)  
**Input**: symfony-beacon spec 231, owner decision D-20261117-21: the manual shows the desktop and the phone way of
doing something wherever they differ (filters as a bottom sheet, scrolling tab rows, the task drawer, kebab / long
press instead of page actions and right-click menus). Most sections are the same on both.

**Related**: [`../001-baseline/spec.md`](../001-baseline/spec.md) ·
[`docs/CONFIGURATION.md#device_views`](../../docs/CONFIGURATION.md#device_views) ·
[`docs/USAGE.md#desktop-and-phone-views`](../../docs/USAGE.md#desktop-and-phone-views)

## User Scenarios & Testing

### User Story 1 — Device blocks (P1)

An author writes `<!-- device: phone -->` … `<!-- /device -->` and `<!-- device: desktop -->` … `<!-- /device -->`
where the phone differs; the rest is shared. GitHub shows both (the block's bold label tells them apart).
**Test**: `DeviceViewsRendererTest::testDeviceBlocksBecomeLabelledWrappersAndTheTocKnowsTheirDevice`,
`testMalformedAndNestedMarkersShareTheirContent`.

### User Story 2 — The switch (P1)

A reader sees a Desktop / Phone switch on chapters that have device content only; the first view follows a search
hit, then their last choice, then the viewport; switching needs no reload; keyboard and screen reader friendly;
CSP-safe. **Test**: `DeviceViewsTest` (functional), Vitest `device-views.test.ts`, demo e2e `devices.spec.ts`.

### User Story 3 — Phone screenshots (P2)

`images/x.webp` with `images/x.phone.webp` next to it shows the phone screenshot in the phone view, without a block.
**Test**: `DeviceViewsRendererTest::testPhoneCompanionsSwapOrTwinTheImage`,
`testACompanionOutsideTheImagesDirectoryIsIgnored`.

### User Story 4 — Search and translations (P2)

Both views are indexed; a device-specific hit opens its view (`?device=`). Stamps and machine translation treat the
blocks as content. **Test**: `DeviceViewsTest::testASearchHitInADeviceSectionOpensThatView`,
`testTranslateKeepsTheDeviceMarkers`, `MarkdownTranslatorTest`, `TranslationAuditTest`.

### User Story 5 — Nothing changes when off (P1)

With `device_views.enabled: false` (default) the output is byte for byte the 1.11.0 one. **Test**: `DeviceViewsBcTest`
(golden files written on v1.11.0).

## Requirements

- **FR-DEVICE-001**: device blocks parsed with the audience rules (shared `CommentBlocks`); only `desktop` / `phone`;
  content converted and sanitised like the chapter; per-rendering random token, wrappers built on the sanitised DOM;
  markers inside fenced code kept.
- **FR-DEVICE-002**: the switch (`js/manual-devices.js`, external, no inline script or style), `aria-pressed`
  buttons in a labelled group, `localStorage` in try/catch, viewport default from `breakpoint`, UiKit tokens.
- **FR-DEVICE-003**: phone companions by name (`image_suffix`, validated), resolved with `realpath()` inside
  `{directory}/images` only.
- **FR-DEVICE-004**: search hits in device sections link to `?device=<view>`; `device` checked against the allow-list
  on the server and in the script.
- **FR-DEVICE-005**: `enabled: false` → 1.11.0 output; no request state in services (FrankenPHP worker, kernel reset
  false).
