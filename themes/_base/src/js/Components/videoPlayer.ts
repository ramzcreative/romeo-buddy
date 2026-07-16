// Video player wiring for the Video content block (video.twig). Two modes:
//
//  - Click-to-play (default): a thumbnail facade — nothing is fetched from
//    YouTube/Vimeo, and Plyr itself isn't even downloaded, until the
//    visitor clicks it. Plays immediately on that click.
//
//  - Autoplay/background ([data-video-autoplay], the "autoplay" field on
//    the block): muted, looping, no visible Plyr controls other than a
//    small custom pause/play toggle — like the background videos on
//    apple.com/apple-vision-pro. Plyr isn't loaded until the block first
//    scrolls into view. Playback then pauses automatically when scrolled
//    out of view and resumes when back in view, unless the visitor paused
//    it manually via the toggle.
//
// Plyr determines whether a player is html5/YouTube/Vimeo from the
// data-plyr-provider/data-plyr-embed-id attributes on its target element at
// construction time — a blank div defaults to html5, and setting `.source`
// on it afterward doesn't retroactively fix that. So those attributes are
// set on a fresh element *before* `new Plyr()` runs.

async function loadPlyr() {
  const [{ default: Plyr }] = await Promise.all([
    import('plyr'),
    // @ts-expect-error no type declarations for the CSS side-effect import
    import('plyr/dist/plyr.css'),
  ]);
  return Plyr;
}

function buildTarget(provider: string, embedId: string): HTMLDivElement {
  const target = document.createElement('div');
  target.dataset.plyrProvider = provider;
  target.dataset.plyrEmbedId = embedId;
  return target;
}

// ---- Click-to-play ----

function initClickToPlay(item: HTMLElement): void {
  const trigger = item.querySelector<HTMLButtonElement>('[data-video-provider][data-video-id]');
  const container = item.querySelector<HTMLElement>('[data-video-player]');
  if (!trigger || !container) return;

  trigger.addEventListener(
    'click',
    async () => {
      const provider = trigger.dataset.videoProvider;
      const embedId = trigger.dataset.videoId;
      if (!provider || !embedId) return;

      const target = buildTarget(provider, embedId);
      container.replaceChildren(target);

      trigger.hidden = true;
      container.hidden = false;

      const Plyr = await loadPlyr();
      new Plyr(target, {
        youtube: { noCookie: true },
        vimeo: { dnt: true },
        // The click that revealed the player should also start it — Plyr
        // picks this up once the provider's SDK finishes loading, and
        // still counts as user-initiated since it's driven directly off
        // this click handler's own promise chain, not a later timer.
        autoplay: true,
      });
    },
    { once: true },
  );
}

// ---- Autoplay/background mode ----

function initAutoplayItem(item: HTMLElement): void {
  const container = item.querySelector<HTMLElement>('[data-video-player]');
  const toggle = item.querySelector<HTMLButtonElement>('[data-video-toggle]');
  const provider = item.dataset.videoProvider;
  const embedId = item.dataset.videoId;
  if (!container || !toggle || !provider || !embedId) return;

  let player: import('plyr') | null = null;
  let loadingPlayer = false;
  let userPaused = false;

  const setToggleState = (playing: boolean): void => {
    toggle.setAttribute('aria-pressed', String(playing));
    toggle.setAttribute('aria-label', playing ? 'Pause video' : 'Play video');
    toggle.dataset.state = playing ? 'playing' : 'paused';
  };

  const mount = async (): Promise<void> => {
    if (player || loadingPlayer) return;
    loadingPlayer = true;

    const target = buildTarget(provider, embedId);
    container.replaceChildren(target);

    const Plyr = await loadPlyr();
    player = new Plyr(target, {
      controls: [],
      clickToPlay: false,
      youtube: { noCookie: true },
      // Vimeo's own "background" param hides its UI and forces
      // muted/loop-friendly playback, on top of Plyr's own muted/loop below.
      vimeo: { dnt: true, background: true },
      autoplay: true,
      muted: true,
      loop: { active: true },
    });

    player.on('playing', () => setToggleState(true));
    player.on('pause', () => setToggleState(false));

    loadingPlayer = false;
  };

  toggle.addEventListener('click', () => {
    if (!player) return;

    if (player.playing) {
      userPaused = true;
      player.pause();
    } else {
      userPaused = false;
      player.play();
    }
  });

  new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        if (entry.isIntersecting) {
          if (!player) {
            mount();
          } else if (!userPaused) {
            player.play();
          }
        } else if (player?.playing) {
          // Leaving the viewport pauses playback but is never treated as a
          // user choice — re-entering should resume unless they'd paused
          // it themselves via the toggle.
          player.pause();
        }
      }
    },
    { threshold: 0.25 },
  ).observe(item);
}

function init(): void {
  document.querySelectorAll<HTMLElement>('[data-video-item]').forEach((item) => {
    if (item.hasAttribute('data-video-autoplay')) {
      initAutoplayItem(item);
    } else {
      initClickToPlay(item);
    }
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
