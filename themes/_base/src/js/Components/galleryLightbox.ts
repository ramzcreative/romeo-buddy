// <gallery-lightbox>: opens a gallery's links in the native <dialog> rendered inside it (_partials/gallery.twig).
// showModal() makes the page inert and handles Escape; this adds paging, keys, swipe and focus return.
export {};

class GalleryLightbox extends HTMLElement {
  private links: HTMLAnchorElement[] = [];
  private index = 0;
  private opener: HTMLAnchorElement | null = null;
  private dialog!: HTMLDialogElement;
  private image!: HTMLImageElement;
  private caption!: HTMLElement;
  private current!: HTMLElement;
  private swipeX: number | null = null;
  private ready = false;

  connectedCallback(): void {
    // Runs again whenever the element is moved; wire it once.
    if (this.ready) return;
    const dialog = this.querySelector<HTMLDialogElement>('dialog.gallery-lightbox');
    // A ticker's second copy of the row is inert; its links aren't separate images.
    this.links = [...this.querySelectorAll<HTMLAnchorElement>('a.gallery__link')].filter((link) => !link.closest('[inert]'));
    if (!dialog || !this.links.length || typeof dialog.showModal !== 'function') return;

    this.ready = true;
    this.dialog = dialog;
    this.image = dialog.querySelector('.gallery-lightbox__image')!;
    this.caption = dialog.querySelector('.gallery-lightbox__caption')!;
    this.current = dialog.querySelector('.gallery-lightbox__current')!;
    dialog.querySelector('.gallery-lightbox__total')!.textContent = String(this.links.length);

    const single = this.links.length < 2;
    dialog.querySelectorAll<HTMLButtonElement>('.gallery-lightbox__btn--prev, .gallery-lightbox__btn--next').forEach((btn) => {
      btn.hidden = single;
    });

    this.links.forEach((link, i) => {
      link.addEventListener('click', (event) => {
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;
        event.preventDefault();
        this.open(i, link);
      });
    });

    dialog.addEventListener('click', (event) => {
      const target = event.target as HTMLElement;
      if (target === dialog || target.closest('.gallery-lightbox__btn--close')) dialog.close();
      else if (target.closest('.gallery-lightbox__btn--prev')) this.show(this.index - 1);
      else if (target.closest('.gallery-lightbox__btn--next')) this.show(this.index + 1);
    });

    dialog.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowLeft') this.show(this.index - 1);
      else if (event.key === 'ArrowRight') this.show(this.index + 1);
    });

    dialog.addEventListener('pointerdown', (event) => {
      if (event.pointerType !== 'mouse') this.swipeX = event.clientX;
    });
    dialog.addEventListener('pointerup', (event) => {
      if (this.swipeX === null) return;
      const dx = event.clientX - this.swipeX;
      this.swipeX = null;
      if (Math.abs(dx) > 50) this.show(this.index + (dx < 0 ? 1 : -1));
    });

    dialog.addEventListener('close', () => {
      this.opener?.focus();
      this.image.removeAttribute('src');
    });
  }

  private open(i: number, opener: HTMLAnchorElement): void {
    this.opener = opener;
    this.show(i);
    this.dialog.showModal();
  }

  private show(i: number): void {
    const total = this.links.length;
    this.index = (i + total) % total;

    const link = this.links[this.index];
    const thumb = link.querySelector('img');
    const figcaption = link.closest('figure')?.querySelector('figcaption');

    this.image.src = link.href;
    this.image.alt = thumb?.alt ?? '';
    this.caption.textContent = figcaption?.textContent ?? '';
    this.caption.hidden = !figcaption;
    this.current.textContent = String(this.index + 1);

    if (total > 1) {
      [this.index - 1, this.index + 1].forEach((n) => {
        new Image().src = this.links[(n + total) % total].href;
      });
    }
  }
}

if (!customElements.get('gallery-lightbox')) customElements.define('gallery-lightbox', GalleryLightbox);
