/**
 * 1.12: client half of the desktop / phone views of a chapter. The server wraps device blocks (and phone companion
 * images) in elements carrying `data-nowo-manual-device="desktop|phone"`; the chapter article carries
 * `data-nowo-manual-devices="<breakpoint>"` (and `data-nowo-manual-device-initial` for a search hit). This script
 * picks the view, shows the switch, and sets `data-nowo-manual-device-active` on the article: the stylesheet hides the
 * other view. No reload, no inline script or style (CSP), no HTML strings.
 *
 * The view: the search hit's (`?device=`, validated by the server and again here), else the reader's last choice
 * (localStorage), else the viewport (phone below the breakpoint). Without JavaScript both views show, labelled.
 */

/** The allow-list of views, in switch order. */
export const DEVICES = ['desktop', 'phone'] as const;

/** A view. */
export type Device = (typeof DEVICES)[number];

/** Article attribute: the breakpoint in CSS px (present only on chapters with device content). */
export const DEVICES_ATTRIBUTE = 'data-nowo-manual-devices';

/** Article attribute: the view a search hit asks for. */
export const INITIAL_ATTRIBUTE = 'data-nowo-manual-device-initial';

/** Article attribute set by this script: the view shown. */
export const ACTIVE_ATTRIBUTE = 'data-nowo-manual-device-active';

/** The switch (a group of buttons with aria-pressed). */
export const SWITCH_ATTRIBUTE = 'data-nowo-manual-device-switch';

/** A button of the switch; its value is the view. */
export const OPTION_ATTRIBUTE = 'data-nowo-manual-device-option';

/** Query parameter of a search hit. */
export const QUERY = 'device';

/** localStorage key of the reader's choice. */
export const STORAGE_KEY = 'nowo-manual-device';

/** Used when the attribute holds no usable number. */
export const DEFAULT_BREAKPOINT = 768;

/**
 * Whether a value is an allowed view.
 *
 * @param value Any value.
 */
export function isDevice(value: unknown): value is Device {
  return typeof value === 'string' && (DEVICES as readonly string[]).includes(value);
}

/**
 * The breakpoint of the attribute (a positive integer), else the default.
 *
 * @param raw Attribute value.
 */
export function parseBreakpoint(raw: string | null): number {
  const value = Number.parseInt(raw ?? '', 10);

  return Number.isInteger(value) && value > 0 ? value : DEFAULT_BREAKPOINT;
}

/**
 * The view the viewport suggests: phone below the breakpoint.
 *
 * @param win Window to measure.
 * @param breakpoint Width in CSS px.
 */
export function viewportDevice(win: Window, breakpoint: number): Device {
  return typeof win.matchMedia === 'function' && win.matchMedia(`(max-width: ${breakpoint - 1}px)`).matches ? 'phone' : 'desktop';
}

/**
 * The reader's stored choice; null when none, invalid, or storage is unavailable (private mode, blocked).
 *
 * @param win Window whose localStorage is read.
 */
export function readStoredDevice(win: Window): Device | null {
  try {
    const value = win.localStorage.getItem(STORAGE_KEY);

    return isDevice(value) ? value : null;
  } catch {
    return null;
  }
}

/**
 * Keep the reader's choice; a storage failure is ignored (the choice then lasts for this page).
 *
 * @param win Window whose localStorage is written.
 * @param device The view chosen.
 */
export function storeDevice(win: Window, device: Device): void {
  try {
    win.localStorage.setItem(STORAGE_KEY, device);
  } catch {
    // Storage blocked or full: nothing to keep.
  }
}

/**
 * The view to show first.
 *
 * @param initial The search hit's view (attribute or `?device=`), unvalidated.
 * @param stored The reader's stored choice.
 * @param viewport The viewport's view.
 */
export function resolveDevice(initial: unknown, stored: Device | null, viewport: Device): Device {
  if (isDevice(initial)) {
    return initial;
  }

  return stored ?? viewport;
}

/**
 * Show a view: the article's active attribute and the switch's pressed state.
 *
 * @param root The chapter article.
 * @param device The view.
 */
export function applyDevice(root: Element, device: Device): void {
  root.setAttribute(ACTIVE_ATTRIBUTE, device);
  root.querySelectorAll<HTMLElement>(`[${OPTION_ATTRIBUTE}]`).forEach((option) => {
    option.setAttribute('aria-pressed', option.getAttribute(OPTION_ATTRIBUTE) === device ? 'true' : 'false');
  });
}

/**
 * Set up the views of the chapter on the page, if it has device content.
 *
 * @param doc Document holding the chapter.
 * @param win Window (viewport, storage, location, history).
 * @returns The view shown first, or null when the page has no device content.
 */
export function registerDeviceViews(doc: Document = document, win: Window = window): Device | null {
  const root = doc.querySelector(`[${DEVICES_ATTRIBUTE}]`);
  if (root === null) {
    return null;
  }
  const url = new URL(win.location.href);
  const initial = root.getAttribute(INITIAL_ATTRIBUTE) ?? url.searchParams.get(QUERY);
  const device = resolveDevice(initial, readStoredDevice(win), viewportDevice(win, parseBreakpoint(root.getAttribute(DEVICES_ATTRIBUTE))));
  applyDevice(root, device);

  root.querySelectorAll<HTMLElement>(`[${SWITCH_ATTRIBUTE}]`).forEach((group) => {
    group.hidden = false;
    group.addEventListener('click', (event) => {
      const option = (event.target as Element | null)?.closest(`[${OPTION_ATTRIBUTE}]`);
      const chosen = option?.getAttribute(OPTION_ATTRIBUTE);
      if (!isDevice(chosen)) {
        return;
      }
      applyDevice(root, chosen);
      storeDevice(win, chosen);
      // The reader's choice now wins over the search hit's: keep the shared address free of it.
      const current = new URL(win.location.href);
      if (current.searchParams.has(QUERY)) {
        current.searchParams.delete(QUERY);
        win.history.replaceState(win.history.state, '', current.pathname + current.search + current.hash);
      }
    });
  });

  // The browser scrolled to the fragment with both views shown: hiding one moved the target.
  const fragment = decodeFragment(url.hash);
  if (fragment !== '') {
    doc.getElementById(fragment)?.scrollIntoView();
  }

  return device;
}

function decodeFragment(hash: string): string {
  const fragment = hash.startsWith('#') ? hash.slice(1) : hash;
  try {
    return decodeURIComponent(fragment);
  } catch {
    return fragment;
  }
}
