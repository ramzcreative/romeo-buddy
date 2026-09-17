// <ticker-row>: the loop itself is CSS (blocks/ticker.pcss). This only wires the pause button.
export {};

class TickerRow extends HTMLElement {
  private ready = false;

  connectedCallback(): void {
    if (this.ready) return;
    const button = this.querySelector<HTMLButtonElement>('[data-ticker-pause]');
    const label = button?.querySelector<HTMLElement>('[data-ticker-label]');
    if (!button || !label) return;

    this.ready = true;
    const pause = label.textContent || 'Pause';
    const play = button.dataset.playLabel || 'Play';

    button.addEventListener('click', () => {
      const paused = !this.hasAttribute('paused');
      this.toggleAttribute('paused', paused);
      button.setAttribute('aria-pressed', String(paused));
      label.textContent = paused ? play : pause;
    });
  }
}

if (!customElements.get('ticker-row')) customElements.define('ticker-row', TickerRow);
