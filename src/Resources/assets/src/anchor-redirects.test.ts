import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  ANCHORS_ATTRIBUTE,
  MOVED_FROM,
  applyAnchorRedirects,
  decodeFragment,
  isInternalUrl,
  parseAnchorRedirects,
  registerAnchorRedirects,
  resolveAnchorRedirect,
} from './anchor-redirects';

interface FakeWindow {
  location: { href: string; replace: ReturnType<typeof vi.fn> };
  history: { state: unknown; replaceState: ReturnType<typeof vi.fn> };
  listeners: Record<string, () => void>;
  addEventListener: (type: string, listener: () => void) => void;
}

function fakeWindow(href: string): FakeWindow {
  const win: FakeWindow = {
    location: { href, replace: vi.fn() },
    history: { state: { kept: true }, replaceState: vi.fn() },
    listeners: {},
    addEventListener: (type, listener) => {
      win.listeners[type] = listener;
    },
  };

  return win;
}

function page(map: unknown, ids: string[] = []): void {
  const attribute = map === undefined ? '' : ` ${ANCHORS_ATTRIBUTE}="${String(typeof map === 'string' ? map : JSON.stringify(map)).replace(/"/g, '&quot;')}"`;
  document.body.innerHTML = `<article data-chapter="setup"${attribute}>${ids.map((id) => `<h2 id="${id}">${id}</h2>`).join('')}</article>`;
}

const MAP = {
  moved: { '': '/en/manual/setup#start', install: '/en/manual/deploy#docker', 'configuración': '/en/manual/setup#config' },
  own: { legacy: '/en/manual/faq#legacy', evil: '//evil.example/x', scheme: 'javascript:alert(1)' },
};

describe('parseAnchorRedirects', () => {
  it('keeps the string entries of moved and own', () => {
    expect(parseAnchorRedirects('{"moved":{"a":"/x","b":3},"own":{"c":"/y"}}')).toEqual({ moved: { a: '/x' }, own: { c: '/y' } });
  });

  it('yields an empty map for missing, invalid or wrongly shaped data', () => {
    expect(parseAnchorRedirects(null)).toEqual({ moved: {}, own: {} });
    expect(parseAnchorRedirects('{nope')).toEqual({ moved: {}, own: {} });
    expect(parseAnchorRedirects('[1,2]')).toEqual({ moved: {}, own: {} });
    expect(parseAnchorRedirects('{"moved":[1],"own":"x"}')).toEqual({ moved: {}, own: {} });
  });

  it('never resolves inherited keys', () => {
    const map = parseAnchorRedirects('{"own":{}}');

    expect(resolveAnchorRedirect(map, 'constructor', false, () => false)).toBeNull();
    expect(resolveAnchorRedirect(map, '__proto__', false, () => false)).toBeNull();
  });
});

describe('isInternalUrl', () => {
  it('accepts site paths only', () => {
    expect(isInternalUrl('/en/manual/setup#a')).toBe(true);
    expect(isInternalUrl('//evil.example')).toBe(false);
    expect(isInternalUrl('/\\evil.example')).toBe(false);
    expect(isInternalUrl('https://evil.example')).toBe(false);
    expect(isInternalUrl('javascript:alert(1)')).toBe(false);
    expect(isInternalUrl('setup')).toBe(false);
  });
});

describe('decodeFragment', () => {
  it('strips the hash and percent-decodes', () => {
    expect(decodeFragment('#configuraci%C3%B3n')).toBe('configuración');
    expect(decodeFragment('plain')).toBe('plain');
    expect(decodeFragment('')).toBe('');
    expect(decodeFragment('#bad%E0%A4%A')).toBe('bad%E0%A4%A');
  });
});

describe('resolveAnchorRedirect', () => {
  const map = parseAnchorRedirects(JSON.stringify(MAP));

  it('applies the moved anchors after a redirect, even when the anchor is on the page', () => {
    expect(resolveAnchorRedirect(map, 'install', true, () => true)).toBe('/en/manual/deploy#docker');
    expect(resolveAnchorRedirect(map, '', true, () => false)).toBe('/en/manual/setup#start');
    expect(resolveAnchorRedirect(map, 'configuración', true, () => false)).toBe('/en/manual/setup#config');
  });

  it('ignores the moved anchors without a redirect', () => {
    expect(resolveAnchorRedirect(map, 'install', false, () => false)).toBeNull();
    expect(resolveAnchorRedirect(map, '', false, () => false)).toBeNull();
  });

  it('applies the own anchors only when the fragment is not on the page', () => {
    expect(resolveAnchorRedirect(map, 'legacy', false, () => false)).toBe('/en/manual/faq#legacy');
    expect(resolveAnchorRedirect(map, 'legacy', false, () => true)).toBeNull();
    expect(resolveAnchorRedirect(map, 'legacy', true, () => false)).toBe('/en/manual/faq#legacy');
    expect(resolveAnchorRedirect(map, 'unknown', true, () => false)).toBeNull();
  });

  it('never leaves the site', () => {
    expect(resolveAnchorRedirect(map, 'evil', false, () => false)).toBeNull();
    expect(resolveAnchorRedirect(map, 'scheme', false, () => false)).toBeNull();
  });
});

describe('applyAnchorRedirects', () => {
  afterEach(() => {
    document.body.innerHTML = '';
    vi.resetModules();
  });

  it('does nothing on a page without the attribute', () => {
    page(undefined);
    const win = fakeWindow('https://app.test/en/manual/setup#install');

    expect(applyAnchorRedirects(document, win as unknown as Window)).toBeNull();
    expect(win.location.replace).not.toHaveBeenCalled();
  });

  it('after a redirect: drops moved_from from the address bar and moves to the mapped anchor', () => {
    page(MAP, ['install']);
    const win = fakeWindow(`https://app.test/en/manual/setup?${MOVED_FROM}=old-install&tab=1#install`);

    expect(applyAnchorRedirects(document, win as unknown as Window)).toBe('/en/manual/deploy#docker');
    expect(win.history.replaceState).toHaveBeenCalledWith({ kept: true }, '', '/en/manual/setup?tab=1#install');
    expect(win.location.replace).toHaveBeenCalledWith('/en/manual/deploy#docker');
  });

  it('after a redirect with an anchor that did not move: only cleans the address bar', () => {
    page(MAP, ['other']);
    const win = fakeWindow(`https://app.test/en/manual/setup?${MOVED_FROM}=old-install#other`);

    expect(applyAnchorRedirects(document, win as unknown as Window)).toBeNull();
    expect(win.history.replaceState).toHaveBeenCalledWith({ kept: true }, '', '/en/manual/setup#other');
    expect(win.location.replace).not.toHaveBeenCalled();
  });

  it('without a redirect: moves a missing own anchor and leaves a present one', () => {
    page(MAP);
    const missing = fakeWindow('https://app.test/en/manual/setup#legacy');
    expect(applyAnchorRedirects(document, missing as unknown as Window)).toBe('/en/manual/faq#legacy');
    expect(missing.history.replaceState).not.toHaveBeenCalled();

    page(MAP, ['legacy']);
    const present = fakeWindow('https://app.test/en/manual/setup#legacy');
    expect(applyAnchorRedirects(document, present as unknown as Window)).toBeNull();
    expect(present.location.replace).not.toHaveBeenCalled();
  });

  it('survives a broken attribute', () => {
    page('{broken');
    const win = fakeWindow('https://app.test/en/manual/setup#legacy');

    expect(applyAnchorRedirects(document, win as unknown as Window)).toBeNull();
  });

  it('registers a hashchange listener that applies the map again', () => {
    page(MAP);
    const win = fakeWindow('https://app.test/en/manual/setup');
    registerAnchorRedirects(document, win as unknown as Window);
    expect(win.location.replace).not.toHaveBeenCalled();

    win.location.href = 'https://app.test/en/manual/setup#legacy';
    win.listeners.hashchange?.();
    expect(win.location.replace).toHaveBeenCalledWith('/en/manual/faq#legacy');
  });

  it('the standalone entry runs on the real document and window', async () => {
    page(MAP, ['legacy']);
    window.history.replaceState(null, '', `/en/manual/setup?${MOVED_FROM}=old#legacy`);

    await import('./manual-anchors');

    expect(window.location.search).toBe('');
    expect(window.location.hash).toBe('#legacy');
  });
});
