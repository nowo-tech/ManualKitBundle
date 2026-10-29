import { Application } from '@hotwired/stimulus';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ManualLightboxController from '../stimulus-peers/manual_lightbox_controller';
import { LIGHTBOX_IDENTIFIER, registerManualLightbox } from './register-lightbox';

describe('registerManualLightbox', () => {
  afterEach(() => {
    vi.restoreAllMocks();
    vi.resetModules();
    document.body.innerHTML = '';
  });

  it('registers the controller on the given application', () => {
    const application = { register: vi.fn() } as unknown as Application;

    expect(registerManualLightbox(application)).toBe(application);
    expect(application.register).toHaveBeenCalledWith(LIGHTBOX_IDENTIFIER, ManualLightboxController);
    expect(LIGHTBOX_IDENTIFIER).toBe('nowo-manual-lightbox');
  });

  it('starts its own application when none is given', () => {
    const application = { register: vi.fn() } as unknown as Application;
    const start = vi.spyOn(Application, 'start').mockReturnValue(application);

    expect(registerManualLightbox()).toBe(application);
    expect(start).toHaveBeenCalledOnce();
  });

  it('the standalone entry connects the lightbox on the chapter markup', async () => {
    document.body.innerHTML = '<article data-controller="nowo-manual-lightbox" data-nowo-manual-lightbox-zoom-label-value="Zoom">'
      + '<a class="manual-zoom" href="/a.png"><img src="/a.png" alt="A"></a>'
      + '<dialog data-nowo-manual-lightbox-target="dialog"><img data-nowo-manual-lightbox-target="image"><p data-nowo-manual-lightbox-target="caption"></p>'
      + '<button data-nowo-manual-lightbox-target="previous"></button><button data-nowo-manual-lightbox-target="next"></button></dialog></article>';

    await import('./manual-lightbox');
    await vi.waitFor(() => {
      expect(document.querySelector('a.manual-zoom')?.getAttribute('aria-label')).toBe('Zoom: A');
    });
  });
});
