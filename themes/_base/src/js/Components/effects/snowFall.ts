// <snow-fall>: the Theme variant snowfall effect (craft-modules docs/theme-variants-spec.md §4.8).
// A decorative canvas hidden from assistive technology, off under reduced motion, paused while the tab
// is hidden, with a Pause button (WCAG 2.2.2) whose choice is remembered.
// Styling: --snowfall-color, --snowfall-opacity.

export {};

const STORAGE_KEY = 'snowfall-paused';
const MAX_DPR = 1.5;
// Bottom-docked bars the Pause button sits above rather than under.
const DOCKED = '[data-cookie-consent], [data-admin-bar-full]';

type Flake = { x: number; y: number; r: number; speed: number; drift: number; phase: number };

function readPaused(): boolean {
  try {
    return window.localStorage.getItem(STORAGE_KEY) === '1';
  } catch {
    return false;
  }
}

function storePaused(paused: boolean): void {
  try {
    window.localStorage.setItem(STORAGE_KEY, paused ? '1' : '0');
  } catch {
    // Storage blocked: the choice still holds for this page.
  }
}

const PAUSE_ICON = '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><rect x="4" y="3" width="3" height="10" rx="1"/><rect x="9" y="3" width="3" height="10" rx="1"/></svg>';
const PLAY_ICON = '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M5 3.2v9.6a.6.6 0 0 0 .9.5l7.6-4.8a.6.6 0 0 0 0-1L5.9 2.7a.6.6 0 0 0-.9.5z"/></svg>';

const STYLES = `
:host { display: contents; }
canvas {
  position: fixed; inset: 0; width: 100%; height: 100%;
  pointer-events: none; z-index: calc(var(--z-sticky, 9999) - 1);
}
button {
  position: fixed; right: 1rem; bottom: calc(1rem + var(--snowfall-lift, 0px));
  z-index: calc(var(--z-sticky, 9999) - 1);
  display: grid; place-items: center; width: 40px; height: 40px; padding: 0;
  border: 1px solid rgb(255 255 255 / 0.4); border-radius: 999px;
  background: rgb(28 31 36 / 0.88); color: #fff; cursor: pointer;
}
button:focus-visible { outline: 2px solid #fff; outline-offset: 2px; box-shadow: 0 0 0 5px #1c1f24; }
svg { width: 16px; height: 16px; fill: currentColor; }
[hidden] { display: none; }
`;

class SnowFall extends HTMLElement {
  canvas!: HTMLCanvasElement;
  ctx!: CanvasRenderingContext2D;
  button!: HTMLButtonElement;
  flakes: Flake[] = [];
  frame = 0;
  last = 0;
  width = 0;
  height = 0;
  color = '#fff';
  opacity = 0.75;
  dirty = true;
  paused = readPaused();
  reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  docked?: ResizeObserver;

  connectedCallback(): void {
    if (!this.shadowRoot) {
      const root = this.attachShadow({ mode: 'open' });
      const style = document.createElement('style');
      style.textContent = STYLES;

      this.canvas = document.createElement('canvas');
      this.canvas.setAttribute('aria-hidden', 'true');

      this.button = document.createElement('button');
      this.button.type = 'button';
      this.button.title = 'Pause snowfall';
      this.button.setAttribute('aria-label', 'Pause snowfall');
      this.button.addEventListener('click', () => this.setPaused(!this.paused));

      root.append(style, this.canvas, this.button);
    }

    const ctx = this.canvas.getContext('2d');
    if (!ctx) return;
    this.ctx = ctx;

    this.reduced.addEventListener('change', this);
    document.addEventListener('visibilitychange', this);
    window.addEventListener('resize', this);

    this.docked = new ResizeObserver(() => this.lift());
    document.querySelectorAll(DOCKED).forEach((el) => this.docked?.observe(el));

    this.sync();
  }

  disconnectedCallback(): void {
    this.stop();
    this.reduced.removeEventListener('change', this);
    document.removeEventListener('visibilitychange', this);
    window.removeEventListener('resize', this);
    this.docked?.disconnect();
  }

  handleEvent(event: Event): void {
    // Resizes are applied on the next frame, so a phone's toolbar scrolling away doesn't reallocate per event.
    if (event.type === 'resize') {
      this.dirty = true;
      return;
    }

    this.sync();
  }

  setPaused(paused: boolean): void {
    this.paused = paused;
    storePaused(paused);
    this.sync();
  }

  // One place decides whether anything moves: reduced motion hides it all, pause and a hidden tab stop it.
  sync(): void {
    this.button.hidden = this.reduced.matches;
    this.button.setAttribute('aria-pressed', String(this.paused));
    this.button.innerHTML = this.paused ? PLAY_ICON : PAUSE_ICON;

    if (this.reduced.matches || this.paused || document.hidden) {
      this.stop();
      if (this.reduced.matches || this.paused) this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
      return;
    }

    if (!this.frame) {
      this.last = performance.now();
      this.frame = requestAnimationFrame((now) => this.tick(now));
    }
  }

  stop(): void {
    cancelAnimationFrame(this.frame);
    this.frame = 0;
  }

  lift(): void {
    let height = 0;
    document.querySelectorAll<HTMLElement>(DOCKED).forEach((el) => {
      height = Math.max(height, el.offsetHeight);
    });
    this.style.setProperty('--snowfall-lift', `${height}px`);
  }

  resize(): void {
    const dpr = Math.min(window.devicePixelRatio || 1, MAX_DPR);
    this.width = window.innerWidth;
    this.height = window.innerHeight;
    this.canvas.width = Math.round(this.width * dpr);
    this.canvas.height = Math.round(this.height * dpr);
    this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    this.dirty = false;

    const css = getComputedStyle(this);
    this.color = css.getPropertyValue('--snowfall-color').trim() || '#fff';
    this.opacity = parseFloat(css.getPropertyValue('--snowfall-opacity')) || 0.75;

    // Sparse: about one flake per 25,000 px², capped, so small screens get fewer.
    const count = Math.min(80, Math.round((this.width * this.height) / 25000));
    while (this.flakes.length < count) this.flakes.push(this.flake(Math.random() * this.height));
    this.flakes.length = count;
  }

  flake(y: number): Flake {
    return {
      x: Math.random() * this.width,
      y,
      r: 1 + Math.random() * 2.2,
      speed: 18 + Math.random() * 30,
      drift: 6 + Math.random() * 14,
      phase: Math.random() * Math.PI * 2,
    };
  }

  tick(now: number): void {
    // Seconds since the last frame, capped so a stalled tab doesn't jump the flakes.
    const dt = Math.min((now - this.last) / 1000, 0.05);
    this.last = now;

    if (this.dirty) this.resize();

    const { ctx } = this;
    ctx.clearRect(0, 0, this.width, this.height);
    ctx.fillStyle = this.color;
    ctx.globalAlpha = this.opacity;
    ctx.beginPath();

    for (const f of this.flakes) {
      f.y += f.speed * f.r * 0.5 * dt;
      f.phase += dt;
      const x = f.x + Math.sin(f.phase) * f.drift;

      if (f.y - f.r > this.height) Object.assign(f, this.flake(-f.r));

      ctx.moveTo(x + f.r, f.y);
      ctx.arc(x, f.y, f.r, 0, Math.PI * 2);
    }

    ctx.fill();
    this.frame = requestAnimationFrame((t) => this.tick(t));
  }
}

if (!customElements.get('snow-fall')) customElements.define('snow-fall', SnowFall);
