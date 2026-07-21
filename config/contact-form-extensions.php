<?php
/**
 * hybridinteractive/craft-contact-form-extensions settings.
 *
 * Was previously unconfigured — no config file, and nothing ever saved via
 * the CP settings screen, so the plugin was running on its class-property
 * defaults: enableDatabase/enableTemplateOverwrite/enableConfirmationEmail
 * all true, but notificationTemplate/confirmationTemplate/
 * confirmationSubject all empty strings. Pointing those at the new
 * themes/_base/templates/_emails/* templates below (ported from stables,
 * same setup).
 *
 * Note this plugin's own EVENT_BEFORE_SEND/EVENT_AFTER_SEND listeners
 * (where these settings get used) only run *after* the base
 * craft-contact-form plugin's own settings validate — see
 * config/contact-form.php. Unlike stables, this site's CONTACT_FORM_TO_EMAIL
 * env var was already set, so submissions were likely reaching this plugin
 * fine, just rendering blank/broken email bodies from the empty template
 * paths.
 */
return [
    'enableDatabase' => true,

    'enableTemplateOverwrite' => true,
    'notificationTemplate' => '_emails/notification',

    'enableConfirmationEmail' => true,
    'confirmationTemplate' => '_emails/confirmation',
    'confirmationSubject' => 'We received your message',
];
