import { Controller } from '@hotwired/stimulus';

/**
 * Click a manual image to see it full size in a native <dialog> — caption (figcaption, else the alt text),
 * previous / next across the chapter's images (buttons and arrow keys), Escape / backdrop / close button to leave.
 * Without JavaScript the zoom link simply opens the image.
 */
export default class extends Controller<HTMLElement> {
  static override targets = ['dialog', 'image', 'caption', 'previous', 'next'];

  static override values = { zoomLabel: String };

  declare readonly dialogTarget: HTMLDialogElement;
  declare readonly imageTarget: HTMLImageElement;
  declare readonly captionTarget: HTMLElement;
  declare readonly previousTarget: HTMLButtonElement;
  declare readonly nextTarget: HTMLButtonElement;
  declare readonly zoomLabelValue: string;

  private index = -1;

  override connect(): void {
    this.links().forEach((link) => {
      const alt = link.querySelector('img')?.alt ?? '';
      link.setAttribute('aria-label', `${this.zoomLabelValue}${alt === '' ? '' : `: ${alt}`}`);
    });
  }

  zoom(event: MouseEvent): void {
    const link = (event.target as Element | null)?.closest<HTMLAnchorElement>('a.manual-zoom');
    if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }
    event.preventDefault();
    this.show(this.links().indexOf(link));
    if (!this.dialogTarget.open) {
      this.dialogTarget.showModal();
    }
  }

  previous(): void {
    this.show(this.index - 1);
  }

  next(): void {
    this.show(this.index + 1);
  }

  close(): void {
    this.dialogTarget.close();
  }

  keydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowLeft') {
      this.previous();
    } else if (event.key === 'ArrowRight') {
      this.next();
    }
  }

  /** A click on the backdrop (the dialog itself, outside its content) closes it. */
  backdrop(event: MouseEvent): void {
    if (event.target === this.dialogTarget) {
      this.close();
    }
  }

  private show(index: number): void {
    const links = this.links();
    const link = links[index];
    if (!link) {
      return;
    }
    this.index = index;
    const image = link.querySelector('img');
    const caption = link.closest('figure')?.querySelector('figcaption')?.textContent?.trim() || image?.alt || '';
    this.imageTarget.src = link.href;
    this.imageTarget.alt = image?.alt ?? '';
    this.captionTarget.textContent = caption;
    this.captionTarget.hidden = caption === '';
    this.previousTarget.hidden = index === 0;
    this.nextTarget.hidden = index === links.length - 1;
  }

  private links(): HTMLAnchorElement[] {
    return Array.from(this.element.querySelectorAll<HTMLAnchorElement>('a.manual-zoom'));
  }
}
