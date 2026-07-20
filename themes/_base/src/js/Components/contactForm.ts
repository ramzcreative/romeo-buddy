// Progressive-enhancement AJAX submission for the [data-contact-form]
// layouts under _blocks/layouts/forms/*.twig. The <form> itself is a real,
// working HTML form (method="POST", a hidden action input) — this only
// intercepts submit to avoid a full-page reload and to populate the
// reCAPTCHA v3 token. If grecaptcha never becomes available (blocked,
// slow network, ad-blocker), submission falls back to a real page POST
// rather than trap the visitor — the server still enforces the honeypot
// either way, it just can't verify a recaptcha score without a token.
// See modules/contactform/Module.php (craft-modules) for the server side.

declare global {
  interface Window {
    recaptchaSiteKey?: string;
    grecaptcha?: {
      ready: (callback: () => void) => void;
      execute: (siteKey: string, options: { action: string }) => Promise<string>;
    };
  }
}

const RECAPTCHA_TIMEOUT_MS = 4000;

function waitForRecaptcha(): Promise<NonNullable<Window['grecaptcha']>> {
  return new Promise((resolve, reject) => {
    if (!window.recaptchaSiteKey) {
      reject(new Error('reCAPTCHA site key not configured'));
      return;
    }

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

  if (tokenField) {
    try {
      const grecaptcha = await waitForRecaptcha();
      tokenField.value = await grecaptcha.execute(window.recaptchaSiteKey!, {
        action: formNameField?.value || 'contact',
      });
    } catch {
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
  document.querySelectorAll<HTMLFormElement>('[data-contact-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      void handleSubmit(form, event as SubmitEvent);
    });
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
