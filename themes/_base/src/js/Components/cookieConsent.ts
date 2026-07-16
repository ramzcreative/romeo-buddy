// Basic GDPR cookie-consent banner. No dependencies, no tag-manager
// integration — this site doesn't ship any analytics/marketing scripts
// today, so there's nothing to actually gate behind consent yet. What this
// does provide: a stored decision, and a `window.consent` flag + a
// `consentchange` event that a future tracker can check/listen for before
// initializing, e.g.:
//
//   if (window.consent?.analytics) { initGoogleAnalytics(); }
//   window.addEventListener('consentchange', (e) => {
//     if (e.detail.analytics) initGoogleAnalytics();
//   });

const STORAGE_KEY = 'cookie-consent';
const ACCEPTED = 'accepted';
const NECESSARY = 'necessary';

declare global {
  interface Window {
    consent?: { analytics: boolean };
  }
}

function readStoredConsent(): string | null {
  try {
    return window.localStorage.getItem(STORAGE_KEY);
  } catch {
    // localStorage unavailable (private browsing, disabled storage, etc.) —
    // the banner will just show again next visit, which is the safe default.
    return null;
  }
}

function storeConsent(value: string): void {
  try {
    window.localStorage.setItem(STORAGE_KEY, value);
  } catch {
    // Nothing we can do if storage is blocked — consent still applies for
    // the rest of this page load via window.consent below.
  }

  window.consent = { analytics: value === ACCEPTED };
  window.dispatchEvent(new CustomEvent('consentchange', { detail: window.consent }));
}

function init(): void {
  const banner = document.querySelector<HTMLElement>('[data-cookie-consent]');
  if (!banner) return;

  const existing = readStoredConsent();
  if (existing === ACCEPTED || existing === NECESSARY) {
    window.consent = { analytics: existing === ACCEPTED };
    return;
  }

  banner.hidden = false;

  const acceptBtn = banner.querySelector<HTMLButtonElement>('[data-cookie-consent-accept]');
  const declineBtn = banner.querySelector<HTMLButtonElement>('[data-cookie-consent-decline]');

  const respond = (value: string) => {
    storeConsent(value);
    banner.hidden = true;
  };

  acceptBtn?.addEventListener('click', () => respond(ACCEPTED));
  declineBtn?.addEventListener('click', () => respond(NECESSARY));
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
