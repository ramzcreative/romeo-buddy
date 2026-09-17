// <stat-count value="2500" decimals="0">2,500</stat-count>: counts up to the value it already shows, once, when it
// starts below the fold. Timing from --stat-count-duration and --stat-count-ease.
export {};

class StatCount extends HTMLElement {
  private started = false;

  connectedCallback(): void {
    if (this.started) return;
    this.started = true;

    const target = Number(this.getAttribute('value'));
    const final = this.textContent ?? '';

    if (!Number.isFinite(target) || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    if (this.getBoundingClientRect().top < innerHeight) return;

    const decimals = Number(this.getAttribute('decimals')) || 0;
    const format = new Intl.NumberFormat(document.documentElement.lang || undefined, {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    });

    this.style.minInlineSize = `${final.length}ch`;

    // Reset just before it scrolls in, so the page keeps its real value until then.
    const soon = new IntersectionObserver(([entry]) => {
      if (!entry.isIntersecting) return;
      soon.disconnect();

      const digits = document.createElement('span');
      const copy = document.createElement('span');
      digits.setAttribute('aria-hidden', 'true');
      digits.textContent = format.format(0);
      copy.className = 'visually-hidden';
      copy.textContent = final;
      this.replaceChildren(digits, copy);

      const inView = new IntersectionObserver(([seen]) => {
        if (!seen.isIntersecting) return;
        inView.disconnect();
        this.count(target, format, digits, final);
      });
      inView.observe(this);
    }, { rootMargin: '0px 0px 25% 0px' });

    soon.observe(this);
  }

  private count(target: number, format: Intl.NumberFormat, digits: HTMLElement, final: string): void {
    const style = getComputedStyle(this);
    const raw = style.getPropertyValue('--stat-count-duration').trim() || '1000ms';
    const duration = raw.endsWith('ms') ? parseFloat(raw) : parseFloat(raw) * 1000;
    const easing = style.getPropertyValue('--stat-count-ease').trim() || 'ease-out';

    // An empty animation carries the easing; its progress drives the digits.
    const animation = this.animate([{}, {}], { duration: duration || 1000, easing });

    const tick = (): void => {
      const progress = animation.effect?.getComputedTiming().progress;
      if (progress == null) return;
      digits.textContent = format.format(target * progress);
      requestAnimationFrame(tick);
    };

    animation.finished.then(() => {
      this.textContent = final;
    }, () => {
      this.textContent = final;
    });

    requestAnimationFrame(tick);
  }
}

if (!customElements.get('stat-count')) customElements.define('stat-count', StatCount);
