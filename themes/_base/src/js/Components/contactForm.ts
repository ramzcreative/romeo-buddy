// Progressive-enhancement AJAX submission for the [data-contact-form]
// layouts under _blocks/layouts/forms/*.twig. The <form> itself is a real,
// working HTML form (method="POST", a hidden action input) — this only
// intercepts submit to avoid a full-page reload and to populate the
// reCAPTCHA v3 token.
//
// reCAPTCHA sets Google cookies, so — same as any other non-essential
// tracker — its script is only ever loaded after cookie consent is
// granted (see Components/cookieConsent.ts). Declining consent doesn't
// break the form: submission still proceeds via fetch() without a token;
// the server (modules/contactform/Module.php, craft-modules) treats a
// missing token as "consent declined or JS disabled," not spam, and
// relies on the honeypot field instead. If consent WAS granted but
// reCAPTCHA still fails to load in time (blocked, slow network),
// submission falls back to a real page POST rather than trap the
// visitor.

declare global {
  interface Window {
    recaptchaSiteKey?: string;
    consent?: { analytics: boolean };
    grecaptcha?: {
      ready: (callback: () => void) => void;
      execute: (siteKey: string, options: { action: string }) => Promise<string>;
    };
  }
}

const RECAPTCHA_TIMEOUT_MS = 4000;
const RECAPTCHA_SCRIPT_ATTR = 'data-recaptcha-script';

function hasAnalyticsConsent(): boolean {
  return window.consent?.analytics === true;
}

function loadRecaptchaScript(siteKey: string): void {
  if (document.querySelector(`script[${RECAPTCHA_SCRIPT_ATTR}]`)) {
    return;
  }
  const script = document.createElement('script');
  script.src = `https://www.google.com/recaptcha/api.js?render=${siteKey}`;
  script.setAttribute(RECAPTCHA_SCRIPT_ATTR, '');
  document.head.appendChild(script);
}

function waitForRecaptcha(): Promise<NonNullable<Window['grecaptcha']>> {
  return new Promise((resolve, reject) => {
    const timeout = setTimeout(() => {
      reject(new Error('reCAPTCHA did not load in time'));
    }, RECAPTCHA_TIMEOUT_MS);

    const check = () => {
      if (window.grecaptcha) {
        window.grecaptcha.ready(() => {
          clearTimeout(timeout);
          resolve(window.grecaptcha!);
        });
      } else {
        setTimeout(check, 100);
      }
    };
    check();
  });
}

function setMessage(el: HTMLElement, text: string, type: 'success' | 'error'): void {
  el.textContent = text;
  el.hidden = false;
  el.classList.remove('form__message--success', 'form__message--error');
  el.classList.add(`form__message--${type}`);
}

async function handleSubmit(form: HTMLFormElement, event: SubmitEvent): Promise<void> {
  event.preventDefault();

  const tokenField = form.querySelector<HTMLInputElement>('[name="recaptchaToken"]');
  const formNameField = form.querySelector<HTMLInputElement>('[name="message[formName]"]');
  const messageEl = form.querySelector<HTMLElement>('[data-form-message]');
  const submitBtn = form.querySelector<HTMLButtonElement>('button[type="submit"]');

  if (tokenField && window.recaptchaSiteKey && hasAnalyticsConsent()) {
    try {
      const grecaptcha = await waitForRecaptcha();
      tokenField.value = await grecaptcha.execute(window.recaptchaSiteKey, {
        action: formNameField?.value || 'contact',
      });
    } catch {
      // Consent was granted but reCAPTCHA still didn't come through —
      // fall back to a real page submit rather than trap the visitor.
      form.submit();
      return;
    }
  }

  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.dataset.originalText ??= submitBtn.textContent ?? '';
    submitBtn.textContent = 'Sending…';
  }

  try {
    const response = await fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { Accept: 'application/json' },
    });
    const data = await response.json();

    if (messageEl) {
      setMessage(
        messageEl,
        data.message || (response.ok ? 'Thanks — your message has been sent.' : 'Something went wrong. Please try again.'),
        response.ok ? 'success' : 'error',
      );
    }

    if (response.ok) {
      form.reset();
    }
  } catch {
    if (messageEl) {
      setMessage(messageEl, 'Something went wrong. Please try again.', 'error');
    }
  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = submitBtn.dataset.originalText ?? submitBtn.textContent;
    }
  }
}

function init(): void {
  const forms = document.querySelectorAll<HTMLFormElement>('[data-contact-form]');
  if (!forms.length) {
    return;
  }

  forms.forEach((form) => {
    form.addEventListener('submit', (event) => {
      void handleSubmit(form, event as SubmitEvent);
    });
  });

  if (window.recaptchaSiteKey && hasAnalyticsConsent()) {
    loadRecaptchaScript(window.recaptchaSiteKey);
  }

  window.addEventListener('consentchange', ((event: CustomEvent<{ analytics: boolean }>) => {
    if (window.recaptchaSiteKey && event.detail?.analytics) {
      loadRecaptchaScript(window.recaptchaSiteKey);
    }
  }) as EventListener);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
