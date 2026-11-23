/**
 * Every shipped IIFE entry logs the house "script loaded" line exactly once when it is evaluated.
 */
import { afterEach, describe, expect, it, vi } from 'vitest';

const ENTRIES = [
  ['./manual-lightbox', 'manual-kit/lightbox'],
  ['./manual-anchors', 'manual-kit/anchors'],
  ['./manual-devices', 'manual-kit/devices'],
] as const;

describe.each(ENTRIES)('%s', (entry, name) => {
  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    vi.resetModules();
  });

  it('logs "script loaded" once, without a build id when none is injected', async () => {
    const log = vi.spyOn(console, 'log').mockImplementation(() => undefined);

    await import(/* @vite-ignore */ entry);

    const lines = log.mock.calls.map((call) => String(call[0])).filter((line) => line.includes('script loaded'));
    expect(lines).toHaveLength(1);
    expect(lines[0]).toContain(`[${name}] script loaded`);
    expect(lines[0]).not.toContain('build: ');
  });

  it('includes the injected build id', async () => {
    vi.stubGlobal('__MANUAL_KIT_BUILD_TIME__', 'abc123def456');
    const log = vi.spyOn(console, 'log').mockImplementation(() => undefined);

    await import(/* @vite-ignore */ entry);

    const lines = log.mock.calls.map((call) => String(call[0])).filter((line) => line.includes('script loaded'));
    expect(lines).toHaveLength(1);
    expect(lines[0]).toContain(`[${name}] script loaded, build: `);
    expect(log.mock.calls.find((call) => String(call[0]).includes('script loaded'))?.[2]).toBe('color:#059669');
    expect(JSON.stringify(log.mock.calls)).toContain('abc123def456');
  });
});
