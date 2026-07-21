<?php
/**
 * hybridinteractive/craft-contact-form-extensions settings.
 *
 * Was previously unconfigured — no config file, and nothing ever saved via
 * the CP settings screen, so the plugin was running on its class-property
 * defaults: enableDatabase/enableTemplateOverwrite/enableConfirmationEmail
 * all true, but notificationTemplate/confirmationTemplate/
 * confirmationSubject all empty strings.
 *
 * enableConfirmationEmail is off — Gary doesn't want an email sent to
 * either the site owner or the submitter; the CP's own submissions list
 * (enableDatabase) is the single source of truth here. This also happens
 * to be what was actually crashing production: this plugin's confirmation
 * step builds its "from" address as [fromEmail => fromName], and
 * project.yaml's `email.fromName: $SITE_NAME` resolved to null (SITE_NAME
 * was never set in romeo-buddy's .env, on either local or production),
 * which Symfony's Address constructor rejects — a TypeError, not a
 * graceful failure. Leaving notificationTemplate/enableTemplateOverwrite
 * on: the base craft-contact-form plugin always attempts *a* notification
 * send regardless of these settings (that's core to what it is), so this
 * just controls what that email's body looks like on the rare chance it's
 * ever looked at; a failed send there logs and moves on, it doesn't 500.
 */
return [
    'enableDatabase' => true,

    'enableTemplateOverwrite' => true,
    'notificationTemplate' => '_emails/notification',

    'enableConfirmationEmail' => false,
    'confirmationTemplate' => '_emails/confirmation',
    'confirmationSubject' => 'We received your message',
];
