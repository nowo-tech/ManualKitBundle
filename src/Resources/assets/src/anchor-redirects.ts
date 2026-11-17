/**
 * 1.11: client half of the chapter redirect map. A 301 cannot see the #fragment, so the chapter page carries the
 * anchors that moved in `data-nowo-manual-anchors` (`{moved: {anchor: url}, own: {anchor: url}}`, printed by the
 * server) and this script sends the reader on:
 *
 * - `moved`: the anchors of the old chapter the reader came from (`?moved_from=<old-slug>`, added by the 301): applied
 *   even when the same anchor exists on the page (`''` = the reader came without a fragment);
 * - `own`: the sections of this chapter that moved: applied only when the fragment is not on the page.
 *
 * External file only (CSP: no inline script, no eval). Without JavaScript the reader still lands on the new chapter.
 */

/** Attribute that holds the map (JSON). */
export const ANCHORS_ATTRIBUTE = 'data-nowo-manual-anchors';

/** Query parameter the 301 adds; removed from the address bar once read. */
export const MOVED_FROM = 'moved_from';

/** The map printed by the chapter template. */
export interface AnchorRedirects {
  moved: Record<string, string>;
  own: Record<string, string>;
}

/**
 * Parse the attribute; anything that is not the expected shape yields an empty map.
 *
 * @param raw Attribute value.
 */
export function parseAnchorRedirects(raw: string | null): AnchorRedirects {
  const data = parseJson(raw ?? 'null');
  const record = isRecord(data) ? data : {};

  return { moved: strings(record.moved), own: strings(record.own) };
}

/**
 * Whether a URL stays on this site: a path starting with one `/` (never `//host` or `/\host`, never a scheme).
 *
 * @param url URL from the map.
 */
export function isInternalUrl(url: string): boolean {
  return /^\/(?![/\\])/.test(url);
}

/**
 * The fragment without `#`, percent-decoded (heading ids may hold accented letters).
 *
 * @param hash `location.hash`.
 */
export function decodeFragment(hash: string): string {
  const fragment = hash.startsWith('#') ? hash.slice(1) : hash;
  try {
    return decodeURIComponent(fragment);
  } catch {
    return fragment;
  }
}

/**
 * Where to send the reader, or null to stay.
 *
 * @param map The page's map.
 * @param fragment Decoded fragment (`''` = none).
 * @param movedFrom Whether the page was reached through a chapter redirect (`?moved_from`).
 * @param onPage Whether an element with this id is on the page.
 */
export function resolveAnchorRedirect(
  map: AnchorRedirects,
  fragment: string,
  movedFrom: boolean,
  onPage: (id: string) => boolean,
): string | null {
  let target: string | undefined;
  if (movedFrom) {
    target = lookup(map.moved, fragment);
  }
  if (target === undefined && fragment !== '' && !onPage(fragment)) {
    target = lookup(map.own, fragment);
  }

  return target !== undefined && isInternalUrl(target) ? target : null;
}

/**
 * Read the map of the page and move the reader when the fragment moved.
 *
 * @param doc Document holding the chapter.
 * @param win Window whose location is read and replaced.
 * @returns The URL the reader is sent to, or null.
 */
export function applyAnchorRedirects(doc: Document, win: Window): string | null {
  const element = doc.querySelector(`[${ANCHORS_ATTRIBUTE}]`);
  if (element === null) {
    return null;
  }
  const map = parseAnchorRedirects(element.getAttribute(ANCHORS_ATTRIBUTE));
  const url = new URL(win.location.href);
  const movedFrom = url.searchParams.has(MOVED_FROM);
  if (movedFrom) {
    // The marker has done its job: keep the address the reader would share clean.
    url.searchParams.delete(MOVED_FROM);
    win.history.replaceState(win.history.state, '', url.pathname + url.search + url.hash);
  }
  const target = resolveAnchorRedirect(map, decodeFragment(url.hash), movedFrom, (id) => doc.getElementById(id) !== null);
  if (target !== null) {
    win.location.replace(target);
  }

  return target;
}

/**
 * Apply the map now and whenever the fragment changes (an old in-page link).
 *
 * @param doc Document holding the chapter.
 * @param win Window to watch.
 */
export function registerAnchorRedirects(doc: Document = document, win: Window = window): void {
  applyAnchorRedirects(doc, win);
  win.addEventListener('hashchange', () => {
    applyAnchorRedirects(doc, win);
  });
}

function parseJson(raw: string): unknown {
  try {
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function strings(value: unknown): Record<string, string> {
  const result: Record<string, string> = Object.create(null) as Record<string, string>;
  if (!isRecord(value)) {
    return result;
  }
  for (const [key, entry] of Object.entries(value)) {
    if (typeof entry === 'string') {
      result[key] = entry;
    }
  }

  return result;
}

function lookup(map: Record<string, string>, key: string): string | undefined {
  return Object.prototype.hasOwnProperty.call(map, key) ? map[key] : undefined;
}
