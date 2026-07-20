<?php
/**
 * craftcms/contact-form settings
 *
 * toEmail is required by the plugin's own Settings::defineRules() —
 * without it, Mailer::send() throws "The Contact Form settings don't
 * validate." on every submission, before the honeypot/reCAPTCHA checks
 * in modules/contactform (craft-modules) even run.
 */

use craft\helpers\App;

return [
    'toEmail' => App::env('CONTACT_FORM_TO_EMAIL'),
];
