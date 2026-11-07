import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ManualLightboxController from './manual_lightbox_controller';

describe('manual lightbox controller', () => {
  let application: Application;

  beforeEach(() => {
    HTMLDialogElement.prototype.showModal ??= function showModal(this: HTMLDialogElement) {
      this.open = true;
    };
    HTMLDialogElement.prototype.close ??= function close(this: HTMLDialogElement) {
      this.open = false;
    };
    document.body.innerHTML = `
      <article data-controller="manual-lightbox" data-manual-lightbox-zoom-label-value="Enlarge" data-action="click->manual-lightbox#zoom">
        <figure><a class="manual-zoom" href="/manual/images/a.webp"><img src="/manual/images/a.webp" alt="First"></a><figcaption>Caption A</figcaption></figure>
        <figure><a class="manual-zoom" href="/manual/images/b.webp"><img src="/manual/images/b.webp" alt="Second"></a></figure>
        <a class="manual-zoom" href="/manual/images/c.webp"><span>Bare link</span></a>
        <dialog data-manual-lightbox-target="dialog" data-action="click->manual-lightbox#backdrop keydown->manual-lightbox#keydown">
          <button type="button" data-manual-lightbox-target="previous" data-action="manual-lightbox#previous">‹</button>
          <img data-manual-lightbox-target="image" alt="">
          <p data-manual-lightbox-target="caption"></p>
          <button type="button" data-manual-lightbox-target="next" data-action="manual-lightbox#next">›</button>
          <button type="button" class="close" data-action="manual-lightbox#close">×</button>
        </dialog>
      </article>
    `;
    application = Application.start();
    application.register('manual-lightbox', ManualLightboxController);
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
  });

  it('labels the zoom links, opens an image with its caption and walks the chapter images', async () => {
    await Promise.resolve();
    const links = document.querySelectorAll<HTMLAnchorElement>('a.manual-zoom');
    expect(links[0]?.getAttribute('aria-label')).toBe('Enlarge: First');
    expect(links[2]?.getAttribute('aria-label')).toBe('Enlarge');
    const dialog = document.querySelector('dialog') as HTMLDialogElement;
    const image = document.querySelector('[data-manual-lightbox-target="image"]') as HTMLImageElement;
    const caption = document.querySelector('[data-manual-lightbox-target="caption"]') as HTMLElement;
    const previous = document.querySelector('[data-manual-lightbox-target="previous"]') as HTMLButtonElement;
    const next = document.querySelector('[data-manual-lightbox-target="next"]') as HTMLButtonElement;

    (links[0]?.querySelector('img') as HTMLImageElement).click();
    expect(dialog.open).toBe(true);
    expect(image.src).toMatch(/\/manual\/images\/a\.webp$/);
    expect(caption.textContent).toBe('Caption A');
    expect(previous.hidden).toBe(true);
    expect(next.hidden).toBe(false);
    (links[1]?.querySelector('img') as HTMLImageElement).click();
    expect([dialog.open, image.alt]).toEqual([true, 'Second']);
    (links[0]?.querySelector('img') as HTMLImageElement).click();

    dialog.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));
    expect(image.alt).toBe('Second');
    expect(caption.textContent).toBe('Second');
    next.click();
    expect(image.alt).toBe('');
    expect(caption.hidden).toBe(true);
    expect(next.hidden).toBe(true);
    next.click();
    expect(image.src).toMatch(/c\.webp$/);
    dialog.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft', bubbles: true }));
    dialog.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft', bubbles: true }));
    expect(image.alt).toBe('First');
    dialog.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
    expect(image.alt).toBe('First');

    caption.click();
    expect(dialog.open).toBe(true);
    dialog.click();
    expect(dialog.open).toBe(false);
    (links[1]?.querySelector('img') as HTMLImageElement).click();
    expect(dialog.open).toBe(true);
    (document.querySelector('.close') as HTMLButtonElement).click();
    expect(dialog.open).toBe(false);
  });

  it('leaves modified clicks and clicks outside the images to the browser', async () => {
    await Promise.resolve();
    const dialog = document.querySelector('dialog') as HTMLDialogElement;
    const link = document.querySelector('a.manual-zoom') as HTMLAnchorElement;
    for (const init of [{ ctrlKey: true }, { metaKey: true }, { shiftKey: true }, { altKey: true }, { button: 1 }]) {
      const click = new MouseEvent('click', { bubbles: true, cancelable: true, ...init });
      link.dispatchEvent(click);
      expect(click.defaultPrevented).toBe(false);
    }
    (document.querySelector('figcaption') as HTMLElement).click();
    expect(dialog.open).toBe(false);
    const controller = application.getControllerForElementAndIdentifier(document.querySelector('article') as HTMLElement, 'manual-lightbox') as ManualLightboxController;
    controller.zoom({ target: null } as unknown as MouseEvent);
    expect(dialog.open).toBe(false);
  });

  it('fades before closing, also on Escape, with no fade without a transition', async () => {
    await new Promise((resolve) => setTimeout(resolve, 0));
    const dialog = document.querySelector('dialog') as HTMLDialogElement;
    const close = vi.spyOn(dialog, 'close');
    const open = (): void => {
      document.querySelector<HTMLAnchorElement>('a.manual-zoom')?.click();
    };
    open();
    const cancel = new Event('cancel', { cancelable: true });
    dialog.dispatchEvent(cancel);
    expect(cancel.defaultPrevented).toBe(true);
    expect(close).toHaveBeenCalledOnce();

    const style = vi.spyOn(window, 'getComputedStyle').mockReturnValue({ transitionDuration: '0.15s', transitionDelay: '0s' } as CSSStyleDeclaration);
    open();
    dialog.dispatchEvent(new Event('cancel', { cancelable: true }));
    expect(close).toHaveBeenCalledOnce();
    expect(dialog.hasAttribute('data-manual-closing')).toBe(true);
    dialog.dispatchEvent(new Event('transitionend'));
    expect(close).toHaveBeenCalledTimes(2);
    expect(dialog.hasAttribute('data-manual-closing')).toBe(false);
    style.mockRestore();
  });
});
