import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  ACTIVE_ATTRIBUTE,
  DEFAULT_BREAKPOINT,
  STORAGE_KEY,
  applyDevice,
  isDevice,
  parseBreakpoint,
  readStoredDevice,
  registerDeviceViews,
  resolveDevice,
  storeDevice,
  viewportDevice,
} from './device-views';

/** The markup the chapter template and the renderer print. */
function page(attributes = 'data-nowo-manual-devices="768"'): HTMLElement {
  document.body.innerHTML = `
    <article ${attributes}>
      <div data-nowo-manual-device-switch hidden role="group" aria-label="View for">
        <button type="button" aria-pressed="false" data-nowo-manual-device-option="desktop">Desktop</button>
        <button type="button" aria-pressed="false" data-nowo-manual-device-option="phone">Phone</button>
        <span class="not-an-option">x</span>
      </div>
      <div data-nowo-manual-device="desktop"><h3 id="desk">Desk</h3></div>
      <div data-nowo-manual-device="phone"><h3 id="sheet">Sheet</h3></div>
    </article>`;

  return document.querySelector('article') as HTMLElement;
}

interface FakeWindow {
  location: { href: string };
  history: { state: unknown; replaceState: ReturnType<typeof vi.fn> };
  localStorage: Storage;
  matchMedia?: (query: string) => { matches: boolean };
}

function memoryStorage(initial: Record<string, string> = {}): Storage {
  const data = new Map(Object.entries(initial));

  return {
    getItem: (key: string) => data.get(key) ?? null,
    setItem: (key: string, value: string) => void data.set(key, value),
    removeItem: (key: string) => void data.delete(key),
    clear: () => data.clear(),
    key: () => null,
    get length() {
      return data.size;
    },
  };
}

function blockedStorage(): Storage {
  const fail = (): never => {
    throw new DOMException('blocked', 'SecurityError');
  };

  return { getItem: fail, setItem: fail, removeItem: fail, clear: fail, key: fail, length: 0 };
}

function fakeWindow(href: string, width = 1200, storage: Storage = memoryStorage()): FakeWindow {
  return {
    location: { href },
    history: { state: null, replaceState: vi.fn() },
    localStorage: storage,
    matchMedia: (query: string) => ({ matches: width <= Number(/max-width: (\d+)px/.exec(query)?.[1]) }),
  };
}

afterEach(() => {
  document.body.innerHTML = '';
  vi.restoreAllMocks();
});

describe('device views helpers', () => {
  it('accepts only the allow-listed views', () => {
    expect(isDevice('phone')).toBe(true);
    expect(isDevice('desktop')).toBe(true);
    expect(isDevice('tablet')).toBe(false);
    expect(isDevice('Phone')).toBe(false);
    expect(isDevice(null)).toBe(false);
    expect(isDevice(['phone'])).toBe(false);
  });

  it('parses the breakpoint or falls back to the default', () => {
    expect(parseBreakpoint('900')).toBe(900);
    expect(parseBreakpoint('0')).toBe(DEFAULT_BREAKPOINT);
    expect(parseBreakpoint('wide')).toBe(DEFAULT_BREAKPOINT);
    expect(parseBreakpoint(null)).toBe(DEFAULT_BREAKPOINT);
  });

  it('picks the view from the viewport', () => {
    expect(viewportDevice(fakeWindow('https://a.test/', 375) as unknown as Window, 768)).toBe('phone');
    expect(viewportDevice(fakeWindow('https://a.test/', 767) as unknown as Window, 768)).toBe('phone');
    expect(viewportDevice(fakeWindow('https://a.test/', 768) as unknown as Window, 768)).toBe('desktop');
    const noMedia = fakeWindow('https://a.test/', 375);
    delete noMedia.matchMedia;
    expect(viewportDevice(noMedia as unknown as Window, 768)).toBe('desktop');
  });

  it('reads and writes the stored choice and survives blocked storage', () => {
    const win = fakeWindow('https://a.test/', 1200, memoryStorage({ [STORAGE_KEY]: 'tablet' }));
    expect(readStoredDevice(win as unknown as Window)).toBeNull();
    storeDevice(win as unknown as Window, 'phone');
    expect(readStoredDevice(win as unknown as Window)).toBe('phone');

    const blocked = fakeWindow('https://a.test/', 1200, blockedStorage());
    expect(readStoredDevice(blocked as unknown as Window)).toBeNull();
    expect(() => storeDevice(blocked as unknown as Window, 'phone')).not.toThrow();
  });

  it('prefers the search hit, then the stored choice, then the viewport', () => {
    expect(resolveDevice('phone', 'desktop', 'desktop')).toBe('phone');
    expect(resolveDevice('tablet', 'desktop', 'phone')).toBe('desktop');
    expect(resolveDevice(null, null, 'phone')).toBe('phone');
  });

  it('marks the active view and the pressed button', () => {
    const root = page();
    applyDevice(root, 'phone');
    expect(root.getAttribute(ACTIVE_ATTRIBUTE)).toBe('phone');
    expect(Array.from(root.querySelectorAll('button')).map((button) => button.getAttribute('aria-pressed'))).toEqual(['false', 'true']);
  });
});

describe('registerDeviceViews', () => {
  it('does nothing on a chapter without device content', () => {
    document.body.innerHTML = '<article></article>';
    expect(registerDeviceViews(document, fakeWindow('https://a.test/') as unknown as Window)).toBeNull();
  });

  it('shows the switch, takes the viewport default and keeps the reader\'s choice without a reload', () => {
    const root = page();
    const storage = memoryStorage();
    const win = fakeWindow('https://a.test/en/manual/tasks', 375, storage);

    expect(registerDeviceViews(document, win as unknown as Window)).toBe('phone');
    const group = root.querySelector('[data-nowo-manual-device-switch]') as HTMLElement;
    expect(group.hidden).toBe(false);
    expect(root.getAttribute(ACTIVE_ATTRIBUTE)).toBe('phone');

    (root.querySelector('[data-nowo-manual-device-option="desktop"]') as HTMLButtonElement).click();
    expect(root.getAttribute(ACTIVE_ATTRIBUTE)).toBe('desktop');
    expect(storage.getItem(STORAGE_KEY)).toBe('desktop');
    expect(win.history.replaceState).not.toHaveBeenCalled();

    (root.querySelector('.not-an-option') as HTMLElement).click();
    expect(root.getAttribute(ACTIVE_ATTRIBUTE)).toBe('desktop');
  });

  it('works with the keyboard: the options are native buttons', () => {
    const root = page();
    registerDeviceViews(document, fakeWindow('https://a.test/', 1200) as unknown as Window);
    const phone = root.querySelector('[data-nowo-manual-device-option="phone"]') as HTMLButtonElement;
    expect(phone.tagName).toBe('BUTTON');
    phone.focus();
    expect(document.activeElement).toBe(phone);
    phone.click(); // Enter and Space activate a focused button natively.
    expect(phone.getAttribute('aria-pressed')).toBe('true');
  });

  it('opens the view of a search hit, scrolls to its section and drops the parameter once the reader chooses', () => {
    const root = page('data-nowo-manual-devices="768" data-nowo-manual-device-initial="phone"');
    const scroll = vi.fn();
    (document.getElementById('sheet') as HTMLElement).scrollIntoView = scroll;
    const win = fakeWindow('https://a.test/en/manual/tasks?device=phone&x=1#sheet', 1200, memoryStorage({ [STORAGE_KEY]: 'desktop' }));

    expect(registerDeviceViews(document, win as unknown as Window)).toBe('phone');
    expect(scroll).toHaveBeenCalled();

    (root.querySelector('[data-nowo-manual-device-option="desktop"]') as HTMLButtonElement).click();
    expect(win.history.replaceState).toHaveBeenCalledWith(null, '', '/en/manual/tasks?x=1#sheet');
  });

  it('reads an allow-listed ?device= when the server printed none, and ignores others', () => {
    page();
    expect(registerDeviceViews(document, fakeWindow('https://a.test/?device=phone', 1200) as unknown as Window)).toBe('phone');
    page();
    expect(registerDeviceViews(document, fakeWindow('https://a.test/?device=%3Cscript%3E', 1200) as unknown as Window)).toBe('desktop');
  });

  it('ignores a fragment that is not on the page or is malformed', () => {
    page();
    expect(registerDeviceViews(document, fakeWindow('https://a.test/#missing', 1200) as unknown as Window)).toBe('desktop');
    page();
    expect(registerDeviceViews(document, fakeWindow('https://a.test/#%E0%A4%A', 1200) as unknown as Window)).toBe('desktop');
  });

  it('the standalone entry runs on the real document and window', async () => {
    const root = page('data-nowo-manual-devices="768" data-nowo-manual-device-initial="phone"');

    await import('./manual-devices');

    expect(root.getAttribute(ACTIVE_ATTRIBUTE)).toBe('phone');
  });
});
